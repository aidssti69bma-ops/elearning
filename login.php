<?php
// login.php — กรอกชื่อ/ตำแหน่ง/กลุ่มงาน แล้วเข้าได้เลย ไม่ต้องรหัสผ่าน
require_once 'includes/config.php';

// ถ้า session ยังอยู่ → เข้าได้เลย
if (!empty($_SESSION['user_id'])) redirect('/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name       = trim($_POST['name']       ?? '');
    $position   = trim($_POST['position']   ?? '');
    $department = trim($_POST['department'] ?? '');
    $phone      = trim($_POST['phone']      ?? '');

    if (!$name || !$position || !$department) {
        $error = 'กรุณากรอกชื่อ ตำแหน่ง และกลุ่มงานให้ครบ';
    } else {
        // หาใน DB ด้วยชื่อ+ตำแหน่ง+กลุ่มงาน
        $stmt = $conn->prepare("SELECT id, name, position, department FROM users WHERE name=? AND position=? AND department=? AND role='user' LIMIT 1");
        $stmt->bind_param('sss', $name, $position, $department);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            // สร้าง user ใหม่อัตโนมัติ
            $fakeEmail = 'user_' . time() . '_' . rand(1000,9999) . '@elearning.local';
            $fakeHash  = password_hash(uniqid(), PASSWORD_BCRYPT);
            $stmt2 = $conn->prepare("INSERT INTO users (name, position, department, phone, email, password, role) VALUES (?,?,?,?,?,?,'user')");
            $stmt2->bind_param('ssssss', $name, $position, $department, $phone, $fakeEmail, $fakeHash);
            $stmt2->execute();
            $uid = $conn->insert_id;
            $user = ['id'=>$uid, 'name'=>$name, 'position'=>$position, 'department'=>$department];
        }

        // set session
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['name']       = $user['name'];
        $_SESSION['role']       = 'user';
        $_SESSION['position']   = $user['position'];
        $_SESSION['department'] = $user['department'];

        // จำข้อมูลไว้ใน cookie 30 วัน
        setcookie('el_name',       $name,       time()+86400*30, '/');
        setcookie('el_position',   $position,   time()+86400*30, '/');
        setcookie('el_department', $department, time()+86400*30, '/');
        setcookie('el_phone',      $phone,      time()+86400*30, '/');

        redirect('/index.php');
    }
}

// ดึงข้อมูลจาก cookie (ถ้ามี)
$savedName       = $_COOKIE['el_name']       ?? '';
$savedPosition   = $_COOKIE['el_position']   ?? '';
$savedDepartment = $_COOKIE['el_department'] ?? '';
$savedPhone      = $_COOKIE['el_phone']      ?? '';

$pageTitle = 'เข้าสู่ระบบ';
require_once 'includes/header.php';
?>

<div style="max-width:480px;margin:0 auto;">
  <div class="card">
    <div style="text-align:center;margin-bottom:20px;">
      <div style="font-size:48px;margin-bottom:8px;">🌿</div>
      <h2 style="margin:0 0 4px;">เข้าสู่ระบบ E-Learning</h2>
      <p style="color:#666;font-size:13px;margin:0;">กรอกข้อมูลเพื่อเริ่มเรียน ไม่ต้องสมัครสมาชิก</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <div class="form-group">
        <label>ชื่อ-นามสกุล <span style="color:#c62828;">*</span></label>
        <input type="text" name="name" required autofocus
          value="<?= htmlspecialchars($savedName) ?>"
          placeholder="เช่น นายสมชาย ใจดี">
      </div>
      <div class="form-group">
        <label>ตำแหน่ง <span style="color:#c62828;">*</span></label>
        <input type="text" name="position" required
          value="<?= htmlspecialchars($savedPosition) ?>"
          placeholder="เช่น นักวิชาการสาธารณสุข">
      </div>
      <div class="form-group">
        <label>กลุ่มงาน / หน่วยงาน <span style="color:#c62828;">*</span></label>
        <input type="text" name="department" required
          value="<?= htmlspecialchars($savedDepartment) ?>"
          placeholder="เช่น กลุ่มงานโรคเอดส์ฯ">
      </div>
      <div class="form-group">
        <label>เบอร์โทรศัพท์ <span style="color:#888;font-size:12px;">(ไม่บังคับ)</span></label>
        <input type="tel" name="phone"
          value="<?= htmlspecialchars($savedPhone) ?>"
          placeholder="เช่น 081-234-5678">
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="font-size:15px;padding:12px;">
        ▶ เข้าสู่ระบบ
      </button>
    </form>

    <div style="margin-top:14px;padding:12px;background:#f9fdf9;border-radius:8px;font-size:12px;color:#666;text-align:center;">
      💡 ระบบจะจำข้อมูลของคุณไว้ 30 วัน ครั้งถัดไปไม่ต้องกรอกใหม่
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>
