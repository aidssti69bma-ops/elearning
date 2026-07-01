<?php
// admin/course_manager.php — จัดการหลักสูตรที่เลือก (บทเรียน+ข้อสอบ+dashboard รวมหน้าเดียว)
require_once '../includes/config.php';
requireAdmin();

$cid    = (int)($_GET['cid'] ?? 0);
if (!$cid) { header("Location: index.php"); exit; }
$course = $conn->query("SELECT * FROM courses WHERE id=$cid")->fetch_assoc();
if (!$course) { header("Location: index.php"); exit; }

// ===== AJAX reorder =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_lessons'])) {
    foreach ($_POST['ids']??[] as $i=>$id)
        $conn->query("UPDATE lessons SET sort_order=".($i+1)." WHERE id=".(int)$id." AND course_id=$cid");
    echo json_encode(['ok'=>true]); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reorder_questions'])) {
    foreach ($_POST['ids']??[] as $i=>$id)
        $conn->query("UPDATE questions SET sort_order=".($i+1)." WHERE id=".(int)$id." AND course_id=$cid");
    echo json_encode(['ok'=>true]); exit;
}

// ===== AJAX delete actions (dashboard) =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['del_action'])) {
    $act = $_POST['del_action'];
    $uid = (int)($_POST['target_uid']??0);
    if ($uid && $conn->query("SELECT role FROM users WHERE id=$uid")->fetch_assoc()['role']==='user') {
        if ($act==='del_pre')    { $conn->query("DELETE qa FROM quiz_answers qa JOIN quiz_results qr ON qa.result_id=qr.id WHERE qr.user_id=$uid AND qr.course_id=$cid AND qr.quiz_type='pre'"); $conn->query("DELETE FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='pre'"); }
        if ($act==='del_post')   { $conn->query("DELETE qa FROM quiz_answers qa JOIN quiz_results qr ON qa.result_id=qr.id WHERE qr.user_id=$uid AND qr.course_id=$cid AND qr.quiz_type='post'"); $conn->query("DELETE FROM quiz_results WHERE user_id=$uid AND course_id=$cid AND quiz_type='post'"); $conn->query("DELETE FROM reward_claims WHERE user_id=$uid AND course_id=$cid"); }
        if ($act==='del_reward') { $conn->query("DELETE FROM reward_claims WHERE user_id=$uid AND course_id=$cid"); }
        if ($act==='del_all')    { $conn->query("DELETE qa FROM quiz_answers qa JOIN quiz_results qr ON qa.result_id=qr.id WHERE qr.user_id=$uid AND qr.course_id=$cid"); $conn->query("DELETE FROM quiz_results WHERE user_id=$uid AND course_id=$cid"); $conn->query("DELETE FROM reward_claims WHERE user_id=$uid AND course_id=$cid"); }
        if ($act==='del_user')   { $conn->query("DELETE qa FROM quiz_answers qa JOIN quiz_results qr ON qa.result_id=qr.id WHERE qr.user_id=$uid"); $conn->query("DELETE FROM quiz_results WHERE user_id=$uid"); $conn->query("DELETE FROM reward_claims WHERE user_id=$uid"); $conn->query("DELETE FROM users WHERE id=$uid AND role='user'"); }
    }
    header("Location: course_manager.php?cid=$cid&tab=dashboard&msg=done"); exit;
}

// ===== lesson actions =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_lesson'])) {
    $type  = in_array($_POST['type'],['youtube','pdf','info'])?$_POST['type']:'youtube';
    $title = trim($_POST['title']);
    $url   = trim($_POST['content_url']);
    // extract YouTube ID ถ้าเป็นลิงก์เต็ม
    if ($type==='youtube') {
        if (preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{11})/', $url, $m)) $url = trim($m[1]);
        else $url = trim($url);
    }
    $text = trim($_POST['content_text']);
    if ($title) {
        $ord = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0)+1 m FROM lessons WHERE course_id=$cid")->fetch_assoc()['m'];
        $stmt=$conn->prepare("INSERT INTO lessons (course_id,sort_order,title,type,content_url,content_text) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('iissss',$cid,$ord,$title,$type,$url,$text);
        $stmt->execute();
    }
    header("Location: course_manager.php?cid=$cid&tab=lessons&msg=lesson_added"); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_lesson'])) {
    $id=$_POST['lid']; $type=in_array($_POST['type'],['youtube','pdf','info'])?$_POST['type']:'youtube';
    $title=trim($_POST['title']); $url=trim($_POST['content_url']);
    if ($type==='youtube') {
        if (preg_match('/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{11})/', $url, $m)) $url=trim($m[1]);
        else $url=trim($url);
    }
    $text=trim($_POST['content_text']); $ord=(int)$_POST['sort_order'];
    $stmt=$conn->prepare("UPDATE lessons SET sort_order=?,title=?,type=?,content_url=?,content_text=? WHERE id=? AND course_id=?");
    $stmt->bind_param('issssi i',$ord,$title,$type,$url,$text,$id,$cid);
    $stmt->bind_param('issssii',$ord,$title,$type,$url,$text,$id,$cid);
    $stmt->execute();
    header("Location: course_manager.php?cid=$cid&tab=lessons&msg=lesson_edited"); exit;
}
if (isset($_GET['del_lesson'])) {
    $conn->query("DELETE FROM lessons WHERE id=".(int)$_GET['del_lesson']." AND course_id=$cid");
    header("Location: course_manager.php?cid=$cid&tab=lessons"); exit;
}

