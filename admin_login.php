<?php
// admin_login.php — สำหรับ Admin เข้าด้วยอีเมล/รหัสผ่านเหมือนเดิม
require_once 'includes/config.php';
if (!empty($_SESSION['user_id']) && $_SESSION['role']==='admin') redirect('/admin/index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (!$email || !$password) {
        $error = 'กรุณากรอกอีเมลและรหัสผ่าน';
    } else {
        $stmt = $conn->prepare("SELECT id, name, password, role FROM users WHERE email = ? AND role='admin'");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name']    = $user['name'];
            $_SESSION['role']    = $user['role'];
            redirect('/admin/index.php');
        } else {
            $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
$pageTitle = 'เข้าสู่ระบบ Admin';
require_once 'includes/header.php';
?>
<div style="max-width:420px;margin:0 auto;">
  <div class="card">
    <h2 style="text-align:center;margin-bottom:20px;">🔐 เข้าสู่ระบบ Admin</h2>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="form-group">
        <label>อีเมล</label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          required autofocus placeholder="กรอกอีเมล Admin">
      </div>
      <div class="form-group">
        <label>รหัสผ่าน</label>
        <input type="password" name="password" required placeholder="กรอกรหัสผ่าน">
      </div>
      <button type="submit" class="btn btn-primary btn-block" style="margin-top:4px;">เข้าสู่ระบบ</button>
    </form>
    <p style="text-align:center;margin-top:16px;font-size:13px;">
      <a href="/login.php" style="color:#666;">← กลับหน้าเข้าเรียนของผู้ใช้ทั่วไป</a>
    </p>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
