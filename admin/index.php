<?php
// admin/index.php
require_once '../includes/config.php';
requireAdmin();

$msg = '';

// เพิ่ม course
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_course'])) {
    $title=trim($_POST['title']); $desc=trim($_POST['description']);
    $thumb=trim($_POST['thumbnail'])?:'📚'; $pass=(int)($_POST['pass_score']??80);
    $ord=(int)$conn->query("SELECT COALESCE(MAX(sort_order),0)+1 m FROM courses")->fetch_assoc()['m'];
    if ($title) {
        $stmt=$conn->prepare("INSERT INTO courses (sort_order,title,description,thumbnail,pass_score) VALUES (?,?,?,?,?)");
        $stmt->bind_param('isssi',$ord,$title,$desc,$thumb,$pass); $stmt->execute();
        $msg='<div class="alert alert-success">✅ เพิ่มหลักสูตรแล้ว</div>';
    }
}

// แก้ไข course
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_course'])) {
    $id=(int)$_POST['edit_id']; $title=trim($_POST['title']); $desc=trim($_POST['description']);
    $thumb=trim($_POST['thumbnail'])?:'📚'; $pass=(int)$_POST['pass_score']; $ord=(int)$_POST['sort_order'];
    if ($title && $id) {
        $stmt=$conn->prepare("UPDATE courses SET sort_order=?,title=?,description=?,thumbnail=?,pass_score=? WHERE id=?");
        $stmt->bind_param('isssii',$ord,$title,$desc,$thumb,$pass,$id); $stmt->execute();
        $msg='<div class="alert alert-success">✅ แก้ไขหลักสูตรแล้ว</div>';
    }
}

// ลบ
if (isset($_GET['delete'])) {
    $conn->query("DELETE FROM courses WHERE id=".(int)$_GET['delete']);
    header("Location: index.php?msg=deleted"); exit;
}

// toggle
if (isset($_GET['toggle'])) {
    $conn->query("UPDATE courses SET is_active=1-is_active WHERE id=".(int)$_GET['toggle']);
    header("Location: index.php"); exit;
}

if (isset($_GET['msg'])&&$_GET['msg']==='deleted')
    $msg='<div class="alert alert-danger">ลบหลักสูตรแล้ว</div>';

$editId   = (int)($_GET['edit']??0);
$editData = $editId ? $conn->query("SELECT * FROM courses WHERE id=$editId")->fetch_assoc() : null;