// ===== question actions =====
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_question'])) {
    $q=trim($_POST['question']); $ans=in_array($_POST['correct_ans'],['true','false'])?$_POST['correct_ans']:'true';
    if ($q) {
        $ord=(int)$conn->query("SELECT COALESCE(MAX(sort_order),0)+1 m FROM questions WHERE course_id=$cid")->fetch_assoc()['m'];
        $stmt=$conn->prepare("INSERT INTO questions (course_id,quiz_type,sort_order,question,correct_ans) VALUES (?,'shared',?,?,?)");
        $stmt->bind_param('iiss',$cid,$ord,$q,$ans);
        $stmt->execute();
    }
    header("Location: course_manager.php?cid=$cid&tab=quiz&msg=q_added"); exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['edit_question'])) {
    $id=(int)$_POST['qid']; $q=trim($_POST['question']); $ans=in_array($_POST['correct_ans'],['true','false'])?$_POST['correct_ans']:'true'; $ord=(int)$_POST['sort_order'];
    $stmt=$conn->prepare("UPDATE questions SET sort_order=?,question=?,correct_ans=? WHERE id=? AND course_id=?");
    $stmt->bind_param('issii',$ord,$q,$ans,$id,$cid);
    $stmt->execute();
    header("Location: course_manager.php?cid=$cid&tab=quiz&msg=q_edited"); exit;
}
if (isset($_GET['del_question'])) {
    $conn->query("DELETE FROM questions WHERE id=".(int)$_GET['del_question']." AND course_id=$cid");
    header("Location: course_manager.php?cid=$cid&tab=quiz"); exit;
}

