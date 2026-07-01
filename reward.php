<?php
require_once 'includes/config.php';
requireLogin();

$cid    = (int)($_GET['course_id'] ?? 0);
if (!$cid) redirect('/elearning/index.php');
$uid    = $_SESSION['user_id'];
$course = $conn->query("SELECT * FROM courses WHERE id=$cid")->fetch_assoc();
if (!$course) redirect('/elearning/index.php');

$passed  = $conn->query("SELECT id FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='post' AND passed=1 LIMIT 1")->fetch_assoc();
if (!$passed) redirect('/elearning/index.php');

$claimed = $conn->query("SELECT id,claimed_at FROM reward_claims WHERE user_id=$uid AND course_id=$cid LIMIT 1")->fetch_assoc();

if (!$claimed && $_SERVER['REQUEST_METHOD']==='POST') {
    $rid = (int)$passed['id'];
    $stmt = $conn->prepare("INSERT IGNORE INTO reward_claims (user_id,course_id,result_id) VALUES (?,?,?)");
    $stmt->bind_param('iii', $uid, $cid, $rid);
    $stmt->execute();
    $claimed = $conn->query("SELECT id,claimed_at FROM reward_claims WHERE user_id=$uid AND course_id=$cid LIMIT 1")->fetch_assoc();
}

$pageTitle = 'รับรางวัล';
require_once 'includes/header.php';
?>

<div style="max-width:540px;margin:0 auto;">
  <div class="card" style="text-align:center;padding:32px 24px;">
    <div style="font-size:48px;margin-bottom:4px;"><?= $course['thumbnail'] ?></div>
    <p style="font-size:14px;color:#888;margin-bottom:16px;"><?= htmlspecialchars($course['title']) ?></p>

    <?php if ($claimed): ?>
      <div style="font-size:48px;">🏆</div>
      <h2 style="color:#2e7d32;margin:8px 0;">ลงทะเบียนเรียบร้อยแล้ว!</h2>
      <p style="color:#555;font-size:14px;">ลงทะเบียนเมื่อ <?= date('d/m/Y H:i', strtotime($claimed['claimed_at'])) ?></p>
      <div class="alert alert-info" style="text-align:left;margin-top:16px;">
        📅 พบกันใน <strong>VCT Day — 1 กรกฎาคม 2569</strong>
      </div>
      <a href="index.php" class="btn btn-outline" style="margin-top:12px;">กลับหน้าหลัก</a>
    <?php else: ?>
      <div style="font-size:48px;">🎁</div>
      <h2 style="margin:8px 0;">ยินดีด้วย! คุณผ่านเกณฑ์แล้ว</h2>
      <div class="alert alert-warning" style="text-align:left;margin:16px 0;">
        ⚠️ กดได้ <strong>1 ครั้ง</strong> เท่านั้น
      </div>
      <p><strong>ชื่อ:</strong> <?= htmlspecialchars($_SESSION['name']) ?></p>
      <form method="post" style="margin-top:16px;">
        <button type="submit" class="btn btn-success btn-block" style="font-size:16px;padding:12px;"
          onclick="return confirm('ยืนยันลงทะเบียนรับรางวัล?')">🎁 ยืนยันรับสิทธิ์รางวัล</button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
