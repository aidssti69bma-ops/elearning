<?php
require_once '../includes/config.php';
requireAdmin();

$msg = '';

// เพิ่ม
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_course'])) {
    $title=$trim=$_POST['title']; $title=trim($title);
    $desc=trim($_POST['description']); $thumb=trim($_POST['thumbnail']);
    $pass=(int)($_POST['pass_score']??80); $ord=(int)($_POST['sort_order']??0);
    if ($title) {
        $stmt=$conn->prepare("INSERT INTO courses (sort_order,title,description,thumbnail,pass_score) VALUES (?,?,?,?,?)");
        $stmt->bind_param('isssi',$ord,$title,$desc,$thumb,$pass);
        $stmt->execute();
        $msg='<div class="alert alert-success">✅ เพิ่มหลักสูตรแล้ว</div>';
    }
}

// แก้ไข
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_course'])) {
    $id=(int)$_POST['edit_id'];
    $title=trim($_POST['title']); $desc=trim($_POST['description']);
    $thumb=trim($_POST['thumbnail']); $pass=(int)$_POST['pass_score'];
    $ord=(int)$_POST['sort_order'];
    if ($title && $id) {
        $stmt=$conn->prepare("UPDATE courses SET sort_order=?,title=?,description=?,thumbnail=?,pass_score=? WHERE id=?");
        $stmt->bind_param('isssii',$ord,$title,$desc,$thumb,$pass,$id);
        $stmt->execute();
        $msg='<div class="alert alert-success">✅ แก้ไขหลักสูตรแล้ว</div>';
    }
}

// ลบ
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM courses WHERE id=".(int)$_GET['delete']);
    $msg='<div class="alert alert-danger">ลบหลักสูตรแล้ว</div>';
}

