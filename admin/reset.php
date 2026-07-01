<?php
// admin/reset.php — ล้าง/ลบข้อมูลใน DB
require_once '../includes/config.php';
requireAdmin();

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    // ต้องพิมพ์ CONFIRM ก่อนทุก action
    if ($confirm !== 'CONFIRM') {
        $msg = '<div class="alert alert-danger">❌ กรุณาพิมพ์ CONFIRM ให้ถูกต้องก่อนดำเนินการ</div>';
    } else {
        switch ($action) {

            case 'clear_results':
                $conn->query("DELETE FROM quiz_answers");
                $conn->query("DELETE FROM quiz_results");
                $conn->query("DELETE FROM reward_claims");
                $msg = '<div class="alert alert-success">✅ ล้างผลการทดสอบและรางวัลทั้งหมดแล้ว</div>';
                break;

            case 'clear_rewards':
                $conn->query("DELETE FROM reward_claims");
                $msg = '<div class="alert alert-success">✅ ล้างข้อมูลการรับรางวัลแล้ว</div>';
                break;

            case 'clear_users':
                // ลบผู้ใช้ทั้งหมดยกเว้น admin
                $conn->query("DELETE FROM quiz_answers WHERE result_id IN (SELECT id FROM quiz_results WHERE user_id IN (SELECT id FROM users WHERE role='user'))");
                $conn->query("DELETE FROM quiz_results WHERE user_id IN (SELECT id FROM users WHERE role='user')");
                $conn->query("DELETE FROM reward_claims WHERE user_id IN (SELECT id FROM users WHERE role='user')");
                $conn->query("DELETE FROM users WHERE role='user'");
                $msg = '<div class="alert alert-success">✅ ลบผู้ใช้และข้อมูลที่เกี่ยวข้องทั้งหมดแล้ว (ยกเว้น admin)</div>';
                break;

            case 'clear_lessons':
                $conn->query("DELETE FROM lessons");
                $msg = '<div class="alert alert-success">✅ ลบบทเรียนทั้งหมดแล้ว</div>';
                break;

            case 'clear_questions':
                $conn->query("DELETE FROM quiz_answers");
                $conn->query("DELETE FROM quiz_results");
                $conn->query("DELETE FROM reward_claims");
                $conn->query("DELETE FROM questions");
                $msg = '<div class="alert alert-success">✅ ลบข้อสอบและผลการทดสอบทั้งหมดแล้ว</div>';
                break;

            case 'delete_user':
                $uid = (int)($_POST['target_id'] ?? 0);
                if ($uid) {
                    $conn->query("DELETE FROM quiz_answers WHERE result_id IN (SELECT id FROM quiz_results WHERE user_id=$uid)");
                    $conn->query("DELETE FROM quiz_results WHERE user_id=$uid");
                    $conn->query("DELETE FROM reward_claims WHERE user_id=$uid");
                    $conn->query("DELETE FROM users WHERE id=$uid AND role='user'");
                    $msg = '<div class="alert alert-success">✅ ลบผู้ใช้และข้อมูลที่เกี่ยวข้องแล้ว</div>';
                }
                break;

            case 'reset_user_quiz':
                $uid = (int)($_POST['target_id'] ?? 0);
                if ($uid) {
                    $conn->query("DELETE FROM quiz_answers WHERE result_id IN (SELECT id FROM quiz_results WHERE user_id=$uid)");
                    $conn->query("DELETE FROM quiz_results WHERE user_id=$uid");
                    $conn->query("DELETE FROM reward_claims WHERE user_id=$uid");
                    $msg = '<div class="alert alert-success">✅ รีเซ็ตประวัติการทดสอบของผู้ใช้นี้แล้ว (ยังคงบัญชีไว้)</div>';
                }
                break;

            default:
                $msg = '<div class="alert alert-danger">❌ ไม่พบ action ที่ระบุ</div>';
        }
    }
}

