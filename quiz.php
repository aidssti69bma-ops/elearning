<?php
require_once 'includes/config.php';
requireLogin();

if (!empty($_SESSION['role']) && $_SESSION['role']==='admin') redirect('/elearning/admin/quiz_admin.php');

$cid  = (int)($_GET['course_id'] ?? $_POST['course_id'] ?? 0);
$type = in_array($_GET['type']??$_POST['type']??'', ['pre','post']) ? ($_GET['type']??$_POST['type']) : 'pre';
if (!$cid) redirect('/elearning/index.php');

$uid    = $_SESSION['user_id'];
$course = $conn->query("SELECT * FROM courses WHERE id=$cid AND is_active=1")->fetch_assoc();
if (!$course) redirect('/elearning/index.php');

$passThreshold = $course['pass_score'];

// ตรวจสิทธิ์
if ($type==='pre') {
    $done = $conn->query("SELECT id FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='pre' LIMIT 1")->fetch_assoc();
    if ($done) redirect("/elearning/lessons.php?course_id=$cid");
} else {
    $pre = $conn->query("SELECT id FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='pre' LIMIT 1")->fetch_assoc();
    if (!$pre) redirect("/elearning/course.php?id=$cid");
    $postCount = (int)$conn->query("SELECT COUNT(*) c FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='post'")->fetch_assoc()['c'];
    $passed    = $conn->query("SELECT id FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='post' AND passed=1 LIMIT 1")->fetch_assoc();
    if ($passed || $postCount >= POST_MAX_ATTEMPTS) redirect("/elearning/lessons.php?course_id=$cid");
}

// ดึงข้อสอบ shared (ใช้ชุดเดียวกันทั้ง pre และ post)
$questions = $conn->query("
    SELECT * FROM questions 
    WHERE course_id=$cid AND quiz_type='shared' AND is_active=1 
    ORDER BY sort_order
")->fetch_all(MYSQLI_ASSOC);

// fallback: ถ้ายังมีข้อสอบแบบเก่า (pre/post แยก) ให้ดึงตาม type เดิม
if (!$questions) {
    $questions = $conn->query("
        SELECT * FROM questions 
        WHERE course_id=$cid AND quiz_type='$type' AND is_active=1 
        ORDER BY sort_order
    ")->fetch_all(MYSQLI_ASSOC);
}

if (!$questions) {
    $pageTitle='แบบทดสอบ'; require_once 'includes/header.php';
    echo '<div class="card"><div class="alert alert-warning">ยังไม่มีคำถามในระบบ กรุณาติดต่อ Admin</div></div>';
    require_once 'includes/footer.php'; exit;
}

$resultMsg = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['answers'])) {
    $answers = $_POST['answers'];
    $score = 0; $total = count($questions); $rows = [];
    foreach ($questions as $q) {
        $qid = $q['id'];
        $ua  = in_array($answers[$qid]??'',['true','false','unsure']) ? $answers[$qid] : 'unsure';
        $ok  = ($ua===$q['correct_ans']) ? 1 : 0;
        if ($ok) $score++;
        $rows[] = ['qid'=>$qid,'ans'=>$ua,'correct'=>$ok];
    }
    $pct    = $total>0 ? round($score/$total*100) : 0;
    $passed = $pct >= $passThreshold ? 1 : 0;
    $attempt = 1;
    if ($type==='post') $attempt = $postCount + 1;

    $stmt = $conn->prepare("INSERT INTO quiz_results (user_id,course_id,quiz_type,attempt,score,total,passed) VALUES (?,?,?,?,?,?,?)");
    $stmt->bind_param('iisiiii', $uid, $cid, $type, $attempt, $score, $total, $passed);
    $stmt->execute();
    $rid = $conn->insert_id;

    $stmt2 = $conn->prepare("INSERT INTO quiz_answers (result_id,question_id,user_ans,is_correct) VALUES (?,?,?,?)");
    foreach ($rows as $r) { $stmt2->bind_param('iisi',$rid,$r['qid'],$r['ans'],$r['correct']); $stmt2->execute(); }

    $resultMsg = compact('score','total','pct','passed','attempt','type','cid','passThreshold');
    if ($type==='post') $resultMsg['attLeft'] = POST_MAX_ATTEMPTS - ($postCount+1);
}