// toggle active
if (isset($_GET['toggle'])) {
    $conn->query("UPDATE courses SET is_active=1-is_active WHERE id=".(int)$_GET['toggle']);
    redirect('courses.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$editData = $editId ? $conn->query("SELECT * FROM courses WHERE id=$editId")->fetch_assoc() : null;

$courses = $conn->query("SELECT c.*,
    (SELECT COUNT(*) FROM lessons l WHERE l.course_id=c.id AND l.is_active=1) AS lesson_cnt,
    (SELECT COUNT(*) FROM questions q WHERE q.course_id=c.id AND q.is_active=1 AND q.quiz_type='pre') AS pre_cnt,
    (SELECT COUNT(*) FROM questions q WHERE q.course_id=c.id AND q.is_active=1 AND q.quiz_type='post') AS post_cnt,
    (SELECT COUNT(DISTINCT qr.user_id) FROM quiz_results qr WHERE qr.course_id=c.id AND qr.quiz_type='post' AND qr.passed=1) AS passed_cnt
    FROM courses c ORDER BY c.sort_order")->fetch_all(MYSQLI_ASSOC);

$pageTitle='จัดการหลักสูตร';
require_once '../includes/header.php';
require_once '../includes/admin_nav.php';
?>
<?= $msg ?>

<!-- ฟอร์มเพิ่ม/แก้ไข -->
<div class="card">
  <h2 style="font-size:15px;margin-bottom:16px;"><?= $editData ? '✏️ แก้ไขหลักสูตร' : '➕ เพิ่มหลักสูตรใหม่' ?></h2>
  <form method="post">
    <?php if ($editData): ?>
      <input type="hidden" name="edit_id" value="<?= $editData['id'] ?>">
    <?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 80px 80px 80px;gap:12px;margin-bottom:12px;">
      <div class="form-group" style="margin:0;">
        <label>ชื่อหลักสูตร</label>
        <input type="text" name="title" required value="<?= htmlspecialchars($editData['title'] ?? '') ?>" placeholder="ชื่อหลักสูตร">
      </div>
      <div class="form-group" style="margin:0;">
        <label>ไอคอน (emoji)</label>
        <input type="text" name="thumbnail" value="<?= htmlspecialchars($editData['thumbnail'] ?? '📚') ?>">
      </div>
      <div class="form-group" style="margin:0;">
        <label>เกณฑ์ผ่าน %</label>
        <input type="number" name="pass_score" value="<?= $editData['pass_score'] ?? 80 ?>" min="1" max="100">
      </div>
      <div class="form-group" style="margin:0;">
        <label>ลำดับ</label>
        <input type="number" name="sort_order" value="<?= $editData['sort_order'] ?? count($courses)+1 ?>">
      </div>
    </div>
    <div class="form-group">
      <label>คำอธิบาย</label>
      <textarea name="description" rows="2" style="width:100%;"><?= htmlspecialchars($editData['description'] ?? '') ?></textarea>
    </div>
    <div style="display:flex;gap:8px;">
      <button type="submit" name="<?= $editData ? 'edit_course' : 'add_course' ?>" class="btn btn-primary">
        <?= $editData ? '💾 บันทึกการแก้ไข' : '+ เพิ่มหลักสูตร' ?>
      </button>
      <?php if ($editData): ?>
        <a href="courses.php" class="btn btn-outline">ยกเลิก</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- รายการ -->
<div class="card">
  <h2 style="font-size:15px;margin-bottom:12px;">รายการหลักสูตรทั้งหมด (<?= count($courses) ?> หลักสูตร)</h2>
  <?php if ($courses): ?>
  <div class="table-wrap"><table>
    <thead><tr><th>ลำดับ</th><th>หลักสูตร</th><th style="text-align:center">บทเรียน</th><th style="text-align:center">Pre</th><th style="text-align:center">Post</th><th style="text-align:center">ผ่าน</th><th style="text-align:center">สถานะ</th><th>จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($courses as $c): ?>
    <tr style="<?= $editId==$c['id']?'background:#f1f8e9':'' ?>">
      <td><?= $c['sort_order'] ?></td>
      <td>
        <span style="font-size:18px;margin-right:6px;"><?= $c['thumbnail'] ?></span>
        <strong><?= htmlspecialchars($c['title']) ?></strong>
        <?php if ($c['description']): ?><div style="font-size:12px;color:#888;"><?= htmlspecialchars(mb_strimwidth($c['description'],0,50,'...')) ?></div><?php endif; ?>
        <div style="font-size:11px;color:#2e7d32;">เกณฑ์ผ่าน <?= $c['pass_score'] ?>%</div>
      </td>
      <td style="text-align:center;"><?= $c['lesson_cnt'] ?></td>
      <td style="text-align:center;"><?= $c['pre_cnt'] ?></td>
      <td style="text-align:center;"><?= $c['post_cnt'] ?></td>
      <td style="text-align:center;font-weight:600;color:#2e7d32;"><?= $c['passed_cnt'] ?></td>
      <td style="text-align:center;">
        <a href="?toggle=<?= $c['id'] ?>" style="background:<?= $c['is_active']?'#e8f5e9':'#ffebee' ?>;color:<?= $c['is_active']?'#1b5e20':'#b71c1c' ?>;padding:2px 10px;border-radius:20px;font-size:12px;text-decoration:none;">
          <?= $c['is_active']?'✅ เปิด':'⛔ ปิด' ?>
        </a>
      </td>
      <td style="white-space:nowrap;">
        <a href="?edit=<?= $c['id'] ?>" class="btn btn-warning" style="font-size:12px;padding:4px 10px;">✏️ แก้ไข</a>
        <a href="lessons.php?course_id=<?= $c['id'] ?>" class="btn btn-outline" style="font-size:12px;padding:4px 10px;">📚</a>
        <a href="questions.php?course_id=<?= $c['id'] ?>" class="btn btn-outline" style="font-size:12px;padding:4px 10px;">📝</a>
        <a href="?delete=<?= $c['id'] ?>" class="btn btn-danger" style="font-size:12px;padding:4px 10px;" onclick="return confirm('ลบหลักสูตรนี้?')">ลบ</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><p style="color:#888;">ยังไม่มีหลักสูตร</p><?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