// ดึงรายชื่อผู้ใช้
$users = $conn->query("SELECT id, name, email FROM users WHERE role='user' ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'ล้างข้อมูล';
require_once '../includes/header.php';
require_once '../includes/admin_nav.php';
?>

<?= $msg ?>

<div class="alert alert-warning">
  ⚠️ <strong>คำเตือน:</strong> การดำเนินการในหน้านี้ไม่สามารถย้อนกลับได้ กรุณาตรวจสอบให้แน่ใจก่อนกด
</div>

<!-- ล้างข้อมูลรวม -->
<div class="card">
  <h2>🗑 ล้างข้อมูลแบบรวม</h2>
  <p style="color:#666;font-size:14px;margin-bottom:20px;">พิมพ์ <code style="background:#ffebee;padding:2px 8px;border-radius:4px;color:#c62828;">CONFIRM</code> ในช่องยืนยันทุกครั้งก่อนกด</p>

  <?php
  $bulkActions = [
    ['clear_results',   '🔄 ล้างผลการทดสอบ + รางวัลทั้งหมด', 'ล้างข้อมูล quiz_results, quiz_answers, reward_claims ทั้งหมด (ผู้ใช้ยังอยู่)', 'btn-warning'],
    ['clear_rewards',   '🏆 ล้างข้อมูลรับรางวัลอย่างเดียว',   'ล้างเฉพาะ reward_claims (ผลทดสอบยังอยู่)', 'btn-warning'],
    ['clear_questions', '📝 ลบข้อสอบทั้งหมด',                'ลบ questions + ผลการทดสอบทุกอย่างที่เกี่ยวข้อง', 'btn-danger'],
    ['clear_lessons',   '📚 ลบบทเรียนทั้งหมด',               'ลบ lessons ทั้งหมดออกจากระบบ', 'btn-danger'],
    ['clear_users',     '👥 ลบผู้ใช้ทั้งหมด',                'ลบ users + ข้อมูลที่เกี่ยวข้องทั้งหมด (ยกเว้น admin)', 'btn-danger'],
  ];
  foreach ($bulkActions as [$act, $label, $desc, $btnClass]):
  ?>
  <form method="post" style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #eee;flex-wrap:wrap;">
    <input type="hidden" name="action" value="<?= $act ?>">
    <div style="flex:1;min-width:200px;">
      <strong><?= $label ?></strong>
      <div style="font-size:12px;color:#888;"><?= $desc ?></div>
    </div>
    <input type="text" name="confirm" placeholder="พิมพ์ CONFIRM" autocomplete="off"
      style="width:160px;padding:8px 12px;border:1.5px solid #ef9a9a;border-radius:6px;font-size:14px;">
    <button type="submit" class="btn <?= $btnClass ?>" style="font-size:13px;padding:8px 16px;"
      onclick="return confirm('ยืนยันการลบข้อมูล?')">ดำเนินการ</button>
  </form>
  <?php endforeach; ?>
</div>

<!-- จัดการรายบุคคล -->
<div class="card">
  <h2>👤 จัดการผู้ใช้รายบุคคล</h2>
  <?php if ($users): ?>
  <table>
    <thead><tr><th>#</th><th>ชื่อ</th><th>อีเมล</th><th colspan="2" style="text-align:center;">จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($users as $i => $u): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= htmlspecialchars($u['name']) ?></td>
      <td><?= htmlspecialchars($u['email']) ?></td>
      <td>
        <!-- รีเซ็ตประวัติ -->
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="reset_user_quiz">
          <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
          <input type="text" name="confirm" placeholder="CONFIRM" autocomplete="off"
            style="width:110px;padding:4px 8px;border:1px solid #f0a500;border-radius:4px;font-size:12px;">
          <button type="submit" class="btn btn-warning" style="font-size:12px;padding:5px 10px;margin-left:4px;"
            onclick="return confirm('รีเซ็ตประวัติทดสอบของ <?= addslashes($u['name']) ?>?')">🔄 รีเซ็ต</button>
        </form>
      </td>
      <td>
        <!-- ลบบัญชี -->
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="delete_user">
          <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
          <input type="text" name="confirm" placeholder="CONFIRM" autocomplete="off"
            style="width:110px;padding:4px 8px;border:1px solid #ef9a9a;border-radius:4px;font-size:12px;">
          <button type="submit" class="btn btn-danger" style="font-size:12px;padding:5px 10px;margin-left:4px;"
            onclick="return confirm('ลบบัญชีและข้อมูลทั้งหมดของ <?= addslashes($u['name']) ?>?')">🗑 ลบบัญชี</button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p style="color:#888;">ไม่มีผู้ใช้ในระบบ</p>
  <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