$pageTitle = ($type==='pre')?'Pre-test':'Post-test';
require_once 'includes/header.php';
?>

<div style="font-size:13px;color:#888;margin-bottom:16px;">
  <a href="index.php" style="color:#2e7d32;">หน้าหลัก</a> ›
  <a href="<?=$type==='post'?"lessons.php?course_id=$cid":"course.php?id=$cid"?>" style="color:#2e7d32;">
    <?= htmlspecialchars($course['title']) ?>
  </a> › <?= $type==='pre'?'Pre-test':'Post-test' ?>
</div>

<?php if ($resultMsg): ?>
<div class="card score-box">
  <p style="font-size:16px;margin-bottom:6px;"><?=$type==='pre'?'📋 Pre-test':'📝 Post-test'?> — <?= htmlspecialchars($course['title']) ?></p>
  <div class="score-big <?=$resultMsg['pct']>=$passThreshold?'score-pass':'score-fail'?>"><?=$resultMsg['pct']?>%</div>
  <p style="color:#666;margin:6px 0;"><?=$resultMsg['score']?>/<?=$resultMsg['total']?> ข้อ | เกณฑ์ผ่าน <?=$passThreshold?>%</p>
  <?php if ($type==='pre'): ?>
    <div class="alert alert-info" style="text-align:left;margin-top:16px;">บันทึกคะแนน Pre-test แล้ว — ไปศึกษาเนื้อหาได้เลย</div>
    <a href="lessons.php?course_id=<?=$cid?>" class="btn btn-primary">📚 ไปเรียนเนื้อหา</a>
  <?php else: ?>
    <?php if ($resultMsg['passed']): ?>
      <div class="alert alert-success" style="text-align:left;margin-top:16px;">🎉 ผ่านเกณฑ์ <?=$passThreshold?>%!</div>
      <a href="reward.php?course_id=<?=$cid?>" class="btn btn-success">🎁 รับรางวัล</a>
    <?php else: ?>
      <div class="alert alert-warning" style="text-align:left;margin-top:16px;">
        ยังไม่ถึงเกณฑ์<?= ($resultMsg['attLeft']??0)>0 ? ' — สามารถแก้ตัวได้อีก '.($resultMsg['attLeft']).' ครั้ง' : ' — หมดสิทธิ์แก้ตัวแล้ว' ?>
      </div>
      <?php if (($resultMsg['attLeft']??0)>0): ?>
        <a href="lessons.php?course_id=<?=$cid?>" class="btn btn-outline" style="margin-right:8px;">📚 ทบทวน</a>
        <a href="quiz.php?course_id=<?=$cid?>&type=post" class="btn btn-primary">🔄 แก้ตัว</a>
      <?php else: ?>
        <a href="index.php" class="btn btn-outline">กลับหน้าหลัก</a>
      <?php endif; ?>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
  <h2><?=$type==='pre'?'📋 Pre-test':'📝 Post-test'?> — <?= htmlspecialchars($course['title']) ?></h2>
  <div class="alert alert-info" style="margin-bottom:20px;font-size:13px;">
    ทั้งหมด <?=count($questions)?> ข้อ &nbsp;|&nbsp;
    ✅ ถูก = 1 คะแนน &nbsp;|&nbsp; ❌ ผิด / 🤔 ไม่แน่ใจ = 0 &nbsp;|&nbsp;
    เกณฑ์ผ่าน <?=$passThreshold?>%
  </div>
  <form method="post">
    <input type="hidden" name="course_id" value="<?=$cid?>">
    <input type="hidden" name="type" value="<?=$type?>">
    <?php foreach ($questions as $i=>$q): ?>
    <div class="question-block">
      <p><?=($i+1)?>. <?=htmlspecialchars($q['question'])?></p>
      <label class="option-label"><input type="radio" name="answers[<?=$q['id']?>]" value="true" required> ✅ ถูก</label>
      <label class="option-label"><input type="radio" name="answers[<?=$q['id']?>]" value="false"> ❌ ผิด</label>
      <label class="option-label"><input type="radio" name="answers[<?=$q['id']?>]" value="unsure"> 🤔 ไม่แน่ใจ</label>
    </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary" onclick="return confirm('ยืนยันส่งคำตอบ?')">ส่งคำตอบ</button>
  </form>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