// ===== ดึงข้อมูล =====
$tab      = $_GET['tab'] ?? 'lessons';
$lessons  = $conn->query("SELECT * FROM lessons WHERE course_id=$cid AND is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$questions= $conn->query("SELECT * FROM questions WHERE course_id=$cid AND is_active=1 ORDER BY sort_order")->fetch_all(MYSQLI_ASSOC);
$editL    = isset($_GET['edit_lesson'])   ? $conn->query("SELECT * FROM lessons   WHERE id=".(int)$_GET['edit_lesson']  ." AND course_id=$cid")->fetch_assoc() : null;
$editQ    = isset($_GET['edit_q'])        ? $conn->query("SELECT * FROM questions WHERE id=".(int)$_GET['edit_q']       ." AND course_id=$cid")->fetch_assoc() : null;
if ($editL) $tab='lessons';
if ($editQ) $tab='quiz';

// dashboard stats
$totalUsers  = (int)$conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()['c'];
$didPre      = (int)$conn->query("SELECT COUNT(DISTINCT user_id) c FROM quiz_results WHERE course_id=$cid AND quiz_type='pre'")->fetch_assoc()['c'];
$didPost     = (int)$conn->query("SELECT COUNT(DISTINCT user_id) c FROM quiz_results WHERE course_id=$cid AND quiz_type='post'")->fetch_assoc()['c'];
$passedPost  = (int)$conn->query("SELECT COUNT(DISTINCT user_id) c FROM quiz_results WHERE course_id=$cid AND quiz_type='post' AND passed=1")->fetch_assoc()['c'];
$claimed     = (int)$conn->query("SELECT COUNT(*) c FROM reward_claims WHERE course_id=$cid")->fetch_assoc()['c'];
$scoreStats  = $conn->query("SELECT ROUND(AVG(score/total*100),1) avg_pct, MAX(score/total*100) max_pct, MIN(score/total*100) min_pct, COUNT(*) cnt FROM quiz_results qr JOIN users u ON u.id=qr.user_id WHERE qr.course_id=$cid AND qr.quiz_type='post' AND u.role='user'")->fetch_assoc();
$distRows    = $conn->query("SELECT FLOOR(score/total*10)*10 AS band,COUNT(*) cnt FROM quiz_results qr JOIN users u ON u.id=qr.user_id WHERE qr.course_id=$cid AND qr.quiz_type='post' AND u.role='user' GROUP BY band ORDER BY band")->fetch_all(MYSQLI_ASSOC);
$distMap=[]; foreach($distRows as $r) $distMap[(int)$r['band']]=(int)$r['cnt'];

// per-question stats
$qStats=[];
foreach ($questions as $q) {
    $qid=$q['id'];
    $r=$conn->query("SELECT COUNT(*) total, SUM(user_ans='true') cnt_true, SUM(user_ans='false') cnt_false, SUM(user_ans='unsure') cnt_unsure, SUM(is_correct) cnt_correct FROM quiz_answers qa JOIN quiz_results qr ON qr.id=qa.result_id JOIN users u ON u.id=qr.user_id WHERE qa.question_id=$qid AND qr.course_id=$cid AND u.role='user'")->fetch_assoc();
    $tot=(int)$r['total'];
    $qStats[$qid]=['q'=>$q['question'],'no'=>$q['sort_order'],'ca'=>$q['correct_ans'],'total'=>$tot,
        'pct_correct'=>$tot>0?round($r['cnt_correct']/$tot*100):0,
        'pct_true'=>$tot>0?round($r['cnt_true']/$tot*100):0,
        'pct_false'=>$tot>0?round($r['cnt_false']/$tot*100):0,
        'pct_unsure'=>$tot>0?round($r['cnt_unsure']/$tot*100):0,
        'cnt_true'=>(int)$r['cnt_true'],'cnt_false'=>(int)$r['cnt_false'],'cnt_unsure'=>(int)$r['cnt_unsure']];
}
$hardestQ=array_values($qStats); usort($hardestQ,fn($a,$b)=>$a['pct_correct']-$b['pct_correct']);

// users list
$users=$conn->query("SELECT u.id,u.name,u.position,u.department,u.phone,
    (SELECT score FROM quiz_results WHERE user_id=u.id AND course_id=$cid AND quiz_type='pre' ORDER BY id LIMIT 1) pre_score,
    (SELECT total FROM quiz_results WHERE user_id=u.id AND course_id=$cid AND quiz_type='pre' ORDER BY id LIMIT 1) pre_total,
    (SELECT score FROM quiz_results WHERE user_id=u.id AND course_id=$cid AND quiz_type='post' AND passed=1 LIMIT 1) post_score,
    (SELECT total FROM quiz_results WHERE user_id=u.id AND course_id=$cid AND quiz_type='post' AND passed=1 LIMIT 1) post_total,
    (SELECT MAX(score/total*100) FROM quiz_results WHERE user_id=u.id AND course_id=$cid AND quiz_type='post') best_pct,
    (SELECT COUNT(*) FROM quiz_results WHERE user_id=u.id AND course_id=$cid AND quiz_type='post') post_count,
    (SELECT id FROM reward_claims WHERE user_id=u.id AND course_id=$cid LIMIT 1) claimed
    FROM users u WHERE u.role='user' ORDER BY u.name")->fetch_all(MYSQLI_ASSOC);

$flashMsg = match($_GET['msg']??'') {
    'lesson_added'=>'<div class="alert alert-success">✅ เพิ่มบทเรียนแล้ว</div>',
    'lesson_edited'=>'<div class="alert alert-success">✅ แก้ไขบทเรียนแล้ว</div>',
    'q_added'=>'<div class="alert alert-success">✅ เพิ่มข้อสอบแล้ว</div>',
    'q_edited'=>'<div class="alert alert-success">✅ แก้ไขข้อสอบแล้ว</div>',
    'done'=>'<div class="alert alert-success">✅ ดำเนินการแล้ว</div>',
    default=>''
};

$pageTitle = $course['title'];
require_once '../includes/header.php';
require_once '../includes/admin_nav.php';
?>

<style>
.cm-back { display:inline-flex;align-items:center;gap:6px;color:#2e7d32;text-decoration:none;font-size:13px;font-weight:600;margin-bottom:12px; }
.cm-back:hover { text-decoration:underline; }

/* course header strip */
.cm-header { border-radius:12px; padding:18px 22px; margin-bottom:16px; color:#fff;
  display:flex; align-items:center; gap:14px; flex-wrap:wrap; }
.cm-header-title { font-size:19px; font-weight:700; }
.cm-header-sub   { font-size:12px; opacity:.85; margin-top:2px; }

/* tab nav */
.cm-tabs { display:flex; gap:0; margin-bottom:0; border-bottom:2px solid #c8e6c9; }
.cm-tab  { padding:10px 20px; font-size:14px; font-weight:600; text-decoration:none;
  color:#888; border-bottom:3px solid transparent; margin-bottom:-2px; }
.cm-tab:hover { color:#2e7d32; }
.cm-tab.active { color:#2e7d32; border-bottom-color:#2e7d32; }
.cm-tab-content { background:#fff; border-radius:0 0 10px 10px; padding:20px;
  box-shadow:0 2px 8px rgba(0,0,0,.07); }

/* lesson/question list */
.cml-item { display:flex; align-items:center; gap:8px; padding:10px 12px;
  border-bottom:1px solid #e8f5e9; background:#fff; }
.cml-item:last-child { border-bottom:none; }
.cml-item:hover { background:#f9fdf9; }
.cml-drag { color:#ccc; font-size:18px; cursor:grab; user-select:none; }
.cml-num  { width:24px; height:24px; border-radius:50%; background:#e8f5e9; color:#1b5e20;
  font-size:11px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.cml-text { flex:1; font-size:13px; font-weight:500; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.cml-sub  { font-size:11px; color:#aaa; }
.cml-acts { display:flex; gap:4px; flex-shrink:0; }
.cml-btn  { padding:4px 9px; border-radius:5px; text-decoration:none; font-size:12px; cursor:pointer; border:none; font-family:inherit; }
.cml-edit { background:#fff8e1; color:#e65100; }
.cml-del  { background:#ffebee; color:#c62828; }
.cml-item.active-edit { background:#f1f8e9; border-left:3px solid #2e7d32; }
.cml-item.drag-over   { border-top:2px solid #2e7d32; }
.cml-item.dragging    { opacity:.4; }

/* form */
.cm-form { background:#f9fdf9; border:1.5px solid #c8e6c9; border-radius:8px; padding:16px; margin-bottom:16px; }
.cm-form.editing { background:#fffde7; border-color:#f9a825; }
.cm-form-title { font-size:12px; font-weight:700; color:#2e7d32; text-transform:uppercase; letter-spacing:.05em; margin-bottom:12px; }
.cm-form.editing .cm-form-title { color:#e65100; }
.fg { margin-bottom:12px; }
.fg label { display:block; font-size:12px; font-weight:600; color:#555; margin-bottom:4px; }
.fg input,.fg textarea,.fg select { width:100%;padding:8px 10px;border:1.5px solid #c8e6c9;border-radius:6px;font-size:13px;font-family:inherit;background:#fff; }
.fg input:focus,.fg textarea:focus,.fg select:focus { outline:none;border-color:#2e7d32; }
.type-btns { display:flex; gap:6px; }
.type-btn label { cursor:pointer; }
.type-btn input { display:none; }
.type-btn span { display:block;padding:6px 12px;border-radius:6px;font-size:12px;border:1.5px solid #c8e6c9;background:#fff; }
.type-btn input:checked+span { background:#2e7d32;color:#fff;border-color:#2e7d32;font-weight:600; }
.ans-btns { display:flex;gap:8px; }
.ans-btn { flex:1; }
.ans-btn label { cursor:pointer;display:block; }
.ans-btn input { display:none; }
.ans-btn span { display:block;padding:9px;text-align:center;border-radius:8px;font-size:13px;border:1.5px solid #ddd;background:#fff; }
.ans-btn.true input:checked+span  { background:#e8f5e9;border-color:#2e7d32;color:#1b5e20;font-weight:600; }
.ans-btn.false input:checked+span { background:#ffebee;border-color:#c62828;color:#b71c1c;font-weight:600; }

/* stats */
.db-mini-cards { display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:10px;margin-bottom:16px; }
.db-mini-card  { background:#f9fdf9;border-radius:8px;padding:12px;text-align:center;border-top:3px solid #ccc; }

/* user table */
.table-wrap { overflow-x:auto; }
table { width:100%;border-collapse:collapse;font-size:13px; }
th,td { padding:9px 11px;border-bottom:1px solid #e8f5e9;text-align:left; }
th { background:#e8f5e9;font-weight:600;color:#1b5e20; }

/* save order button */
.save-ord { display:none;padding:8px 0; }
</style>

<a href="index.php" class="cm-back">← กลับหน้าหลัก</a>

<!-- Course Header -->
<?php
$colors=['#1b5e20','#1565c0','#4a148c','#bf360c','#004d40','#880e4f','#37474f','#e65100'];
$col=$colors[($cid-1)%count($colors)];
?>
<div class="cm-header" style="background:linear-gradient(135deg,<?=$col?>,<?=$col?>cc);">
  <div style="font-size:40px;"><?=$course['thumbnail']?></div>
  <div style="flex:1;">
    <div class="cm-header-title"><?=htmlspecialchars($course['title'])?></div>
    <div class="cm-header-sub">
      📚 <?=count($lessons)?> บทเรียน &nbsp;|&nbsp;
      📝 <?=count($questions)?> ข้อสอบ &nbsp;|&nbsp;
      ✅ ผ่านเกณฑ์ <?=$passedPost?>/<?=$totalUsers?> คน &nbsp;|&nbsp;
      เกณฑ์ <?=$course['pass_score']?>%
    </div>
  </div>
  <a href="quiz_admin.php?course_id=<?=$cid?>" style="background:rgba(255,255,255,.2);color:#fff;padding:8px 16px;border-radius:8px;text-decoration:none;font-size:13px;border:1px solid rgba(255,255,255,.3);">
    🧪 ทดลองทำ
  </a>
</div>

<?= $flashMsg ?>

<!-- Tabs -->
<div class="cm-tabs">
  <a href="?cid=<?=$cid?>&tab=lessons"   class="cm-tab <?=$tab==='lessons'  ?'active':''?>">📚 บทเรียน (<?=count($lessons)?>)</a>
  <a href="?cid=<?=$cid?>&tab=quiz"      class="cm-tab <?=$tab==='quiz'     ?'active':''?>">📝 ข้อสอบ (<?=count($questions)?>)</a>
  <a href="?cid=<?=$cid?>&tab=dashboard" class="cm-tab <?=$tab==='dashboard'?'active':''?>">📈 รายงาน</a>
</div>

<div class="cm-tab-content">

<?php // ============ TAB: LESSONS ============
if ($tab==='lessons'): ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">

  <!-- ฟอร์ม -->
  <div>
    <div class="cm-form <?=$editL?'editing':''?>">
      <div class="cm-form-title"><?=$editL?'✏️ แก้ไขบทเรียน':'➕ เพิ่มบทเรียน'?></div>
      <form method="post" id="lessonForm">
        <?php if ($editL): ?><input type="hidden" name="lid" value="<?=$editL['id']?>"><input type="hidden" name="sort_order" value="<?=$editL['sort_order']?>"><?php endif; ?>

        <div class="fg">
          <label>ชื่อบทเรียน</label>
          <input type="text" name="title" required value="<?=htmlspecialchars($editL['title']??'')?>" placeholder="ชื่อบทเรียน">
        </div>

        <div class="fg">
          <label>ประเภท</label>
          <div class="type-btns">
            <?php foreach(['youtube'=>'▶️ YouTube','pdf'=>'📄 PDF','info'=>'📝 ข้อความ'] as $t=>$lbl): ?>
            <div class="type-btn">
              <label>
                <input type="radio" name="type" value="<?=$t?>" onchange="switchType('<?=$t?>')"
                  <?=($editL['type']??'youtube')===$t?'checked':''?>>
                <span><?=$lbl?></span>
              </label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div id="yt-field" class="fg" style="<?=($editL['type']??'youtube')==='youtube'?'':'display:none'?>">
          <label>ลิงก์ YouTube (วางลิงก์เต็มได้เลย)</label>
          <input type="text" name="content_url" id="ytInput"
            value="<?=htmlspecialchars($editL['content_url']??'')?>"
            placeholder="https://youtube.com/watch?v=xxxxx หรือ Video ID"
            oninput="previewYT(this.value)">
          <div id="yt-preview" style="<?=($editL['content_url']??'')?'':'display:none'?>;margin-top:8px;border-radius:6px;overflow:hidden;aspect-ratio:16/9;background:#000;">
            <iframe id="yt-iframe" style="width:100%;height:100%;border:0;"
              src="<?=($editL['type']??'')!=='youtube'||!($editL['content_url']??'')?'':'https://www.youtube.com/embed/'.htmlspecialchars(trim($editL['content_url'])).'?rel=0'?>"
              allowfullscreen></iframe>
          </div>
        </div>

        <div id="pdf-field" class="fg" style="<?=($editL['type']??'')==='pdf'?'':'display:none'?>">
          <label>Path ไฟล์ PDF (เช่น uploads/doc.pdf)</label>
          <input type="text" name="content_url" value="<?=htmlspecialchars($editL['type']==='pdf'?($editL['content_url']??''):'')?>" placeholder="uploads/เอกสาร.pdf">
        </div>

        <div id="info-field" class="fg" style="<?=($editL['type']??'')==='info'?'':'display:none'?>">
          <label>เนื้อหา</label>
          <textarea name="content_text" rows="4"><?=htmlspecialchars($editL['content_text']??'')?></textarea>
        </div>

        <div style="display:flex;gap:8px;">
          <button type="submit" name="<?=$editL?'edit_lesson':'add_lesson'?>" class="cml-btn" style="background:#2e7d32;color:#fff;padding:8px 18px;font-size:13px;">
            <?=$editL?'💾 บันทึก':'+ เพิ่ม'?>
          </button>
          <?php if ($editL): ?>
          <a href="?cid=<?=$cid?>&tab=lessons" class="cml-btn" style="background:#eee;color:#555;padding:8px 14px;">ยกเลิก</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- รายการบทเรียน -->
  <div>
    <div style="font-size:13px;font-weight:700;color:#2e7d32;margin-bottom:8px;">
      รายการ (<?=count($lessons)?> บท) — ลากเพื่อเรียงลำดับ
    </div>
    <?php if ($lessons): ?>
    <div style="border:1px solid #e8f5e9;border-radius:8px;overflow:hidden;">
      <ul id="lessonList" style="list-style:none;margin:0;padding:0;">
        <?php foreach ($lessons as $i=>$l): ?>
        <li class="cml-item <?=$editL&&$editL['id']==$l['id']?'active-edit':''?>" data-id="<?=$l['id']?>">
          <span class="cml-drag">⠿</span>
          <span class="cml-num"><?=$i+1?></span>
          <span style="font-size:16px;"><?=['youtube'=>'▶️','pdf'=>'📄','info'=>'📝'][$l['type']]?></span>
          <div style="flex:1;min-width:0;">
            <div class="cml-text"><?=htmlspecialchars($l['title'])?></div>
            <?php if ($l['type']==='youtube' && $l['content_url']): ?>
            <div class="cml-sub"><?=htmlspecialchars(mb_strimwidth($l['content_url'],0,28,'...'))?></div>
            <?php endif; ?>
          </div>
          <div class="cml-acts">
            <a href="?cid=<?=$cid?>&edit_lesson=<?=$l['id']?>" class="cml-btn cml-edit">✏️</a>
            <a href="?cid=<?=$cid?>&del_lesson=<?=$l['id']?>" class="cml-btn cml-del"
               onclick="return confirm('ลบบทเรียน \"<?=addslashes($l['title'])?>\"?')">🗑</a>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="save-ord" id="lesson-save">
      <button onclick="saveOrder('lessons')" class="cml-btn" style="background:#2e7d32;color:#fff;padding:7px 16px;font-size:13px;">💾 บันทึกลำดับ</button>
    </div>
    <?php else: ?>
    <div style="padding:20px;text-align:center;color:#aaa;border:1px dashed #c8e6c9;border-radius:8px;">ยังไม่มีบทเรียน</div>
    <?php endif; ?>
  </div>
</div>

<?php // ============ TAB: QUIZ ============
elseif ($tab==='quiz'): ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;">
  <!-- ฟอร์ม -->
  <div>
    <div class="alert alert-info" style="font-size:12px;margin-bottom:12px;">
      ℹ️ ข้อสอบชุดเดียวนี้ใช้ทั้ง <strong>Pre-test</strong> และ <strong>Post-test</strong>
    </div>
    <div class="cm-form <?=$editQ?'editing':''?>">
      <div class="cm-form-title"><?=$editQ?'✏️ แก้ไขข้อสอบ':'➕ เพิ่มข้อสอบ'?></div>
      <form method="post">
        <?php if ($editQ): ?><input type="hidden" name="qid" value="<?=$editQ['id']?>"><input type="hidden" name="sort_order" value="<?=$editQ['sort_order']?>"><?php endif; ?>
        <div class="fg">
          <label>ข้อความคำถาม</label>
          <textarea name="question" rows="3" required placeholder="พิมพ์ข้อความที่ให้ผู้เรียนตัดสินว่าถูกหรือผิด..."><?=htmlspecialchars($editQ?$editQ['question']:'')?></textarea>
        </div>
        <div class="fg">
          <label>คำตอบที่ถูกต้อง</label>
          <div class="ans-btns">
            <div class="ans-btn true">
              <label><input type="radio" name="correct_ans" value="true" <?=(($editQ?$editQ['correct_ans']:'true')==='true')?'checked':''?>>
              <span>✅ ข้อความนี้ <strong>ถูก</strong></span></label>
            </div>
            <div class="ans-btn false">
              <label><input type="radio" name="correct_ans" value="false" <?=(($editQ?$editQ['correct_ans']:'')==='false')?'checked':''?>>
              <span>❌ ข้อความนี้ <strong>ผิด</strong></span></label>
            </div>
          </div>
        </div>
        <div style="display:flex;gap:8px;">
          <button type="submit" name="<?=$editQ?'edit_question':'add_question'?>" class="cml-btn" style="background:#2e7d32;color:#fff;padding:8px 18px;font-size:13px;">
            <?=$editQ?'💾 บันทึก':'+ เพิ่ม'?>
          </button>
          <?php if ($editQ): ?>
          <a href="?cid=<?=$cid?>&tab=quiz" class="cml-btn" style="background:#eee;color:#555;padding:8px 14px;">ยกเลิก</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

  <!-- รายการข้อสอบ -->
  <div>
    <div style="font-size:13px;font-weight:700;color:#2e7d32;margin-bottom:8px;">
      รายการ (<?=count($questions)?> ข้อ) — ลากเพื่อเรียงลำดับ
    </div>
    <?php if ($questions): ?>
    <div style="border:1px solid #e8f5e9;border-radius:8px;overflow:hidden;">
      <ul id="qList" style="list-style:none;margin:0;padding:0;">
        <?php foreach ($questions as $i=>$q): ?>
        <li class="cml-item <?=$editQ&&$editQ['id']==$q['id']?'active-edit':''?>" data-id="<?=$q['id']?>">
          <span class="cml-drag">⠿</span>
          <span class="cml-num"><?=$i+1?></span>
          <div style="flex:1;min-width:0;">
            <div class="cml-text"><?=htmlspecialchars(mb_strimwidth($q['question'],0,55,'...'))?></div>
            <span style="font-size:11px;padding:1px 8px;border-radius:20px;<?=$q['correct_ans']==='true'?'background:#e8f5e9;color:#1b5e20':'background:#ffebee;color:#b71c1c'?>">
              <?=$q['correct_ans']==='true'?'✅ ถูก':'❌ ผิด'?>
            </span>
          </div>
          <div class="cml-acts">
            <a href="?cid=<?=$cid?>&edit_q=<?=$q['id']?>" class="cml-btn cml-edit">✏️</a>
            <a href="?cid=<?=$cid?>&del_question=<?=$q['id']?>" class="cml-btn cml-del"
               onclick="return confirm('ลบข้อนี้?')">🗑</a>
          </div>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="save-ord" id="q-save">
      <button onclick="saveOrder('questions')" class="cml-btn" style="background:#2e7d32;color:#fff;padding:7px 16px;font-size:13px;">💾 บันทึกลำดับ</button>
    </div>
    <?php else: ?>
    <div style="padding:20px;text-align:center;color:#aaa;border:1px dashed #c8e6c9;border-radius:8px;">ยังไม่มีข้อสอบ</div>
    <?php endif; ?>
  </div>
</div>

<?php // ============ TAB: DASHBOARD ============
elseif ($tab==='dashboard'): ?>

<!-- Summary cards -->
<div class="db-mini-cards">
  <?php
  $cards=[['👥','ผู้สมัคร',$totalUsers,'#2e7d32'],['📋','ทำ Pre-test',$didPre,'#1565c0'],
          ['📝','ทำ Post-test',$didPost,'#6a1b9a'],['✅','ผ่านเกณฑ์',$passedPost,'#00695c'],['🏆','รับรางวัล',$claimed,'#e65100']];
  foreach($cards as [$ico,$lbl,$val,$col]):?>
  <div class="db-mini-card" style="border-top-color:<?=$col?>;">
    <div style="font-size:20px;"><?=$ico?></div>
    <div style="font-size:26px;font-weight:700;color:<?=$col?>;line-height:1.1;"><?=$val?></div>
    <div style="font-size:11px;color:#888;"><?=$lbl?></div>
  </div>
  <?php endforeach;?>
</div>

<!-- Export -->
<div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
  <a href="?cid=<?=$cid?>&tab=dashboard&export=1" class="cml-btn" style="background:#2e7d32;color:#fff;padding:8px 16px;font-size:13px;">⬇ Export CSV</a>
</div>

<!-- Score stats + distribution -->
<?php if ($scoreStats['cnt'] > 0): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px;">
  <div style="background:#f9fdf9;border-radius:8px;padding:16px;border:1px solid #e8f5e9;">
    <div style="font-size:13px;font-weight:700;color:#2e7d32;margin-bottom:12px;">📊 สถิติคะแนน Post-test</div>
    <?php foreach([['เฉลี่ย',round((float)$scoreStats['avg_pct'],1).'%','#2e7d32'],['สูงสุด',round((float)$scoreStats['max_pct']).'%','#1565c0'],['ต่ำสุด',round((float)$scoreStats['min_pct']).'%','#c62828'],['ส่งทั้งหมด',$scoreStats['cnt'].' ครั้ง','#6a1b9a']] as [$l,$v,$c]): ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #e8f5e9;font-size:13px;">
      <span style="color:#555;"><?=$l?></span><strong style="color:<?=$c?>"><?=$v?></strong>
    </div>
    <?php endforeach;?>
  </div>
  <div style="background:#f9fdf9;border-radius:8px;padding:16px;border:1px solid #e8f5e9;">
    <div style="font-size:13px;font-weight:700;color:#2e7d32;margin-bottom:10px;">การกระจายคะแนน</div>
    <?php $mx=max(array_values($distMap)?:[1]);
    foreach([0,10,20,30,40,50,60,70,80,90] as $b):
      $c2=$distMap[$b]??0; $w=$mx>0?round($c2/$mx*100):0;
      $col2=$b>=80?'#2e7d32':($b>=60?'#f57f17':'#c62828');?>
    <div style="display:flex;align-items:center;gap:5px;margin-bottom:5px;font-size:11px;">
      <span style="width:46px;text-align:right;color:#555;"><?=$b?>-<?=$b+9?>%</span>
      <div style="flex:1;background:#eee;border-radius:3px;height:12px;overflow:hidden;">
        <div style="width:<?=$w?>%;background:<?=$col2?>;height:100%;border-radius:3px;"></div></div>
      <span style="width:18px;font-weight:700;color:<?=$col2?>"><?=$c2?></span>
    </div>
    <?php endforeach;?>
  </div>
</div>
<?php endif; ?>

<!-- Per-question analysis -->
<?php if ($qStats): ?>
<div style="font-size:13px;font-weight:700;color:#2e7d32;margin-bottom:10px;">🎯 วิเคราะห์รายข้อ</div>
<?php foreach ($qStats as $qid=>$qs):
  $pc=$qs['pct_correct'];
  $bc=$pc>=80?'#2e7d32':($pc>=50?'#f57f17':'#c62828');
  $bg=$pc>=80?'#e8f5e9':($pc>=50?'#fff8e1':'#ffebee');
?>
<div style="margin-bottom:12px;padding:12px 14px;background:<?=$bg?>;border-radius:8px;border-left:4px solid <?=$bc?>;">
  <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;">
    <span style="background:<?=$bc?>;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0;"><?=$qs['no']?></span>
    <div style="flex:1;">
      <div style="font-size:13px;font-weight:600;color:#333;"><?=htmlspecialchars($qs['q'])?></div>
      <div style="font-size:11px;color:#666;margin-top:2px;">เฉลย: <strong><?=$qs['ca']==='true'?'✅ ถูก':'❌ ผิด'?></strong> | ตอบทั้งหมด <?=$qs['total']?> ครั้ง</div>
    </div>
    <div style="text-align:right;flex-shrink:0;">
      <div style="font-size:24px;font-weight:700;color:<?=$bc?>;line-height:1;"><?=$pc?>%</div>
      <div style="font-size:10px;color:#888;">ตอบถูก</div>
    </div>
  </div>
  <?php if ($qs['total']>0): ?>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;">
    <?php foreach([['✅ ถูก',$qs['cnt_true'],$qs['pct_true'],$qs['ca']==='true'?'#2e7d32':'#888'],['❌ ผิด',$qs['cnt_false'],$qs['pct_false'],$qs['ca']==='false'?'#2e7d32':'#888'],['🤔 ไม่แน่ใจ',$qs['cnt_unsure'],$qs['pct_unsure'],'#888']] as [$lbl,$cnt,$pct,$c3]): ?>
    <div style="background:#fff;border-radius:6px;padding:7px 9px;border:1px solid #ddd;">
      <div style="font-size:11px;color:#666;margin-bottom:3px;"><?=$lbl?></div>
      <div style="background:#f0f0f0;border-radius:20px;height:7px;overflow:hidden;margin-bottom:3px;">
        <div style="width:<?=$pct?>%;background:<?=$c3?>;height:100%;border-radius:20px;"></div></div>
      <div style="font-size:12px;font-weight:700;color:<?=$c3?>"><?=$pct?>% <span style="font-size:10px;font-weight:400;color:#aaa;">(<?=$cnt?>)</span></div>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>
</div>
<?php endforeach;?>

<?php if (count($hardestQ)>0 && ($hardestQ[0]['total']??0)>0): ?>
<div style="margin-top:4px;padding:12px 14px;background:#fff3e0;border-radius:8px;border-left:4px solid #f57f17;">
  <div style="font-size:13px;font-weight:700;color:#e65100;margin-bottom:8px;">⚠️ ข้อที่คนตอบถูกน้อยสุด (ควรเน้นสอน)</div>
  <?php foreach(array_slice($hardestQ,0,3) as $i2=>$hq): if(!$hq['total']) continue; ?>
  <div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #ffe0b2;font-size:13px;">
    <span style="background:#f57f17;color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;flex-shrink:0;"><?=$i2+1?></span>
    <span style="flex:1;">ข้อ <?=$hq['no']?>: <?=htmlspecialchars(mb_strimwidth($hq['q'],0,55,'...'))?></span>
    <span style="font-weight:700;color:#c62828;"><?=$hq['pct_correct']?>%</span>
  </div>
  <?php endforeach;?>
</div>
<?php endif;?>
<?php endif;?>

<!-- User list -->
<div style="font-size:13px;font-weight:700;color:#2e7d32;margin:16px 0 10px;">👥 รายชื่อผู้เรียน (<?=count($users)?> คน)</div>
<div class="table-wrap">
<table>
  <thead><tr><th>#</th><th>ชื่อ</th><th>ตำแหน่ง/กลุ่มงาน</th><th style="text-align:center">Pre</th><th style="text-align:center">Post</th><th style="text-align:center">ครั้ง</th><th style="text-align:center">สถานะ</th><th style="text-align:center">รางวัล</th><th style="text-align:center">จัดการ</th></tr></thead>
  <tbody>
  <?php foreach($users as $i=>$u):
    $prePct  = ($u['pre_score']!==null  && $u['pre_total'])  ? round($u['pre_score']/$u['pre_total']*100)   : null;
    $postPct = ($u['post_score']!==null && $u['post_total']) ? round($u['post_score']/$u['post_total']*100) : null;
    $bestPct = $u['best_pct']!==null ? round((float)$u['best_pct']) : null;
  ?>
  <tr>
    <td><?=$i+1?></td>
    <td style="font-weight:500;white-space:nowrap;font-size:13px;"><?=htmlspecialchars($u['name'])?></td>
    <td style="font-size:12px;color:#666;"><?=htmlspecialchars(($u['position']??'').($u['department']?(' / '.$u['department']):''))?></td>
    <td style="text-align:center;font-size:12px;"><?=$prePct!==null?"<strong>{$prePct}%</strong>":'<span style="color:#ddd">—</span>'?></td>
    <td style="text-align:center;font-size:12px;">
      <?php if($postPct!==null): ?><strong style="color:#2e7d32"><?=$postPct?>%</strong>
      <?php elseif($bestPct!==null): ?><strong style="color:#c62828"><?=$bestPct?>%</strong>
      <?php else: ?><span style="color:#ddd">—</span><?php endif;?>
    </td>
    <td style="text-align:center;font-size:12px;"><?=$u['post_count']?:0?></td>
    <td style="text-align:center;">
      <?php if($postPct!==null): ?><span style="background:#e8f5e9;color:#1b5e20;padding:2px 8px;border-radius:20px;font-size:11px;">✅ ผ่าน</span>
      <?php elseif($u['post_count']>0): ?><span style="background:#ffebee;color:#b71c1c;padding:2px 8px;border-radius:20px;font-size:11px;">❌ ไม่ผ่าน</span>
      <?php else: ?><span style="background:#fff8e1;color:#e65100;padding:2px 8px;border-radius:20px;font-size:11px;">⏳ ยังไม่ทำ</span><?php endif;?>
    </td>
    <td style="text-align:center;"><?=$u['claimed']?'🏆':'—'?></td>
    <td style="text-align:center;">
      <div style="position:relative;display:inline-block;" class="mgr-wrap">
        <button type="button" class="cml-btn" style="background:#e8f5e9;color:#1b5e20;font-size:11px;padding:4px 8px;" onclick="toggleMenu(<?=$u['id']?>)">จัดการ ▾</button>
        <div id="menu-<?=$u['id']?>" style="display:none;position:absolute;right:0;top:100%;background:#fff;border:1px solid #c8e6c9;border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.12);z-index:200;min-width:185px;padding:6px 0;">
          <?php foreach([['del_pre','🗑 ลบ Pre-test','#f57f17'],['del_post','🗑 ลบ Post-test+รางวัล','#e65100'],['del_reward','🗑 ลบสิทธิ์รางวัล','#6a1b9a'],['del_all','🗑 ลบข้อมูลทดสอบ','#c62828'],['del_user','🗑 ลบบัญชีผู้ใช้','#b71c1c']] as [$act,$lbl,$col3]):?>
          <form method="post" style="margin:0;">
            <input type="hidden" name="del_action" value="<?=$act?>">
            <input type="hidden" name="target_uid" value="<?=$u['id']?>">
            <button type="submit" style="display:block;width:100%;text-align:left;padding:8px 14px;border:none;background:none;cursor:pointer;font-size:12px;color:<?=$col3?>;font-family:inherit;"
              onclick="return confirm('<?=addslashes($lbl)?> ของ <?=addslashes($u['name'])?>?')"
              onmouseover="this.style.background='#f5f5f5'" onmouseout="this.style.background='none'"><?=$lbl?></button>
          </form>
          <?php endforeach;?>
        </div>
      </div>
    </td>
  </tr>
  <?php endforeach;?>
  </tbody>
</table>
</div>

<?php endif; // end tabs ?>
</div><!-- end tab-content -->

<script>
// YouTube preview
function previewYT(val) {
  val=val.trim();
  const m=val.match(/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{11})/);
  const id=m?m[1].trim():(/^[A-Za-z0-9_-]{11}$/.test(val)?val:'');
  const prev=document.getElementById('yt-preview');
  const iframe=document.getElementById('yt-iframe');
  const inp=document.getElementById('ytInput');
  if(id&&prev){iframe.src='https://www.youtube.com/embed/'+id+'?rel=0';prev.style.display='block';inp.dataset.id=id;}
  else if(prev){prev.style.display='none';iframe.src='';inp.dataset.id='';}
}
// save YouTube ID before submit
document.querySelectorAll('form').forEach(f=>{
  f.addEventListener('submit',()=>{
    const y=document.getElementById('ytInput');
    if(y){const id=y.dataset.id?y.dataset.id.trim():(()=>{const m=y.value.match(/(?:v=|youtu\.be\/|embed\/)([A-Za-z0-9_-]{11})/);return m?m[1].trim():y.value.trim();})();if(id)y.value=id;}
  });
});
function switchType(t){
  ['yt-field','pdf-field','info-field'].forEach(id=>{const el=document.getElementById(id);if(el)el.style.display='none';});
  const show={'youtube':'yt-field','pdf':'pdf-field','info':'info-field'}[t];
  if(show){const el=document.getElementById(show);if(el)el.style.display='block';}
}

// Drag & drop sort
function makeSortable(listId,saveId){
  const list=document.getElementById(listId);if(!list)return;
  let drag=null;
  list.querySelectorAll('li').forEach(item=>{
    item.draggable=true;
    item.addEventListener('dragstart',()=>{drag=item;item.classList.add('dragging');});
    item.addEventListener('dragend',()=>{item.classList.remove('dragging');list.querySelectorAll('li').forEach(i=>i.classList.remove('drag-over'));});
    item.addEventListener('dragover',e=>{e.preventDefault();list.querySelectorAll('li').forEach(i=>i.classList.remove('drag-over'));if(item!==drag)item.classList.add('drag-over');});
    item.addEventListener('drop',e=>{e.preventDefault();if(drag&&drag!==item){const items=[...list.querySelectorAll('li')];if(items.indexOf(drag)<items.indexOf(item))item.after(drag);else item.before(drag);updateNums(list);const sb=document.getElementById(saveId);if(sb)sb.style.display='block';}item.classList.remove('drag-over');});
  });
}
function updateNums(list){list.querySelectorAll('.cml-num').forEach((n,i)=>n.textContent=i+1);}
function saveOrder(type){
  const listId=type==='lessons'?'lessonList':'qList';
  const saveId=type==='lessons'?'lesson-save':'q-save';
  const action=type==='lessons'?'reorder_lessons=1':'reorder_questions=1';
  const ids=[...(document.getElementById(listId)?.querySelectorAll('li')||[])].map(r=>r.dataset.id);
  fetch('course_manager.php?cid=<?=$cid?>',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:action+'&'+ids.map((id,i)=>`ids[${i}]=${id}`).join('&')
  }).then(r=>r.json()).then(d=>{if(d.ok){const el=document.getElementById(saveId);el.innerHTML='<span style="color:#2e7d32;font-size:13px;font-weight:600;">✅ บันทึกแล้ว</span>';setTimeout(()=>el.style.display='none',2000);}});
}
makeSortable('lessonList','lesson-save');
makeSortable('qList','q-save');

// Dropdown menu
function toggleMenu(uid){
  document.querySelectorAll('[id^="menu-"]').forEach(m=>{if(m.id!=='menu-'+uid)m.style.display='none';});
  const m=document.getElementById('menu-'+uid);
  if(m)m.style.display=m.style.display==='none'?'block':'none';
}
document.addEventListener('click',e=>{if(!e.target.closest('.mgr-wrap'))document.querySelectorAll('[id^="menu-"]').forEach(m=>m.style.display='none');});

// auto preview existing YouTube
const ytIn=document.getElementById('ytInput');
if(ytIn&&ytIn.value)previewYT(ytIn.value);
</script>

<?php
// Handle CSV export in dashboard tab
if (isset($_GET['export'])) {
    ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="'.mb_substr($course['title'],0,20).'_'.date('Ymd').'.csv"');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','w');
    fputcsv($out,['ชื่อ','ตำแหน่ง','กลุ่มงาน','โทร','Pre %','Post %','ครั้ง','สถานะ','รับรางวัล']);
    foreach($users as $u){
        $pp=($u['pre_score']!==null&&$u['pre_total'])?round($u['pre_score']/$u['pre_total']*100):'';
        $qp=($u['post_score']!==null&&$u['post_total'])?round($u['post_score']/$u['post_total']*100):'';
        $st=$qp!==''?'ผ่าน':($u['post_count']>0?'ยังไม่ผ่าน':'ยังไม่ทำ');
        fputcsv($out,[$u['name'],$u['position']??'',$u['department']??'',$u['phone']??'',$pp,$qp,$u['post_count'],$st,$u['claimed']?'รับแล้ว':'']);
    }
    fclose($out); exit;
}
require_once '../includes/footer.php';
?>
