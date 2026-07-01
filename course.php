<?php
require_once 'includes/config.php';
requireLogin();

$cid = (int)($_GET['id'] ?? 0);
if (!$cid) redirect('/index.php');

$course = $conn->query("SELECT * FROM courses WHERE id=$cid AND is_active=1")->fetch_assoc();
if (!$course) redirect('/index.php');

$uid = $_SESSION['user_id'];
$pre = $conn->query("SELECT id FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='pre' LIMIT 1")->fetch_assoc();
if ($pre) redirect("/lessons.php?course_id=$cid");

$lessonCnt = (int)$conn->query("SELECT COUNT(*) c FROM lessons WHERE course_id=$cid AND is_active=1")->fetch_assoc()['c'];
$preCnt    = (int)$conn->query("SELECT COUNT(*) c FROM questions WHERE course_id=$cid AND quiz_type='pre' AND is_active=1")->fetch_assoc()['c'];
$postCnt   = (int)$conn->query("SELECT COUNT(*) c FROM questions WHERE course_id=$cid AND quiz_type='post' AND is_active=1")->fetch_assoc()['c'];

// ประกาศของห้องนี้
$announcements = $conn->query("SELECT * FROM announcements WHERE (course_id=$cid OR course_id IS NULL) AND is_active=1 ORDER BY is_pinned DESC, created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

// จำนวนคนที่ผ่าน
$passedCnt = (int)$conn->query("SELECT COUNT(DISTINCT user_id) c FROM quiz_results WHERE course_id=$cid AND quiz_type='post' AND passed=1")->fetch_assoc()['c'];

$pageTitle = $course['title'];
require_once 'includes/header.php';
?>

<!-- Breadcrumb -->
<div class="breadcrumb">
  <a href="index.php">🏠 หน้าหลัก</a> <span>›</span> <span><?= htmlspecialchars($course['title']) ?></span>
</div>

<div class="room-layout">

  <!-- LEFT: ข้อมูลห้อง -->
  <div class="room-main">

    <!-- Hero ห้อง -->
    <div class="room-hero" style="background:linear-gradient(135deg,<?= ['#1b5e20','#1565c0','#4a148c','#bf360c','#004d40','#880e4f'][$cid%6] ?> 0%,<?= ['#2e7d32','#1976d2','#6a1b9a','#e64a19','#00695c','#ad1457'][$cid%6] ?> 100%);">
      <div class="room-hero-inner">
        <div style="font-size:52px;margin-bottom:10px;"><?= $course['thumbnail'] ?></div>
        <h1 class="room-title"><?= htmlspecialchars($course['title']) ?></h1>
        <?php if ($course['description']): ?>
          <p class="room-desc"><?= htmlspecialchars($course['description']) ?></p>
        <?php endif; ?>
        <div class="room-chips">
          <span class="chip">📚 <?= $lessonCnt ?> บทเรียน</span>
          <span class="chip">📋 Pre-test <?= $preCnt ?> ข้อ</span>
          <span class="chip">📝 Post-test <?= $postCnt ?> ข้อ</span>
          <span class="chip">🎯 ผ่าน <?= $course['pass_score'] ?>%</span>
          <span class="chip">✅ <?= $passedCnt ?> คนผ่านแล้ว</span>
        </div>
      </div>
    </div>

    <!-- ขั้นตอนการเรียน -->
    <div class="card" style="margin-bottom:16px;">
      <h2 style="font-size:15px;margin-bottom:16px;">📋 ขั้นตอนการเรียน</h2>
      <div class="steps-visual">
        <div class="step-v"><div class="step-v-num">1</div><div class="step-v-txt">ทำ Pre-test</div></div>
        <div class="step-v-arrow">→</div>
        <div class="step-v"><div class="step-v-num">2</div><div class="step-v-txt">ศึกษาบทเรียน</div></div>
        <div class="step-v-arrow">→</div>
        <div class="step-v"><div class="step-v-num">3</div><div class="step-v-txt">ทำ Post-test</div></div>
        <div class="step-v-arrow">→</div>
        <div class="step-v step-v-reward"><div class="step-v-num">🏆</div><div class="step-v-txt">รับรางวัล</div></div>
      </div>
      <p style="font-size:13px;color:#888;margin-top:12px;">แก้ตัว Post-test ได้ <?= POST_MAX_ATTEMPTS ?> ครั้ง | ไม่เฉลยคำตอบ</p>
    </div>

    <!-- ปุ่ม CTA -->
    <?php if ($preCnt > 0): ?>
      <a href="quiz.php?course_id=<?=$cid?>&type=pre" class="btn-start">📋 เริ่ม Pre-test เพื่อเข้าเรียน</a>
    <?php else: ?>
      <div class="alert alert-warning">ยังไม่มีข้อสอบ Pre-test กรุณาติดต่อ Admin</div>
    <?php endif; ?>
  </div>

  <!-- RIGHT: ประกาศห้อง -->
  <div class="room-side">
    <div class="card" style="padding:16px;">
      <h3 style="font-size:14px;color:#2e7d32;margin-bottom:12px;">📢 ประกาศ / ข่าวสาร</h3>
      <?php if ($announcements): ?>
        <?php foreach ($announcements as $a): ?>
        <div style="padding:10px 0;border-bottom:1px solid #e8f5e9;">
          <?php if ($a['is_pinned']): ?><span style="font-size:10px;color:#2e7d32;font-weight:700;">📌 ปักหมุด</span><br><?php endif; ?>
          <div style="font-size:13px;font-weight:600;color:#333;margin:2px 0;"><?= htmlspecialchars($a['title']) ?></div>
          <div style="font-size:12px;color:#666;line-height:1.5;"><?= htmlspecialchars($a['body']) ?></div>
          <div style="font-size:11px;color:#aaa;margin-top:4px;"><?= date('d/m/Y', strtotime($a['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="font-size:13px;color:#aaa;">ยังไม่มีประกาศ</p>
      <?php endif; ?>
    </div>

    <!-- ลิงก์กลับ -->
    <a href="index.php" style="display:block;text-align:center;padding:10px;color:#888;font-size:13px;margin-top:8px;">← กลับหน้าหลัก</a>
  </div>

</div>

<style>
.breadcrumb { font-size:13px; color:#888; margin-bottom:16px; }
.breadcrumb a { color:#2e7d32; text-decoration:none; }
.breadcrumb span { margin:0 6px; }

.room-layout { display:grid; grid-template-columns:1fr 280px; gap:20px; align-items:start; }
@media(max-width:720px){ .room-layout { grid-template-columns:1fr; } }

.room-hero { border-radius:14px; padding:32px 28px; margin-bottom:16px; color:#fff; position:relative; overflow:hidden; }
.room-hero::after { content:''; position:absolute; right:-40px; top:-40px; width:200px; height:200px; border-radius:50%; background:rgba(255,255,255,.07); }
.room-hero-inner { position:relative; z-index:1; }
.room-title { font-size:22px; font-weight:700; margin:0 0 8px; }
.room-desc  { font-size:13px; color:rgba(255,255,255,.8); margin:0 0 16px; line-height:1.6; }
.room-chips { display:flex; flex-wrap:wrap; gap:8px; }
.chip { background:rgba(255,255,255,.18); border:1px solid rgba(255,255,255,.25); border-radius:20px; padding:3px 12px; font-size:12px; }

.steps-visual { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
.step-v { text-align:center; min-width:70px; }
.step-v-num { width:36px; height:36px; border-radius:50%; background:#2e7d32; color:#fff; font-size:15px; font-weight:700; display:flex; align-items:center; justify-content:center; margin:0 auto 4px; }
.step-v-reward .step-v-num { background:#f9a825; }
.step-v-txt { font-size:11px; color:#555; }
.step-v-arrow { font-size:18px; color:#c8e6c9; }

.btn-start { display:block; text-align:center; background:linear-gradient(135deg,#2e7d32,#43a047); color:#fff; padding:14px; border-radius:10px; font-size:16px; font-weight:700; text-decoration:none; transition:opacity .2s; }
.btn-start:hover { opacity:.88; }
</style>

<?php require_once 'includes/footer.php'; ?>
