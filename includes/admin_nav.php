<?php
// includes/admin_nav.php
$_navCid  = (int)($_GET['cid'] ?? $_GET['course_id'] ?? 0);
$_navCourses = $conn->query("SELECT id,title,thumbnail FROM courses WHERE is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$_navPage = basename($_SERVER['PHP_SELF']);
?>
<style>
.an-wrap { margin-bottom:20px; }
.an-top {
  display:flex; gap:8px; flex-wrap:wrap; align-items:center;
  background:#fff; padding:12px 16px; border-radius:10px 10px 0 0;
  border-bottom:2px solid #c8e6c9; box-shadow:0 1px 4px rgba(0,0,0,.06);
}
.an-top a {
  padding:7px 14px; border-radius:8px; font-size:13px; font-weight:600;
  text-decoration:none; color:#2e7d32; background:#f0f7f0;
  border:1.5px solid #c8e6c9; transition:all .15s; white-space:nowrap;
}
.an-top a:hover  { background:#2e7d32; color:#fff; border-color:#2e7d32; }
.an-top a.active { background:#2e7d32; color:#fff; border-color:#2e7d32; }
.an-top a.danger { background:#ffebee; color:#c62828; border-color:#ef9a9a; }
.an-top a.muted  { background:#e3f2fd; color:#0d47a1; border-color:#90caf9; }
.an-sep { width:1px; height:28px; background:#ddd; flex-shrink:0; }

.an-courses {
  background:#f9fdf9; padding:8px 16px; border-radius:0 0 10px 10px;
  display:flex; gap:8px; flex-wrap:wrap; align-items:center;
  box-shadow:0 2px 6px rgba(0,0,0,.05); margin-bottom:4px;
}
.an-courses span { font-size:12px; color:#888; font-weight:600; white-space:nowrap; }
.an-courses a {
  padding:4px 12px; border-radius:20px; font-size:12px; text-decoration:none;
  border:1.5px solid #c8e6c9; color:#2e7d32; background:#fff; transition:all .15s;
  white-space:nowrap;
}
.an-courses a:hover  { background:#2e7d32; color:#fff; border-color:#2e7d32; }
.an-courses a.active { background:#2e7d32; color:#fff; border-color:#2e7d32; font-weight:700; }
</style>

<div class="an-wrap">
  <div class="an-top">
    <!-- หน้าหลัก -->
    <a href="/elearning/admin/index.php"
       class="<?=$_navPage==='index.php'?'active':''?>">🏫 หลักสูตร</a>

    <!-- ถ้าอยู่ใน course_manager ให้แสดง tab -->
    <?php if ($_navPage==='course_manager.php' && $_navCid): ?>
    <div class="an-sep"></div>
    <a href="/elearning/admin/course_manager.php?cid=<?=$_navCid?>&tab=lessons"
       class="<?=($_GET['tab']??'')==='lessons'?'active':''?>">📚 บทเรียน</a>
    <a href="/elearning/admin/course_manager.php?cid=<?=$_navCid?>&tab=quiz"
       class="<?=($_GET['tab']??'')==='quiz'?'active':''?>">📝 ข้อสอบ</a>
    <a href="/elearning/admin/course_manager.php?cid=<?=$_navCid?>&tab=dashboard"
       class="<?=($_GET['tab']??'')==='dashboard'?'active':''?>">📈 รายงาน</a>
    <div class="an-sep"></div>
    <?php endif; ?>

    <!-- เมนูทั่วไป -->
    <a href="/elearning/admin/users.php"
       class="<?=$_navPage==='users.php'?'active':''?>">👥 ผู้ใช้</a>
    <a href="/elearning/admin/rewards.php"
       class="<?=$_navPage==='rewards.php'?'active':''?>">🏆 รางวัล</a>
    <a href="/elearning/admin/announcements.php"
       class="<?=$_navPage==='announcements.php'?'active':''?>">📢 ประกาศ</a>
    <a href="/elearning/admin/quiz_admin.php"
       class="<?=$_navPage==='quiz_admin.php'?'active':''?>">🧪 ทดลองทำ</a>

    <!-- อันตราย + setting ชิดขวา -->
    <div class="an-sep"></div>
    <a href="/elearning/admin/reset.php"   class="danger <?=$_navPage==='reset.php'  ?'active':''?>">🗑 ล้างข้อมูล</a>
    <a href="/elearning/admin/profile.php" class="muted  <?=$_navPage==='profile.php'?'active':''?>" style="margin-left:auto;">🔑 รหัสผ่าน</a>
  </div>

  <!-- แถวหลักสูตร -->
  <?php if ($_navCourses): ?>
  <div class="an-courses">
    <span>📂 เลือกหลักสูตร:</span>
    <?php foreach ($_navCourses as $nc): ?>
    <a href="/elearning/admin/course_manager.php?cid=<?=$nc['id']?>&tab=lessons"
       class="<?=$_navCid==$nc['id']?'active':''?>">
      <?=$nc['thumbnail']?> <?=htmlspecialchars(mb_strimwidth($nc['title'],0,20,'...'))?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
