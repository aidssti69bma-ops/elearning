<?php
require_once '../includes/config.php';
requireAdmin();

$courses = $conn->query("SELECT id,title,thumbnail FROM courses WHERE is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$cid     = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? ($courses[0]['id'] ?? 0));
$course  = $cid ? $conn->query("SELECT * FROM courses WHERE id=$cid")->fetch_assoc() : null;

// ดึงข้อสอบ: shared ก่อน ถ้าไม่มีค่อย fallback pre+post แบบเก่า
$questions = [];
if ($cid) {
    $questions = $conn->query("
        SELECT * FROM questions
        WHERE course_id=$cid AND quiz_type='shared' AND is_active=1
        ORDER BY sort_order
    ")->fetch_all(MYSQLI_ASSOC);

    // fallback: ข้อสอบแบบเก่า (pre หรือ post)
    if (!$questions) {
        $questions = $conn->query("
            SELECT * FROM questions
            WHERE course_id=$cid AND quiz_type IN ('pre','post') AND is_active=1
            ORDER BY quiz_type, sort_order
        ")->fetch_all(MYSQLI_ASSOC);
    }
}

$resultMsg = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['answers'])) {
    $answers = $_POST['answers'];
    $score = 0; $total = count($questions); $rows = [];
    foreach ($questions as $q) {
        $ua = in_array($answers[$q['id']]??'', ['true','false','unsure'])
              ? $answers[$q['id']] : 'unsure';
        $ok = ($ua === $q['correct_ans']) ? 1 : 0;
        if ($ok) $score++;
        $rows[] = ['q'=>$q['question'], 'ua'=>$ua, 'ca'=>$q['correct_ans'], 'ok'=>$ok];
    }
    $pct = $total > 0 ? round($score/$total*100) : 0;
    // *** ไม่บันทึกลง DB เลย ***
    $resultMsg = compact('score','total','pct','rows');
}

$pageTitle = 'ทดลองทำข้อสอบ (Admin)';
require_once '../includes/header.php';
?>

<div class="admin-nav">
  <a href="index.php">📊 หน้าหลัก</a>
  <a href="dashboard.php">📈 สรุปผล</a>
  <a href="courses.php">🏫 หลักสูตร</a>
  <a href="lessons.php">📚 บทเรียน</a>
  <a href="questions.php">📝 ข้อสอบ</a>
  <a href="quiz_admin.php" class="active">🧪 ทดลองทำ</a>
  <a href="announcements.php">📢 ประกาศ</a>
  <a href="users.php">👥 ผู้ใช้</a>
  <a href="rewards.php">🏆 รางวัล</a>
  <a href="reset.php" style="background:#ffebee;color:#c62828;border-color:#ef9a9a;">🗑 ล้างข้อมูล</a>
  <a href="profile.php" style="margin-left:auto;background:#e3f2fd;color:#0d47a1;border-color:#90caf9;">🔑 รหัสผ่าน</a>
</div>

<div class="alert alert-info" style="margin-bottom:16px;">
  🧪 <strong>Admin โหมดทดสอบ</strong> — ไม่บันทึกลง DB ไม่นับสถิติ เห็นเฉลยหลังส่ง
</div>

<!-- เลือก course -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
  <?php foreach ($courses as $c): ?>
  <a href="quiz_admin.php?course_id=<?=$c['id']?>"
     class="btn <?=$cid==$c['id']?'btn-primary':'btn-outline'?>" style="font-size:13px;">
    <?=$c['thumbnail']?> <?=htmlspecialchars(mb_strimwidth($c['title'],0,24,'...'))?>
  </a>
  <?php endforeach; ?>
</div>

<?php if ($cid && $course): ?>

<!-- จำนวนข้อสอบ -->
<div class="card" style="padding:14px 18px;margin-bottom:16px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
  <div style="font-size:24px;"><?=$course['thumbnail']?></div>
  <div>
    <div style="font-size:15px;font-weight:700;color:#1b5e20;"><?=htmlspecialchars($course['title'])?></div>
    <div style="font-size:13px;color:#666;">ข้อสอบ <?=count($questions)?> ข้อ | เกณฑ์ผ่าน <?=$course['pass_score']?>%</div>
  </div>
</div>

<?php if ($resultMsg): ?>
<!-- ผลคะแนน + เฉลย -->
<div class="card score-box" style="margin-bottom:20px;">
  <p style="font-size:16px;margin-bottom:6px;">📝 ผลทดสอบ — <?=htmlspecialchars($course['title'])?></p>
  <div class="score-big <?=$resultMsg['pct']>=$course['pass_score']?'score-pass':'score-fail'?>">
    <?=$resultMsg['pct']?>%
  </div>
  <p style="color:#666;margin:6px 0;"><?=$resultMsg['score']?>/<?=$resultMsg['total']?> ข้อ
    | เกณฑ์ผ่าน <?=$course['pass_score']?>%
    <span style="background:#e3f2fd;color:#1565c0;padding:2px 10px;border-radius:20px;font-size:12px;margin-left:8px;">ไม่บันทึกผล</span>
  </p>
</div>

<!-- เฉลยรายข้อ -->
<div class="card" style="margin-bottom:16px;">
  <h2 style="font-size:15px;margin-bottom:16px;">📖 เฉลยรายข้อ</h2>
  <?php foreach ($resultMsg['rows'] as $i=>$row): ?>
  <div style="padding:14px 16px;margin-bottom:10px;border-radius:8px;
    background:<?=$row['ok']?'#e8f5e9':'#ffebee'?>;
    border-left:4px solid <?=$row['ok']?'#2e7d32':'#c62828'?>;">
    <p style="font-weight:600;margin-bottom:8px;font-size:14px;">
      <?=$row['ok']?'✅':'❌'?> ข้อ <?=$i+1?>: <?=htmlspecialchars($row['q'])?>
    </p>
    <div style="font-size:13px;display:flex;gap:20px;flex-wrap:wrap;">
      <span>คำตอบของคุณ:
        <strong style="color:<?=$row['ok']?'#1b5e20':'#b71c1c'?>">
          <?=['true'=>'✅ ถูก','false'=>'❌ ผิด','unsure'=>'🤔 ไม่แน่ใจ'][$row['ua']]?>
        </strong>
      </span>
      <?php if (!$row['ok']): ?>
      <span>เฉลย: <strong style="color:#1b5e20"><?=$row['ca']==='true'?'✅ ถูก':'❌ ผิด'?></strong></span>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:flex;gap:12px;flex-wrap:wrap;">
  <a href="quiz_admin.php?course_id=<?=$cid?>" class="btn btn-primary">🔄 ทำใหม่</a>
  <a href="dashboard.php?cid=<?=$cid?>" class="btn btn-outline">📈 ดูสรุปผล</a>
</div>

<?php elseif ($questions): ?>
<!-- ฟอร์มข้อสอบ -->
<div class="card">
  <h2 style="font-size:15px;margin-bottom:4px;">📝 ข้อสอบ — <?=htmlspecialchars($course['title'])?></h2>
  <p style="color:#666;font-size:13px;margin-bottom:20px;">
    ทั้งหมด <?=count($questions)?> ข้อ | ใช้ทั้ง Pre-test และ Post-test
  </p>
  <form method="post">
    <input type="hidden" name="course_id" value="<?=$cid?>">
    <?php foreach ($questions as $i=>$q): ?>
    <div class="question-block">
      <p><?=($i+1)?>. <?=htmlspecialchars($q['question'])?></p>
      <label class="option-label"><input type="radio" name="answers[<?=$q['id']?>]" value="true" required> ✅ ถูก</label>
      <label class="option-label"><input type="radio" name="answers[<?=$q['id']?>]" value="false"> ❌ ผิด</label>
      <label class="option-label"><input type="radio" name="answers[<?=$q['id']?>]" value="unsure"> 🤔 ไม่แน่ใจ</label>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary" onclick="return confirm('ส่งคำตอบและดูเฉลย?')">
      ส่งและดูเฉลย
    </button>
  </form>
</div>

<?php else: ?>
<div class="card">
  <div class="alert alert-warning">
    ยังไม่มีข้อสอบในหลักสูตรนี้
    ไปเพิ่มที่ <a href="course_editor.php?cid=<?=$cid?>">✏️ แก้ไขหลักสูตร</a>
    หรือ <a href="questions.php?course_id=<?=$cid?>">📝 จัดการข้อสอบ</a>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
