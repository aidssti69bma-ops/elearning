<?php
require_once '../includes/config.php';
requireAdmin();

$msg = '';

// เพิ่ม
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add'])) {
    $cid=$_POST['course_id']===''?null:(int)$_POST['course_id'];
    $title=trim($_POST['title']); $body=trim($_POST['body']); $pinned=isset($_POST['is_pinned'])?1:0;
    if ($title && $body) {
        $stmt=$conn->prepare("INSERT INTO announcements (course_id,title,body,is_pinned) VALUES (?,?,?,?)");
        $stmt->bind_param('issi',$cid,$title,$body,$pinned);
        $stmt->execute();
        $msg='<div class="alert alert-success">✅ เพิ่มประกาศแล้ว</div>';
    }
}

// แก้ไข
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit'])) {
    $id=(int)$_POST['edit_id'];
    $cid=$_POST['course_id']===''?null:(int)$_POST['course_id'];
    $title=trim($_POST['title']); $body=trim($_POST['body']); $pinned=isset($_POST['is_pinned'])?1:0;
    if ($title && $body && $id) {
        $stmt=$conn->prepare("UPDATE announcements SET course_id=?,title=?,body=?,is_pinned=? WHERE id=?");
        $stmt->bind_param('issii',$cid,$title,$body,$pinned,$id);
        $stmt->execute();
        $msg='<div class="alert alert-success">✅ แก้ไขประกาศแล้ว</div>';
    }
}

// ลบ
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM announcements WHERE id=".(int)$_GET['delete']);
    $msg='<div class="alert alert-danger">ลบประกาศแล้ว</div>';
}

// toggle pin / active
if (isset($_GET['pin']))    { $conn->query("UPDATE announcements SET is_pinned=1-is_pinned WHERE id=".(int)$_GET['pin']);   redirect('announcements.php'); }
if (isset($_GET['toggle'])) { $conn->query("UPDATE announcements SET is_active=1-is_active WHERE id=".(int)$_GET['toggle']); redirect('announcements.php'); }

$editId   = (int)($_GET['edit'] ?? 0);
$editData = $editId ? $conn->query("SELECT * FROM announcements WHERE id=$editId")->fetch_assoc() : null;

$courses       = $conn->query("SELECT id,title,thumbnail FROM courses ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$announcements = $conn->query("SELECT a.*, c.title AS c_title, c.thumbnail AS c_thumb FROM announcements a LEFT JOIN courses c ON c.id=a.course_id ORDER BY a.is_pinned DESC, a.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$pageTitle='จัดการประกาศ';
require_once '../includes/header.php';
require_once '../includes/admin_nav.php';
?>
<?= $msg ?>

<div class="card">
  <h2 style="font-size:15px;margin-bottom:16px;"><?= $editData ? '✏️ แก้ไขประกาศ' : '➕ เพิ่มประกาศใหม่' ?></h2>
  <form method="post">
    <?php if ($editData): ?><input type="hidden" name="edit_id" value="<?= $editData['id'] ?>"><?php endif; ?>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
      <div class="form-group" style="margin:0;">
        <label>ห้องเรียน</label>
        <select name="course_id">
          <option value="" <?= ($editData && $editData['course_id']===null)?'selected':(!$editData?'selected':'') ?>>📢 ทุกห้อง (ประกาศทั่วไป)</option>
          <?php foreach ($courses as $c): ?>
          <option value="<?= $c['id'] ?>" <?= ($editData['course_id']??null)==$c['id']?'selected':'' ?>>
            <?= $c['thumbnail'] ?> <?= htmlspecialchars($c['title']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin:0;">
        <label>หัวข้อประกาศ</label>
        <input type="text" name="title" required value="<?= htmlspecialchars($editData['title'] ?? '') ?>" placeholder="หัวข้อ...">
      </div>
    </div>
    <div class="form-group">
      <label>เนื้อหาประกาศ</label>
      <textarea name="body" rows="3" required style="width:100%;"><?= htmlspecialchars($editData['body'] ?? '') ?></textarea>
    </div>
    <label style="display:flex;align-items:center;gap:8px;font-size:14px;margin-bottom:12px;cursor:pointer;">
      <input type="checkbox" name="is_pinned" <?= ($editData['is_pinned']??0)?'checked':'' ?>> 📌 ปักหมุดประกาศนี้
    </label>
    <div style="display:flex;gap:8px;">
      <button type="submit" name="<?= $editData ? 'edit' : 'add' ?>" class="btn btn-primary">
        <?= $editData ? '💾 บันทึกการแก้ไข' : '+ เพิ่มประกาศ' ?>
      </button>
      <?php if ($editData): ?><a href="announcements.php" class="btn btn-outline">ยกเลิก</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <h2 style="font-size:15px;margin-bottom:12px;">ประกาศทั้งหมด (<?= count($announcements) ?> รายการ)</h2>
  <?php if ($announcements): ?>
  <div class="table-wrap"><table>
    <thead><tr><th>ห้องเรียน</th><th>หัวข้อ</th><th>เนื้อหา</th><th style="text-align:center">ปักหมุด</th><th style="text-align:center">สถานะ</th><th>วันที่</th><th>จัดการ</th></tr></thead>
    <tbody>
    <?php foreach ($announcements as $a): ?>
    <tr style="<?= !$a['is_active']?'opacity:.5':'' ?><?= $editId==$a['id']?' background:#f1f8e9':'' ?>">
      <td style="font-size:12px;"><?= $a['c_title'] ? $a['c_thumb'].' '.htmlspecialchars(mb_strimwidth($a['c_title'],0,20,'...')) : '<span style="color:#1565c0;">📢 ทุกห้อง</span>' ?></td>
      <td style="font-weight:600;font-size:13px;"><?= htmlspecialchars($a['title']) ?></td>
      <td style="font-size:12px;color:#666;"><?= htmlspecialchars(mb_strimwidth($a['body'],0,50,'...')) ?></td>
      <td style="text-align:center;"><a href="?pin=<?= $a['id'] ?>" style="font-size:18px;text-decoration:none;"><?= $a['is_pinned']?'📌':'⬜' ?></a></td>
      <td style="text-align:center;">
        <a href="?toggle=<?= $a['id'] ?>" style="font-size:12px;padding:2px 10px;border-radius:20px;text-decoration:none;background:<?= $a['is_active']?'#e8f5e9':'#f5f5f5' ?>;color:<?= $a['is_active']?'#1b5e20':'#888' ?>;">
          <?= $a['is_active']?'✅ แสดง':'⛔ ซ่อน' ?>
        </a>
      </td>
      <td style="font-size:12px;white-space:nowrap;"><?= date('d/m/Y',strtotime($a['created_at'])) ?></td>
      <td style="white-space:nowrap;">
        <a href="?edit=<?= $a['id'] ?>" class="btn btn-warning" style="font-size:12px;padding:4px 10px;">✏️ แก้ไข</a>
        <a href="?delete=<?= $a['id'] ?>" class="btn btn-danger" style="font-size:12px;padding:4px 10px;" onclick="return confirm('ลบประกาศนี้?')">ลบ</a>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?><p style="color:#888;">ยังไม่มีประกาศ</p><?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
