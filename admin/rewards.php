<?php
// admin/rewards.php — รายชื่อผู้ผ่านเกณฑ์และ claim รางวัล
require_once '../includes/config.php';
requireAdmin();

// รายชื่อที่ claim แล้ว
$claimed = $conn->query("
    SELECT u.name, u.position, u.department, qr.score, qr.total,
           ROUND(qr.score/qr.total*100) AS pct,
           qr.attempt, rc.claimed_at
    FROM reward_claims rc
    JOIN users u ON u.id = rc.user_id
    JOIN quiz_results qr ON qr.id = rc.result_id
    ORDER BY rc.claimed_at
")->fetch_all(MYSQLI_ASSOC);

// รายชื่อผ่าน 80% แต่ยังไม่ claim
$notClaimed = $conn->query("
    SELECT u.name, u.position, u.department, qr.score, qr.total,
           ROUND(qr.score/qr.total*100) AS pct, qr.attempt
    FROM quiz_results qr
    JOIN users u ON u.id = qr.user_id
    WHERE qr.quiz_type='post' AND qr.passed=1
      AND qr.user_id NOT IN (SELECT user_id FROM reward_claims)
    ORDER BY u.name
")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'รายชื่อรับรางวัล';
require_once '../includes/header.php';
require_once '../includes/admin_nav.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
  <h2>🏆 รายชื่อรับรางวัล — VCT Day 1 ก.ค. 69</h2>
  <button onclick="window.print()" class="btn btn-primary">🖨 พิมพ์รายชื่อ</button>
</div>

<div class="card">
  <h2 style="font-size:16px;">✅ ลงทะเบียนรับรางวัลแล้ว (<?= count($claimed) ?> คน)</h2>
  <?php if ($claimed): ?>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>ชื่อ</th><th>ตำแหน่ง</th><th>กลุ่มงาน</th><th>คะแนน</th><th>%</th><th>ครั้งที่</th><th>ลงทะเบียนเมื่อ</th></tr></thead>
    <tbody>
    <?php foreach ($claimed as $i => $r): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td><?= htmlspecialchars($r['position'] ?? '—') ?></td>
      <td><?= htmlspecialchars($r['department'] ?? '—') ?></td>
      <td><?= $r['score'] ?>/<?= $r['total'] ?></td>
      <td><strong><?= $r['pct'] ?>%</strong></td>
      <td><?= $r['attempt'] ?></td>
      <td><?= date('d/m/Y H:i', strtotime($r['claimed_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
    <p style="color:#888;">ยังไม่มีผู้ลงทะเบียน</p>
  <?php endif; ?>
</div>

<?php if ($notClaimed): ?>
<div class="card">
  <h2 style="font-size:16px;color:#f9a825;">⏳ ผ่านเกณฑ์แต่ยังไม่ลงทะเบียน (<?= count($notClaimed) ?> คน)</h2>
  <div class="table-wrap"><table>
    <thead><tr><th>#</th><th>ชื่อ</th><th>ตำแหน่ง</th><th>กลุ่มงาน</th><th>คะแนน</th><th>%</th></tr></thead>
    <tbody>
    <?php foreach ($notClaimed as $i => $r): ?>
    <tr>
      <td><?= $i+1 ?></td>
      <td><?= htmlspecialchars($r['name']) ?></td>
      <td><?= htmlspecialchars($r['position'] ?? '—') ?></td>
      <td><?= htmlspecialchars($r['department'] ?? '—') ?></td>
      <td><?= $r['score'] ?>/<?= $r['total'] ?></td>
      <td><strong><?= $r['pct'] ?>%</strong></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>

<style>
@media print {
  .navbar, .footer, button, .btn, .an-wrap { display:none!important; }
  .card { box-shadow:none; border:1px solid #ddd; }
}
</style>

<?php require_once '../includes/footer.php'; ?>
