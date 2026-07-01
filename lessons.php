<?php
require_once 'includes/config.php';
requireLogin();

$cid = (int)($_GET['course_id'] ?? 0);
if (!$cid) redirect('/index.php');

$uid    = $_SESSION['user_id'];
$course = $conn->query("SELECT * FROM courses WHERE id=$cid AND is_active=1")->fetch_assoc();
if (!$course) redirect('/index.php');

$pre = $conn->query("SELECT id FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='pre' LIMIT 1")->fetch_assoc();
if (!$pre) redirect("/course.php?id=$cid");

$lessons = $conn->query("SELECT * FROM lessons WHERE course_id=$cid AND is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$lid     = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$current = null;
if ($lid) foreach ($lessons as $l) { if ($l['id']==$lid) { $current=$l; break; } }
if (!$current && $lessons) $current = $lessons[0];

$postPass = $conn->query("SELECT id FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='post' AND passed=1 LIMIT 1")->fetch_assoc();
$postCnt  = (int)$conn->query("SELECT COUNT(*) c FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='post'")->fetch_assoc()['c'];
$attLeft  = POST_MAX_ATTEMPTS - $postCnt;

// ===== ฟังก์ชัน extract YouTube ID สะอาด =====
function extractYouTubeId($val) {
    $val = trim($val);
    // ลิงก์เต็ม
    if (preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{11})/', $val, $m)) {
        return trim($m[1]);
    }
    // ID ตรงๆ
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $val)) {
        return trim($val);
    }
    return trim($val); // fallback
}

$pageTitle = $course['title'];
require_once 'includes/header.php';
?>

<div style="font-size:13px;color:#888;margin-bottom:16px;">
  <a href="index.php" style="color:#2e7d32;">หน้าหลัก</a> ›
  <span><?= htmlspecialchars($course['title']) ?></span>
</div>

