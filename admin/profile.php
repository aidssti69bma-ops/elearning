<?php
// admin/profile.php — เปลี่ยนรหัสผ่าน admin
require_once '../includes/config.php';
requireAdmin();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    // ดึง hash ปัจจุบัน
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!password_verify($current, $row['password'])) {
        $msg = '<div class="alert alert-danger">❌ รหัสผ่านปัจจุบันไม่ถูกต้อง</div>';
    } elseif (strlen($new) < 6) {
        $msg = '<div class="alert alert-danger">❌ รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร</div>';
    } elseif ($new !== $confirm) {
        $msg = '<div class="alert alert-danger">❌ รหัสผ่านใหม่และยืนยันไม่ตรงกัน</div>';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt2 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt2->bind_param('si', $hash, $_SESSION['user_id']);
        $stmt2->execute();
        $msg = '<div class="alert alert-success">✅ เปลี่ยนรหัสผ่านสำเร็จแล้ว</div>';
    }
}

$pageTitle = 'เปลี่ยนรหัสผ่าน';
require_once '../includes/header.php';
require_once '../includes/admin_nav.php';
?>

<div class="card" style="max-width:440px;margin:0 auto;">
  <h2>🔑 เปลี่ยนรหัสผ่าน Admin</h2>
  <p style="color:#666;font-size:14px;margin-bottom:20px;">ผู้ใช้: <strong><?= htmlspecialchars($_SESSION['name']) ?></strong></p>
  <?= $msg ?>
  <form method="post">
    <div class="form-group">
      <label>รหัสผ่านปัจจุบัน</label>
      <input type="password" name="current_password" required>
    </div>
    <div class="form-group">
      <label>รหัสผ่านใหม่ (อย่างน้อย 6 ตัว)</label>
      <input type="password" name="new_password" required>
    </div>
    <div class="form-group">
      <label>ยืนยันรหัสผ่านใหม่</label>
      <input type="password" name="confirm_password" required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%">เปลี่ยนรหัสผ่าน</button>
  </form>
</div>

<?php require_once '../includes/footer.php'; ?>
