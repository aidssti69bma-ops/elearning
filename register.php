<?php
require_once 'includes/config.php';
if (!empty($_SESSION['user_id'])) redirect('/elearning/index.php');

$error = ''; $success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']       ?? '');
    $position   = trim($_POST['position']   ?? '');
    $department = trim($_POST['department'] ?? '');
    $phone      = trim($_POST['phone']      ?? '');
    $email      = trim($_POST['email']      ?? '');
    $password   = $_POST['password']        ?? '';
    $confirm    = $_POST['confirm']         ?? '';

    if (!$name || !$position || !$department || !$phone || !$email || !$password || !$confirm) {
        $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'รูปแบบอีเมลไม่ถูกต้อง';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร';
    } elseif ($password !== $confirm) {
        $error = 'รหัสผ่านและยืนยันไม่ตรงกัน';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = 'อีเมลนี้ถูกใช้งานแล้ว';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt2 = $conn->prepare("INSERT INTO users (name, position, department, phone, email, password) VALUES (?,?,?,?,?,?)");
            $stmt2->bind_param('ssssss', $name, $position, $department, $phone, $email, $hash);
            $success = $stmt2->execute() ? 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ' : 'เกิดข้อผิดพลาด กรุณาลองใหม่';
        }
    }
}
$pageTitle = 'สมัครสมาชิก';
require_once 'includes/header.php';
?>
<div style="max-width:520px;margin:0 auto;">
  <div class="card">
    <h2 style="text-align:center;margin-bottom:4px;">📝 สมัครสมาชิก</h2>
    <p style="text-align:center;color:#666;font-size:13px;margin-bottom:20px;">ระบบ E-Learning กลุ่มงานโรคเอดส์และโรคติดต่อทางเพศสัมพันธ์</p>

    <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
      <a href="login.php" class="btn btn-primary btn-block">เข้าสู่ระบบ</a>
    <?php else: ?>
    <form method="post">

      <div class="form-section-label">ข้อมูลส่วนตัว</div>

      <div class="form-group">
        <label>ชื่อ-นามสกุล <span class="req">*</span></label>
        <input type="text" name="name" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
          required placeholder="เช่น นายสมชาย ใจดี">
      </div>

      <div class="form-group">
        <label>ตำแหน่ง <span class="req">*</span></label>
        <input type="text" name="position" value="<?= htmlspecialchars($_POST['position'] ?? '') ?>"
          required placeholder="เช่น นักวิชาการสาธารณสุข, พยาบาลวิชาชีพ">
      </div>

      <div class="form-group">
        <label>ชื่อกลุ่มงาน / หน่วยงาน <span class="req">*</span></label>
        <input type="text" name="department" value="<?= htmlspecialchars($_POST['department'] ?? '') ?>"
          required placeholder="เช่น กลุ่มงานโรคเอดส์ฯ, ฝ่ายบริหาร">
      </div>

      <div class="form-group">
        <label>เบอร์โทรศัพท์ <span class="req">*</span></label>
        <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
          required placeholder="เช่น 081-234-5678">
      </div>

      <div class="form-section-label" style="margin-top:8px;">ข้อมูลบัญชี</div>

      <div class="form-group">
        <label>อีเมล <span class="req">*</span></label>
        <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
          required placeholder="กรอกอีเมลสำหรับ login">
      </div>

      <div class="form-group">
        <label>รหัสผ่าน (อย่างน้อย 6 ตัว) <span class="req">*</span></label>
        <input type="password" name="password" required placeholder="ตั้งรหัสผ่าน">
      </div>

      <div class="form-group">
        <label>ยืนยันรหัสผ่าน <span class="req">*</span></label>
        <input type="password" name="confirm" required placeholder="พิมพ์รหัสผ่านอีกครั้ง">
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:4px;">สมัครสมาชิก</button>
    </form>
    <p style="text-align:center;margin-top:14px;font-size:14px;color:#666;">
      มีบัญชีแล้ว? <a href="login.php" style="color:#2e7d32;font-weight:600;">เข้าสู่ระบบ</a>
    </p>
    <?php endif; ?>
  </div>
</div>

<style>
.form-section-label {
  font-size: 12px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .06em; color: #2e7d32;
  border-bottom: 2px solid #c8e6c9; padding-bottom: 4px; margin-bottom: 14px;
}
.req { color: #c62828; }
</style>

<?php require_once 'includes/footer.php'; ?>