$courses = $conn->query("
    SELECT c.*,
        (SELECT COUNT(*) FROM lessons   l WHERE l.course_id=c.id AND l.is_active=1)   lesson_cnt,
        (SELECT COUNT(*) FROM questions q WHERE q.course_id=c.id AND q.is_active=1)   q_cnt,
        (SELECT COUNT(DISTINCT user_id) FROM quiz_results WHERE course_id=c.id AND quiz_type='pre')             started_cnt,
        (SELECT COUNT(DISTINCT user_id) FROM quiz_results WHERE course_id=c.id AND quiz_type='post' AND passed=1) passed_cnt
    FROM courses c ORDER BY c.sort_order
")->fetch_all(MYSQLI_ASSOC);

$totalUsers=(int)$conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'];

$pageTitle='Admin — หลักสูตร';
require_once '../includes/header.php';
require_once '../includes/admin_nav.php';
?>

<style>
.fg{margin-bottom:12px;}
.fg label{display:block;font-size:13px;font-weight:600;color:#555;margin-bottom:4px;}
.fg input,.fg textarea{width:100%;padding:9px 12px;border:1.5px solid #c8e6c9;border-radius:6px;font-size:14px;font-family:inherit;background:#fafff8;}
.fg input:focus,.fg textarea:focus{outline:none;border-color:#2e7d32;}
.form-row{display:grid;grid-template-columns:1fr 80px 80px;gap:12px;}
@media(max-width:600px){.form-row{grid-template-columns:1fr;}}

.course-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px;margin-top:20px;}
.course-card{background:#fff;border-radius:12px;box-shadow:0 2px 10px rgba(0,0,0,.08);overflow:hidden;transition:transform .2s,box-shadow .2s;}
.course-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.13);}
.cc-strip{padding:16px 18px;color:#fff;display:flex;align-items:center;gap:12px;}
.cc-icon{font-size:34px;}
.cc-title{font-size:15px;font-weight:700;line-height:1.3;}
.cc-desc{font-size:12px;opacity:.8;margin-top:2px;}
.cc-body{padding:14px 18px;}
.cc-stats{display:flex;gap:10px;margin-bottom:10px;flex-wrap:wrap;}
.cc-stat{flex:1;min-width:55px;text-align:center;}
.cc-stat-n{font-size:22px;font-weight:700;color:#2e7d32;line-height:1;}
.cc-stat-l{font-size:11px;color:#888;margin-top:1px;}
.cc-prog{background:#e8f5e9;border-radius:20px;height:6px;overflow:hidden;margin-bottom:12px;}
.cc-actions{display:flex;gap:6px;flex-wrap:wrap;}
.cc-btn{padding:7px 12px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;border:1px solid transparent;cursor:pointer;font-family:inherit;transition:opacity .15s;display:inline-block;text-align:center;}
.cc-btn:hover{opacity:.85;}
.cc-enter{background:#2e7d32;color:#fff;flex:1;padding:10px;font-size:13px;}
.cc-edit{background:#fff8e1;color:#e65100;border-color:#ffe082;}
.cc-tog{background:#e3f2fd;color:#1565c0;border-color:#90caf9;}
.cc-del{background:#ffebee;color:#c62828;border-color:#ef9a9a;}
</style>

<?= $msg ?>

<!-- ฟอร์มเพิ่ม/แก้ไข -->
<div class="card" <?php if($editData) echo 'style="border:2px solid #f9a825;background:#fffde7;"'; ?>>
  <h2 style="font-size:15px;"><?=$editData?'✏️ แก้ไขหลักสูตร':'➕ เพิ่มหลักสูตรใหม่'?></h2>
  <form method="post">
    <?php if ($editData): ?>
      <input type="hidden" name="edit_id"    value="<?=$editData['id']?>">
      <input type="hidden" name="sort_order" value="<?=$editData['sort_order']?>">
    <?php endif; ?>
    <div class="form-row">
      <div class="fg" style="margin:0;">
        <label>ชื่อหลักสูตร *</label>
        <input type="text" name="title" required
          value="<?=htmlspecialchars($editData['title']??'')?>"
          placeholder="เช่น ความรู้เรื่อง HIV">
      </div>
      <div class="fg" style="margin:0;">
        <label>ไอคอน Emoji</label>
        <input type="text" name="thumbnail" value="<?=htmlspecialchars($editData['thumbnail']??'📚')?>">
      </div>
      <div class="fg" style="margin:0;">
        <label>เกณฑ์ผ่าน %</label>
        <input type="number" name="pass_score" min="1" max="100" value="<?=$editData['pass_score']??80?>">
      </div>
    </div>
    <div class="fg">
      <label>คำอธิบาย</label>
      <textarea name="description" rows="2" placeholder="อธิบายสั้นๆ..."><?=htmlspecialchars($editData['description']??'')?></textarea>
    </div>
    <div style="display:flex;gap:8px;">
      <button type="submit" name="<?=$editData?'edit_course':'add_course'?>"
        class="btn btn-primary" style="font-size:14px;">
        <?=$editData?'💾 บันทึก':'+ เพิ่มหลักสูตร'?>
      </button>
      <?php if ($editData): ?>
        <a href="index.php" class="btn btn-outline">ยกเลิก</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<!-- รายการ course cards -->
<div style="font-size:14px;font-weight:700;color:#2e7d32;margin-bottom:8px;">
  หลักสูตรทั้งหมด (<?=count($courses)?> หลักสูตร)
  <span style="font-size:12px;font-weight:400;color:#888;">— กด "เข้าจัดการ" เพื่อแก้ไขบทเรียน ข้อสอบ และดูรายงาน</span>
</div>

<?php
$colors=['#1b5e20','#1565c0','#4a148c','#bf360c','#004d40','#880e4f','#37474f','#e65100'];
?>
<div class="course-grid">
<?php foreach ($courses as $i=>$c):
  $col=$colors[$i%count($colors)];
  $pct=$totalUsers>0?round($c['passed_cnt']/$totalUsers*100):0;
?>
<div class="course-card" style="<?=!$c['is_active']?'opacity:.6':''?>">
  <div class="cc-strip" style="background:linear-gradient(135deg,<?=$col?>,<?=$col?>cc);">
    <div class="cc-icon"><?=$c['thumbnail']?></div>
    <div>
      <div class="cc-title"><?=htmlspecialchars($c['title'])?></div>
      <?php if($c['description']): ?>
      <div class="cc-desc"><?=htmlspecialchars(mb_strimwidth($c['description'],0,45,'...'))?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="cc-body">
    <div class="cc-stats">
      <div class="cc-stat"><div class="cc-stat-n"><?=$c['lesson_cnt']?></div><div class="cc-stat-l">บทเรียน</div></div>
      <div class="cc-stat"><div class="cc-stat-n"><?=$c['q_cnt']?></div><div class="cc-stat-l">ข้อสอบ</div></div>
      <div class="cc-stat"><div class="cc-stat-n"><?=$c['started_cnt']?></div><div class="cc-stat-l">เริ่มเรียน</div></div>
      <div class="cc-stat"><div class="cc-stat-n" style="color:<?=$c['passed_cnt']>0?'#2e7d32':'#ccc'?>"><?=$c['passed_cnt']?></div><div class="cc-stat-l">ผ่านเกณฑ์</div></div>
    </div>
    <div style="font-size:11px;color:#888;margin-bottom:4px;">ผ่าน <?=$c['passed_cnt']?>/<?=$totalUsers?> คน | เกณฑ์ <?=$c['pass_score']?>%</div>
    <div class="cc-prog"><div style="width:<?=$pct?>%;height:100%;background:<?=$col?>;border-radius:20px;"></div></div>
    <?php if(!$c['is_active']): ?>
    <div style="margin-bottom:8px;"><span style="background:#ffebee;color:#c62828;font-size:11px;padding:2px 8px;border-radius:20px;">⛔ ปิดการใช้งาน</span></div>
    <?php endif; ?>
    <!-- ปุ่มหลัก -->
    <a href="course_manager.php?cid=<?=$c['id']?>&tab=lessons" class="cc-btn cc-enter" style="display:block;margin-bottom:8px;">
      ▶ เข้าจัดการหลักสูตรนี้
    </a>
    <div class="cc-actions">
      <a href="?edit=<?=$c['id']?>" class="cc-btn cc-edit">✏️ แก้ชื่อ</a>
      <a href="?toggle=<?=$c['id']?>" class="cc-btn cc-tog"><?=$c['is_active']?'⛔ ปิด':'✅ เปิด'?></a>
      <a href="?delete=<?=$c['id']?>" class="cc-btn cc-del"
         onclick="return confirm('ลบ \"<?=addslashes($c['title'])?>\"?\nข้อมูลทั้งหมดจะหายไป')">🗑 ลบ</a>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php if (!$courses): ?>
<div style="text-align:center;padding:48px;color:#aaa;">
  <div style="font-size:52px;margin-bottom:8px;">📭</div>
  <div>ยังไม่มีหลักสูตร — เพิ่มได้ด้านบน</div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
