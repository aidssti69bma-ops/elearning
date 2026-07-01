<?php
// admin/users.php
require_once '../includes/config.php';
requireAdmin();

$users = $conn->query("
    SELECT u.id, u.name, u.position, u.department, u.phone, u.email, u.created_at,
        (SELECT COUNT(DISTINCT course_id) FROM quiz_results WHERE user_id=u.id AND quiz_type='pre')    courses_started,
        (SELECT COUNT(DISTINCT course_id) FROM quiz_results WHERE user_id=u.id AND quiz_type='post' AND passed=1) courses_passed,
        (SELECT COUNT(*) FROM reward_claims WHERE user_id=u.id) rewards
    FROM users u WHERE u.role='user' ORDER BY u.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$totalCourses=(int)$conn->query("SELECT COUNT(*) c FROM courses WHERE is_active=1")->fetch_assoc()['c'];

$pageTitle='ผู้ใช้งาน';
require_once '../includes/header.php';
require_once '../includes/admin_nav.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
  <div>
    <h2 style="font-size:16px;color:#2e7d32;margin:0;">👥 ผู้ใช้งานทั้งหมด (<?=count($users)?> คน)</h2>
    <div style="font-size:12px;color:#888;margin-top:2px;">มีหลักสูตรทั้งหมด <?=$totalCourses?> หลักสูตร</div>
  </div>
</div>

<div class="card" style="padding:0;overflow:hidden;">
<div class="table-wrap">
<table>
  <thead>
    <tr>
      <th>#</th><th>ชื่อ-นามสกุล</th><th>ตำแหน่ง</th><th>กลุ่มงาน</th>
      <th>เบอร์โทร</th><th>อีเมล</th>
      <th style="text-align:center">เริ่มเรียน</th>
      <th style="text-align:center">ผ่านแล้ว</th>
      <th style="text-align:center">รางวัล</th>
      <th>วันสมัคร</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($users as $i=>$u): ?>
  <tr>
    <td style="font-size:12px;color:#aaa;"><?=$i+1?></td>
    <td style="font-weight:600;font-size:13px;white-space:nowrap;"><?=htmlspecialchars($u['name'])?></td>
    <td style="font-size:12px;"><?=htmlspecialchars($u['position']??'—')?></td>
    <td style="font-size:12px;"><?=htmlspecialchars($u['department']??'—')?></td>
    <td style="font-size:12px;white-space:nowrap;"><?=htmlspecialchars($u['phone']??'—')?></td>
    <td style="font-size:12px;"><?=htmlspecialchars($u['email'])?></td>
    <td style="text-align:center;">
      <span style="font-weight:700;color:#1565c0;"><?=$u['courses_started']?></span>
      <span style="color:#aaa;font-size:11px;">/<?=$totalCourses?></span>
    </td>
    <td style="text-align:center;">
      <span style="font-weight:700;color:<?=$u['courses_passed']>0?'#2e7d32':'#ccc'?>"><?=$u['courses_passed']?></span>
      <span style="color:#aaa;font-size:11px;">/<?=$totalCourses?></span>
    </td>
    <td style="text-align:center;"><?=$u['rewards']>0?"<span style='color:#e65100;font-weight:700;'>🏆 {$u['rewards']}</span>":'—'?></td>
    <td style="font-size:12px;white-space:nowrap;"><?=date('d/m/Y',strtotime($u['created_at']))?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>
</div>

<div style="font-size:12px;color:#aaa;margin-top:8px;text-align:center;">
  💡 จัดการข้อมูลรายบุคคลได้ที่ Tab "รายงาน" ในแต่ละหลักสูตร
</div>

<?php require_once '../includes/footer.php'; ?>
