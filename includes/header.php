<?php if(!isset($pageTitle))$pageTitle='E-Learning'; ?>
<!DOCTYPE html><html lang="th"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=htmlspecialchars($pageTitle)?> | E-Learning</title>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
</head><body>
<nav class="navbar"><div class="navbar-inner">
  <div class="navbar-brand">
    <a class="brand" href="/index.php">🌿 ระบบ E-Learning</a>
    <div class="brand-sub">กลุ่มงานโรคเอดส์และโรคติดต่อทางเพศสัมพันธ์<br>สำนักงานโรคติดต่อทางสาธารณสุข สำนักอนามัย กรุงเทพมหานคร</div>
  </div>
  <button class="nav-toggle" id="navToggle">☰</button>
  <div class="nav-links" id="navLinks">
    <?php if(!empty($_SESSION['user_id'])): ?>
      <a href="/index.php">🏠 หน้าหลัก</a>
      <?php if($_SESSION['role']==='admin'): ?><a href="/admin/index.php">🛠 หลังบ้าน Admin</a><?php endif; ?>
      <span class="nav-user">👤 <?=htmlspecialchars($_SESSION['name'])?></span>
      <a href="/logout.php" class="btn-sm">ออกจากระบบ</a>
    <?php else: ?>
      <a href="/login.php" class="btn-sm">เข้าสู่ระบบ</a>
      <a href="/admin_login.php" class="btn-sm" style="background:#37474f;">🔐 แอดมินเข้าสู่ระบบ</a>
    <?php endif; ?>
  </div>
</div></nav>
<main class="container">
<script>document.getElementById('navToggle').addEventListener('click',function(){document.getElementById('navLinks').classList.toggle('open');});</script>