<div class="lesson-layout">
  <!-- Sidebar -->
  <div class="lesson-sidebar">
    <div class="card" style="padding:16px;">
      <div style="font-size:20px;margin-bottom:4px;"><?= $course['thumbnail'] ?></div>
      <h3 style="font-size:14px;color:#2e7d32;margin-bottom:12px;line-height:1.4;"><?= htmlspecialchars($course['title']) ?></h3>
      <ul class="lesson-list" style="margin:0;">
        <?php foreach ($lessons as $l):
          $icon = match($l['type']) { 'youtube'=>'▶️', 'pdf'=>'📄', 'info'=>'📝', default=>'📌' };
          $isActive = ($current && $current['id']==$l['id']);
        ?>
        <li class="lesson-item" style="padding:8px 4px;">
          <span class="lesson-badge" style="font-size:16px;"><?=$icon?></span>
          <a href="lessons.php?course_id=<?=$cid?>&id=<?=$l['id']?>"
            style="font-size:13px;<?=$isActive?'font-weight:700;color:#1b5e20;':''?>text-decoration:none;color:inherit;">
            <?= htmlspecialchars($l['title']) ?>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>

    <div class="card" style="padding:16px;text-align:center;">
      <?php if ($postPass): ?>
        <p style="font-size:13px;color:#2e7d32;font-weight:600;margin-bottom:8px;">✅ ผ่าน Post-test แล้ว!</p>
        <a href="reward.php?course_id=<?=$cid?>" class="btn btn-success" style="width:100%;font-size:13px;">🎁 รับรางวัล</a>
      <?php elseif ($attLeft > 0): ?>
        <p style="font-size:12px;color:#666;margin-bottom:8px;">ศึกษาครบแล้ว?<br>เหลือสิทธิ์ <?=$attLeft?> ครั้ง</p>
        <a href="quiz.php?course_id=<?=$cid?>&type=post" class="btn btn-primary" style="width:100%;font-size:13px;">✏️ ทำ Post-test</a>
      <?php else: ?>
        <p style="font-size:12px;color:#c62828;">ใช้สิทธิ์ Post-test ครบแล้ว</p>
      <?php endif; ?>
    </div>

    <!-- ประกาศในห้อง -->
    <?php
    $roomAnn = $conn->query("SELECT * FROM announcements WHERE (course_id=$cid OR course_id IS NULL) AND is_active=1 ORDER BY is_pinned DESC, created_at DESC LIMIT 3")->fetch_all(MYSQLI_ASSOC);
    if ($roomAnn): ?>
    <div class="card" style="padding:14px;margin-top:0;">
      <h4 style="font-size:13px;color:#2e7d32;margin-bottom:10px;">📢 ประกาศ</h4>
      <?php foreach($roomAnn as $an): ?>
      <div style="padding:8px 0;border-bottom:1px solid #e8f5e9;">
        <?php if($an['is_pinned']): ?><span style="font-size:10px;color:#2e7d32;font-weight:700;">📌 </span><?php endif; ?>
        <div style="font-size:12px;font-weight:600;color:#333;"><?= htmlspecialchars($an['title']) ?></div>
        <div style="font-size:11px;color:#777;line-height:1.5;margin-top:2px;"><?= htmlspecialchars(mb_strimwidth($an['body'],0,60,'...')) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <a href="index.php" style="display:block;text-align:center;padding:10px;color:#888;font-size:13px;margin-top:8px;">← กลับหน้าหลัก</a>
  </div>

  <!-- Content -->
  <div class="lesson-content">
    <?php if ($current): ?>
    <div class="card">
      <h2><?= htmlspecialchars($current['title']) ?></h2>

      <?php if ($current['type']==='youtube'):
        $ytId = extractYouTubeId($current['content_url']); ?>
        <?php if ($ytId): ?>
        <div class="video-wrap">
          <iframe src="https://www.youtube.com/embed/<?= htmlspecialchars($ytId) ?>?rel=0"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen></iframe>
        </div>
        <?php else: ?>
        <div class="alert alert-warning">⚠️ ไม่พบ YouTube Video ID กรุณาติดต่อ Admin</div>
        <?php endif; ?>

      <?php elseif ($current['type']==='pdf'): ?>
        <div style="margin-bottom:12px;">
          <a href="/<?= htmlspecialchars(trim($current['content_url'])) ?>" target="_blank" class="btn btn-outline">📥 เปิด PDF</a>
        </div>
        <iframe src="/<?= htmlspecialchars(trim($current['content_url'])) ?>"
          style="width:100%;height:520px;border:1px solid #c8e6c9;border-radius:6px;"></iframe>

      <?php else: ?>
        <div style="line-height:1.9;"><?= $current['content_text'] ?? '<p style="color:#888">ไม่มีเนื้อหา</p>' ?></div>
      <?php endif; ?>
    </div>

    <?php
      $ids = array_column($lessons,'id');
      $pos = array_search($current['id'], $ids);
      $prevId = $pos > 0 ? $ids[$pos-1] : null;
      $nextId = $pos < count($ids)-1 ? $ids[$pos+1] : null;
    ?>
    <div style="display:flex;justify-content:space-between;margin-top:4px;">
      <?php if ($prevId): ?>
        <a href="lessons.php?course_id=<?=$cid?>&id=<?=$prevId?>" class="btn btn-outline">← บทก่อน</a>
      <?php else: ?><span></span><?php endif; ?>
      <?php if ($nextId): ?>
        <a href="lessons.php?course_id=<?=$cid?>&id=<?=$nextId?>" class="btn btn-primary">บทถัดไป →</a>
      <?php else: ?>
        <a href="quiz.php?course_id=<?=$cid?>&type=post" class="btn btn-success">✏️ ไปทำ Post-test</a>
      <?php endif; ?>
    </div>
    <?php else: ?>
      <div class="card"><p style="color:#888;">ยังไม่มีบทเรียน</p></div>
    <?php endif; ?>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
