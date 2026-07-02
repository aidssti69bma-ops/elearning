<?php
require_once 'includes/config.php';
requireLogin();

$cid = (int)($_GET['course_id'] ?? 0);
$rid = (int)($_GET['result_id'] ?? 0);
if (!$cid || !$rid) redirect('/index.php');

$uid    = $_SESSION['user_id'];
$course = $conn->query("SELECT * FROM courses WHERE id=$cid AND is_active=1")->fetch_assoc();
if (!$course) redirect('/index.php');

// ผลของ user คนนี้
$result = $conn->query("SELECT * FROM quiz_results WHERE id=$rid AND user_id=$uid AND course_id=$cid AND quiz_type='post'")->fetch_assoc();
if (!$result) redirect('/index.php');

$pct    = $result['total'] > 0 ? round($result['score'] / $result['total'] * 100) : 0;
$passed = $result['passed'];

// ===== สถิติรวมทุกคน (post-test เท่านั้น) =====
$stats = $conn->query("
    SELECT 
        ROUND(AVG(score/total*100), 1) AS avg_pct,
        MAX(score/total*100)           AS max_pct,
        MIN(score/total*100)           AS min_pct,
        COUNT(*)                       AS total_attempts,
        SUM(passed)                    AS total_passed
    FROM quiz_results qr
    JOIN users u ON u.id = qr.user_id
    WHERE qr.course_id=$cid AND qr.quiz_type='post' AND u.role='user'
")->fetch_assoc();

// ===== วิเคราะห์รายข้อ =====
$questions = $conn->query("
    SELECT id, sort_order, question, correct_ans 
    FROM questions 
    WHERE course_id=$cid AND is_active=1 
    ORDER BY sort_order
")->fetch_all(MYSQLI_ASSOC);

$qStats = [];
foreach ($questions as $q) {
    $qid = $q['id'];
    $r = $conn->query("
        SELECT 
            COUNT(*) AS total,
            SUM(user_ans='true')   AS cnt_true,
            SUM(user_ans='false')  AS cnt_false,
            SUM(user_ans='unsure') AS cnt_unsure,
            SUM(is_correct)        AS cnt_correct
        FROM quiz_answers qa
        JOIN quiz_results qr ON qr.id = qa.result_id
        JOIN users u ON u.id = qr.user_id
        WHERE qa.question_id=$qid AND qr.course_id=$cid AND qr.quiz_type='post' AND u.role='user'
    ")->fetch_assoc();

    $total = (int)($r['total'] ?? 0);
    $qStats[] = [
        'id'          => $qid,
        'no'          => $q['sort_order'],
        'question'    => $q['question'],
        'correct_ans' => $q['correct_ans'],
        'total'       => $total,
        'pct_correct' => $total > 0 ? round($r['cnt_correct'] / $total * 100) : 0,
        'pct_true'    => $total > 0 ? round($r['cnt_true']    / $total * 100) : 0,
        'pct_false'   => $total > 0 ? round($r['cnt_false']   / $total * 100) : 0,
        'pct_unsure'  => $total > 0 ? round($r['cnt_unsure']  / $total * 100) : 0,
        'cnt_true'    => (int)$r['cnt_true'],
        'cnt_false'   => (int)$r['cnt_false'],
        'cnt_unsure'  => (int)$r['cnt_unsure'],
    ];
}

// ข้อที่ทำผิดมากสุด
$hardest = $qStats;
usort($hardest, fn($a,$b) => $a['pct_correct'] - $b['pct_correct']);

// ===== Top 10 คะแนนสูงสุด =====
$top10 = $conn->query("
    SELECT u.name, u.position, u.department,
           MAX(qr.score/qr.total*100) AS best_pct,
           MAX(qr.score) AS best_score,
           MAX(qr.total) AS total
    FROM quiz_results qr
    JOIN users u ON u.id = qr.user_id
    WHERE qr.course_id=$cid AND qr.quiz_type='post' AND u.role='user'
    GROUP BY qr.user_id, u.name, u.position, u.department
    ORDER BY best_pct DESC, best_score DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// คำตอบของ user ในการทำครั้งนี้
$myAnswers = $conn->query("
    SELECT qa.question_id, qa.user_ans, qa.is_correct
    FROM quiz_answers qa
    WHERE qa.result_id = $rid
")->fetch_all(MYSQLI_ASSOC);
$myAnsMap = [];
foreach ($myAnswers as $a) $myAnsMap[$a['question_id']] = $a;

$pageTitle = 'ผลการทดสอบ';
require_once 'includes/header.php';
?>

<div style="font-size:13px;color:#888;margin-bottom:16px;">
  <a href="index.php" style="color:#2e7d32;">หน้าหลัก</a> ›
  <span><?= htmlspecialchars($course['title']) ?></span> › ผลการทดสอบ
</div>

<!-- ===== คะแนนของคุณ ===== -->
<div class="card score-box" style="text-align:center;padding:28px;">
  <p style="font-size:15px;color:#666;margin-bottom:8px;">📝 Post-test — <?= htmlspecialchars($course['title']) ?></p>
  <div class="score-big <?= $passed ? 'score-pass' : 'score-fail' ?>" style="font-size:56px;font-weight:800;">
    <?= $pct ?>%
  </div>
  <p style="color:#666;margin:8px 0;"><?= $result['score'] ?>/<?= $result['total'] ?> ข้อ | เกณฑ์ผ่าน <?= $course['pass_score'] ?>%</p>
  <?php if ($passed): ?>
    <div class="alert alert-success" style="display:inline-block;margin:12px 0;">🎉 ผ่านเกณฑ์แล้ว!</div><br>
    <a href="reward.php?course_id=<?=$cid?>" class="btn btn-success" style="font-size:15px;padding:12px 28px;">🎁 รับสิทธิ์รางวัล</a>
  <?php else: ?>
    <div class="alert alert-warning" style="display:inline-block;margin:12px 0;">ยังไม่ถึงเกณฑ์ — ลองทบทวนเนื้อหาแล้วแก้ตัวใหม่</div><br>
    <a href="lessons.php?course_id=<?=$cid?>" class="btn btn-outline" style="margin-right:8px;">📚 ทบทวน</a>
    <a href="quiz.php?course_id=<?=$cid?>&type=post" class="btn btn-primary">🔄 แก้ตัว</a>
  <?php endif; ?>
</div>

<!-- ===== สถิติรวม + Top 10 ===== -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

  <!-- สถิติรวม -->
  <div class="card">
    <h3 style="font-size:14px;font-weight:700;color:#2e7d32;margin-bottom:14px;">📊 สถิติรวมทุกคน</h3>
    <?php
    $statItems = [
      ['คะแนนเฉลี่ย', round((float)$stats['avg_pct'],1).'%', '#2e7d32'],
      ['สูงสุด',       round((float)$stats['max_pct']).'%',   '#1565c0'],
      ['ต่ำสุด',       round((float)$stats['min_pct']).'%',   '#c62828'],
      ['ผู้เข้าสอบ',   $stats['total_attempts'].' ครั้ง',    '#6a1b9a'],
      ['ผ่านเกณฑ์',   $stats['total_passed'].' คน',          '#00695c'],
    ];
    foreach ($statItems as [$lbl,$val,$col]):?>
    <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #e8f5e9;font-size:13px;">
      <span style="color:#555;"><?=$lbl?></span>
      <strong style="color:<?=$col?>"><?=$val?></strong>
    </div>
    <?php endforeach;?>

    <!-- คะแนนของคุณ vs เฉลี่ย -->
    <?php if ($stats['avg_pct']): ?>
    <div style="margin-top:14px;padding:12px;background:#f9fdf9;border-radius:8px;text-align:center;">
      <div style="font-size:12px;color:#888;margin-bottom:6px;">คะแนนของคุณ vs ค่าเฉลี่ย</div>
      <div style="display:flex;justify-content:center;gap:24px;">
        <div>
          <div style="font-size:24px;font-weight:700;color:<?=$pct>=(float)$stats['avg_pct']?'#2e7d32':'#c62828'?>"><?=$pct?>%</div>
          <div style="font-size:11px;color:#888;">คุณ</div>
        </div>
        <div style="font-size:20px;color:#ddd;line-height:2;">vs</div>
        <div>
          <div style="font-size:24px;font-weight:700;color:#666;"><?=round((float)$stats['avg_pct'],1)?>%</div>
          <div style="font-size:11px;color:#888;">เฉลี่ย</div>
        </div>
      </div>
      <?php if ($pct >= (float)$stats['avg_pct']): ?>
      <div style="font-size:12px;color:#2e7d32;margin-top:6px;">⬆ คุณทำได้ดีกว่าค่าเฉลี่ย!</div>
      <?php else: ?>
      <div style="font-size:12px;color:#c62828;margin-top:6px;">⬇ ต่ำกว่าค่าเฉลี่ย ลองทบทวนดูนะครับ</div>
      <?php endif;?>
    </div>
    <?php endif;?>
  </div>

  <!-- Top 10 -->
  <div class="card">
    <h3 style="font-size:14px;font-weight:700;color:#2e7d32;margin-bottom:14px;">🏆 Top 10 คะแนนสูงสุด</h3>
    <?php if ($top10): ?>
    <?php foreach ($top10 as $i=>$t):
      $isMe = ($t['name']===$_SESSION['name']);
      $tPct = round((float)$t['best_pct']);
    ?>
    <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #e8f5e9;<?=$isMe?'background:#f1f8e9;border-radius:6px;padding:7px 8px;':''?>">
      <span style="width:24px;height:24px;border-radius:50%;
        background:<?=$i===0?'#f9a825':($i===1?'#9e9e9e':($i===2?'#bf8650':'#e8f5e9'))?>;
        color:<?=$i<3?'#fff':'#2e7d32'?>;
        display:flex;align-items:center;justify-content:center;
        font-size:11px;font-weight:700;flex-shrink:0;">
        <?=$i+1?>
      </span>
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:<?=$isMe?'700':'500'?>;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
          <?=$isMe?'⭐ ':''?><?=htmlspecialchars(mb_strimwidth($t['name'],0,16,'...'))?>
        </div>
        <div style="font-size:11px;color:#888;"><?=htmlspecialchars(mb_strimwidth($t['department']??'',0,20,'...'))?></div>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:16px;font-weight:700;color:<?=$tPct>=$course['pass_score']?'#2e7d32':'#c62828'?>"><?=$tPct?>%</div>
      </div>
    </div>
    <?php endforeach;?>
    <?php else:?>
    <p style="color:#aaa;font-size:13px;text-align:center;padding:20px;">ยังไม่มีข้อมูล</p>
    <?php endif;?>
  </div>
</div>

<!-- ===== วิเคราะห์รายข้อ ===== -->
<div class="card" style="margin-bottom:16px;">
  <h3 style="font-size:14px;font-weight:700;color:#2e7d32;margin-bottom:4px;">🎯 วิเคราะห์รายข้อ</h3>
  <p style="font-size:12px;color:#888;margin-bottom:16px;">แสดงเปอร์เซ็นต์คำตอบของผู้เข้าสอบทุกคน</p>

  <?php foreach ($qStats as $qs):
    $pc  = $qs['pct_correct'];
    $bc  = $pc >= 80 ? '#2e7d32' : ($pc >= 50 ? '#f57f17' : '#c62828');
    $bg  = $pc >= 80 ? '#e8f5e9' : ($pc >= 50 ? '#fff8e1' : '#ffebee');
    $myA = $myAnsMap[$qs['id']] ?? null;
  ?>
  <div style="margin-bottom:14px;padding:14px;background:<?=$bg?>;border-radius:10px;border-left:4px solid <?=$bc?>;">
    <div style="display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
      <span style="background:<?=$bc?>;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0;"><?=$qs['no']?></span>
      <div style="flex:1;min-width:0;">
        <div style="font-size:13px;font-weight:600;color:#333;"><?=htmlspecialchars($qs['question'])?></div>
        <div style="font-size:11px;color:#666;margin-top:3px;">
          เฉลย: <strong style="color:<?=$qs['correct_ans']==='true'?'#2e7d32':'#c62828'?>"><?=$qs['correct_ans']==='true'?'✅ ถูก':'❌ ผิด'?></strong>
          <?php if ($myA): ?>
          &nbsp;|&nbsp; คำตอบคุณ:
          <strong style="color:<?=$myA['is_correct']?'#2e7d32':'#c62828'?>">
            <?=['true'=>'✅ ถูก','false'=>'❌ ผิด','unsure'=>'🤔 ไม่แน่ใจ'][$myA['user_ans']]?>
          </strong>
          <?php endif;?>
          &nbsp;|&nbsp; ตอบถูก <?=$pc?>% (<?=$qs['total']?> ครั้ง)
        </div>
      </div>
      <div style="text-align:right;flex-shrink:0;">
        <div style="font-size:22px;font-weight:700;color:<?=$bc?>"><?=$pc?>%</div>
        <div style="font-size:10px;color:#888;">ตอบถูก</div>
      </div>
    </div>

    <?php if ($qs['total'] > 0): ?>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;">
      <?php foreach([
        ['✅ ถูก',       $qs['cnt_true'],   $qs['pct_true'],   $qs['correct_ans']==='true'?'#2e7d32':'#888'],
        ['❌ ผิด',       $qs['cnt_false'],  $qs['pct_false'],  $qs['correct_ans']==='false'?'#2e7d32':'#888'],
        ['🤔 ไม่แน่ใจ', $qs['cnt_unsure'], $qs['pct_unsure'], '#888'],
      ] as [$lbl,$cnt,$pct2,$c]):?>
      <div style="background:#fff;border-radius:6px;padding:8px 10px;border:1px solid #ddd;">
        <div style="font-size:11px;color:#666;margin-bottom:3px;"><?=$lbl?></div>
        <div style="background:#f0f0f0;border-radius:20px;height:7px;overflow:hidden;margin-bottom:3px;">
          <div style="width:<?=$pct2?>%;background:<?=$c?>;height:100%;border-radius:20px;"></div>
        </div>
        <div style="font-size:12px;font-weight:700;color:<?=$c?>"><?=$pct2?>% <span style="font-size:10px;font-weight:400;color:#aaa;">(<?=$cnt?>)</span></div>
      </div>
      <?php endforeach;?>
    </div>
    <?php endif;?>
  </div>
  <?php endforeach;?>

  <!-- ข้อที่ผิดมากสุด -->
  <?php if (count($hardest) > 0 && ($hardest[0]['total'] ?? 0) > 0): ?>
  <div style="padding:12px 14px;background:#fff3e0;border-radius:8px;border-left:4px solid #f57f17;">
    <div style="font-size:13px;font-weight:700;color:#e65100;margin-bottom:8px;">⚠️ ข้อที่คนตอบถูกน้อยสุด (ควรทบทวน)</div>
    <?php foreach (array_slice($hardest,0,3) as $i=>$hq): if(!$hq['total']) continue; ?>
    <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #ffe0b2;font-size:13px;">
      <span style="background:#f57f17;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;"><?=$i+1?></span>
      <span style="flex:1;">ข้อ <?=$hq['no']?>: <?=htmlspecialchars(mb_strimwidth($hq['question'],0,55,'...'))?></span>
      <span style="font-weight:700;color:#c62828;"><?=$hq['pct_correct']?>% ถูก</span>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>

<div style="text-align:center;margin-bottom:24px;">
  <a href="index.php" class="btn btn-outline">← กลับหน้าหลัก</a>
</div>

<style>
@media(max-width:720px){
  div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns:1fr !important; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
