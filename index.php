<?php
require_once 'includes/config.php';
requireLogin();

$uid = $_SESSION['user_id'];
$courses = $conn->query("SELECT * FROM courses WHERE is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);

// สถานะแต่ละ course
$statusMap = [];
foreach ($courses as $c) {
    $cid = $c['id'];
    $pre      = $conn->query("SELECT id,score,total FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='pre' LIMIT 1")->fetch_assoc();
    $postPass = $conn->query("SELECT id,score,total FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='post' AND passed=1 LIMIT 1")->fetch_assoc();
    $postCnt  = (int)$conn->query("SELECT COUNT(*) c FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='post'")->fetch_assoc()['c'];
    $claimed  = $conn->query("SELECT id FROM reward_claims WHERE user_id=$uid AND course_id=$cid LIMIT 1")->fetch_assoc();
    $step = 0;
    if ($pre) $step = 1;
    if ($postPass) $step = 3;
    elseif ($postCnt > 0) $step = 2;
    $statusMap[$cid] = compact('pre','postPass','postCnt','claimed','step');
}

// ประกาศทั่วไป
$announcements = $conn->query("SELECT * FROM announcements WHERE course_id IS NULL AND is_active=1 ORDER BY is_pinned DESC, created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'หน้าหลัก';
require_once 'includes/header.php';
?>

<!-- ===== HERO BANNER ===== -->
<div class="hero-banner">
  <div class="hero-inner">
    <div class="hero-badge">🌿 E-Learning</div>
    <h1 class="hero-title">ระบบการเรียนรู้ออนไลน์</h1>
    <p class="hero-sub">กลุ่มงานโรคเอดส์และโรคติดต่อทางเพศสัมพันธ์<br>สำนักงานโรคติดต่อทางสาธารณสุข สำนักอนามัย กรุงเทพมหานคร</p>
    <div class="hero-stats">
      <div class="hero-stat">
        <span class="hero-stat-num"><?= count($courses) ?></span>
        <span class="hero-stat-lbl">หลักสูตร</span>
      </div>
      <div class="hero-stat-div"></div>
      <div class="hero-stat">
        <span class="hero-stat-num"><?= (int)$conn->query("SELECT COUNT(*) c FROM lessons WHERE is_active=1")->fetch_assoc()['c'] ?></span>
        <span class="hero-stat-lbl">บทเรียน</span>
      </div>
      <div class="hero-stat-div"></div>
      <div class="hero-stat">
        <span class="hero-stat-num"><?= (int)$conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'] ?></span>
        <span class="hero-stat-lbl">ผู้เรียน</span>
      </div>
    </div>
  </div>
  <div class="hero-deco">
    <div class="hero-circle c1"></div>
    <div class="hero-circle c2"></div>
    <div class="hero-circle c3"></div>
  </div>
</div>

<!-- ===== ANNOUNCEMENTS ===== -->
<?php if ($announcements): ?>
<div class="section-label">📢 ประกาศ / ข่าวสาร</div>
<div class="announce-list">
  <?php foreach ($announcements as $a): ?>
  <div class="announce-item <?= $a['is_pinned'] ? 'pinned' : '' ?>">
    <?php if ($a['is_pinned']): ?><span class="pin-badge">📌 ปักหมุด</span><?php endif; ?>
    <div class="announce-title"><?= htmlspecialchars($a['title']) ?></div>
    <div class="announce-body"><?= htmlspecialchars($a['body']) ?></div>
    <div class="announce-date"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ===== COURSES ===== -->
<div class="section-label">🏫 ห้องเรียนทั้งหมด</div>

<?php if (!$courses): ?>
  <div class="alert alert-info">ยังไม่มีหลักสูตร กรุณาติดต่อ Admin</div>
<?php endif; ?>

<div class="course-grid">
<?php foreach ($courses as $c):
  $cid  = $c['id'];
  $st   = $statusMap[$cid];
  $step = $st['step'];
  $prog = [0,25,60,90][$step] ?? 0;
  if ($st['claimed']) $prog = 100;

  if ($st['claimed'])      { $badge='🏆 รับรางวัลแล้ว'; $bc='badge-gold'; }
  elseif ($step===3)       { $badge='✅ ผ่านแล้ว';       $bc='badge-green'; }
  elseif ($step===2)       { $badge='📝 รอแก้ตัว';       $bc='badge-orange'; }
  elseif ($step===1)       { $badge='📚 กำลังเรียน';     $bc='badge-blue'; }
  else                     { $badge='⏳ ยังไม่เริ่ม';    $bc='badge-gray'; }

  // จำนวนบทเรียน
  $lCnt = (int)$conn->query("SELECT COUNT(*) c FROM lessons WHERE course_id=$cid AND is_active=1")->fetch_assoc()['c'];
  $qCnt = (int)$conn->query("SELECT COUNT(*) c FROM questions WHERE course_id=$cid AND is_active=1")->fetch_assoc()['c'];
?>
<div class="course-card">
  <!-- Card header color strip -->
  <div class="card-strip" style="background:<?= ['#2e7d32','#1565c0','#6a1b9a','#e65100','#00695c','#ad1457'][$cid % 6] ?>;">
    <span class="card-icon"><?= $c['thumbnail'] ?></span>
  </div>
  <div class="card-body">
    <span class="badge <?= $bc ?>"><?= $badge ?></span>
    <h3 class="card-title"><?= htmlspecialchars($c['title']) ?></h3>
    <?php if ($c['description']): ?>
      <p class="card-desc"><?= htmlspecialchars(mb_strimwidth($c['description'], 0, 80, '...')) ?></p>
    <?php endif; ?>

    <!-- Meta -->
    <div class="card-meta">
      <span>📚 <?= $lCnt ?> บทเรียน</span>
      <span>📝 <?= $qCnt ?> ข้อสอบ</span>
      <span>🎯 ผ่าน <?= $c['pass_score'] ?>%</span>
    </div>

    <!-- Progress -->
    <div class="prog-wrap">
      <div class="prog-bar">
        <div class="prog-fill" style="width:<?= $prog ?>%;background:<?= $prog>=100?'#f9a825':($prog>=60?'#2e7d32':'#66bb6a') ?>;"></div>
      </div>
      <span class="prog-pct"><?= $prog ?>%</span>
    </div>

    <!-- Button -->
    <div style="margin-top:14px;">
      <?php if ($st['claimed']): ?>
        <span class="btn-card btn-card-disabled">🏆 รับรางวัลเรียบร้อย</span>
      <?php elseif ($step===3): ?>
        <a href="reward.php?course_id=<?= $cid ?>" class="btn-card btn-card-gold">🎁 รับสิทธิ์รางวัล</a>
      <?php elseif ($step===2): ?>
        <div style="display:flex;gap:8px;">
          <a href="lessons.php?course_id=<?= $cid ?>" class="btn-card btn-card-outline" style="flex:1;">📚 ทบทวน</a>
          <a href="quiz.php?course_id=<?= $cid ?>&type=post" class="btn-card btn-card-primary" style="flex:1;">🔄 แก้ตัว</a>
        </div>
      <?php elseif ($step===1): ?>
        <a href="lessons.php?course_id=<?= $cid ?>" class="btn-card btn-card-primary">📚 เรียนต่อ →</a>
      <?php else: ?>
        <a href="course.php?id=<?= $cid ?>" class="btn-card btn-card-primary">▶ เข้าห้องเรียน</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<style>
/* ===== HERO ===== */
.hero-banner {
  background: linear-gradient(135deg,#1b5e20 0%,#2e7d32 50%,#388e3c 100%);
  border-radius:16px; padding:40px 36px; margin-bottom:28px;
  position:relative; overflow:hidden; color:#fff;
}
.hero-inner { position:relative; z-index:2; }
.hero-badge {
  display:inline-block; background:rgba(255,255,255,.18);
  border:1px solid rgba(255,255,255,.3); border-radius:20px;
  padding:4px 14px; font-size:13px; margin-bottom:12px;
}
.hero-title { font-size:26px; font-weight:700; margin:0 0 6px; line-height:1.2; }
.hero-sub { font-size:13px; color:#c8e6c9; line-height:1.7; margin:0 0 24px; }
.hero-stats { display:flex; align-items:center; gap:20px; }
.hero-stat { text-align:center; }
.hero-stat-num { display:block; font-size:28px; font-weight:700; line-height:1; }
.hero-stat-lbl { font-size:12px; color:#a5d6a7; }
.hero-stat-div { width:1px; height:36px; background:rgba(255,255,255,.25); }
/* deco circles */
.hero-deco { position:absolute; top:0; right:0; bottom:0; width:300px; z-index:1; }
.hero-circle { position:absolute; border-radius:50%; border:1px solid rgba(255,255,255,.12); }
.c1 { width:220px; height:220px; right:-60px; top:-60px; }
.c2 { width:140px; height:140px; right:40px; top:40px; background:rgba(255,255,255,.05); }
.c3 { width:80px;  height:80px;  right:100px; bottom:20px; background:rgba(255,255,255,.07); }

/* ===== SECTION LABEL ===== */
.section-label {
  font-size:15px; font-weight:700; color:#2e7d32;
  margin:0 0 14px; padding-left:12px;
  border-left:4px solid #2e7d32;
}

/* ===== ANNOUNCEMENTS ===== */
.announce-list { display:flex; flex-direction:column; gap:10px; margin-bottom:28px; }
.announce-item {
  background:#fff; border-radius:10px; padding:14px 18px;
  border-left:4px solid #c8e6c9; box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.announce-item.pinned { border-left-color:#2e7d32; background:#f9fdf9; }
.pin-badge { font-size:11px; color:#2e7d32; font-weight:700; display:block; margin-bottom:4px; }
.announce-title { font-size:14px; font-weight:600; color:#1b5e20; margin-bottom:4px; }
.announce-body  { font-size:13px; color:#555; line-height:1.6; }
.announce-date  { font-size:11px; color:#aaa; margin-top:6px; }

/* ===== COURSE GRID ===== */
.course-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(270px,1fr));
  gap:20px; margin-bottom:32px;
}
.course-card {
  background:#fff; border-radius:14px;
  box-shadow:0 2px 12px rgba(0,0,0,.08);
  overflow:hidden; transition:transform .2s, box-shadow .2s;
  display:flex; flex-direction:column;
}
.course-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(0,0,0,.13); }
.card-strip {
  height:72px; display:flex; align-items:center;
  justify-content:center; position:relative;
}
.card-icon { font-size:36px; }
.card-body { padding:16px 18px; flex:1; display:flex; flex-direction:column; gap:8px; }

/* Badge */
.badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:11px; font-weight:700; }
.badge-gold   { background:#fff8e1; color:#e65100; }
.badge-green  { background:#e8f5e9; color:#1b5e20; }
.badge-orange { background:#fff3e0; color:#e65100; }
.badge-blue   { background:#e3f2fd; color:#1565c0; }
.badge-gray   { background:#f5f5f5; color:#757575; }

.card-title { font-size:15px; font-weight:700; color:#1b5e20; line-height:1.4; margin:0; }
.card-desc  { font-size:12px; color:#777; line-height:1.6; margin:0; }
.card-meta  { display:flex; gap:10px; flex-wrap:wrap; }
.card-meta span { font-size:11px; color:#888; background:#f5f5f5; padding:2px 8px; border-radius:20px; }

/* Progress */
.prog-wrap  { display:flex; align-items:center; gap:8px; }
.prog-bar   { flex:1; background:#e8f5e9; border-radius:20px; height:8px; overflow:hidden; }
.prog-fill  { height:100%; border-radius:20px; transition:width .4s; }
.prog-pct   { font-size:12px; font-weight:700; color:#2e7d32; min-width:30px; text-align:right; }

/* Buttons */
.btn-card {
  display:block; text-align:center; padding:10px;
  border-radius:8px; font-size:13px; font-weight:600;
  text-decoration:none; transition:opacity .15s;
}
.btn-card:hover { opacity:.85; }
.btn-card-primary  { background:#2e7d32; color:#fff; }
.btn-card-gold     { background:#f9a825; color:#fff; }
.btn-card-outline  { background:#fff; color:#2e7d32; border:1.5px solid #2e7d32; }
.btn-card-disabled { background:#f5f5f5; color:#aaa; cursor:default; }

@media(max-width:600px){
  .hero-banner { padding:24px 18px; }
  .hero-title  { font-size:20px; }
  .hero-deco   { display:none; }
  .hero-stats  { gap:12px; }
  .hero-stat-num { font-size:22px; }
}
</style>

<?php require_once 'includes/footer.php'; ?>
