<?php
// router.php — ใช้กับ php -S เพื่อ serve static files ด้วย
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// ถ้าเป็นไฟล์จริงและไม่ใช่ .php ให้ส่งตรงๆ
if ($uri !== '/' && file_exists($file) && !is_dir($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false; // PHP built-in server จะ serve ไฟล์นั้นเอง
}

// ถ้าไม่มี extension หรือเป็น / ให้ไปที่ index.php
if ($uri === '/' || !pathinfo($uri, PATHINFO_EXTENSION)) {
    require __DIR__ . '/index.php';
    return true;
}

// อื่นๆ ให้ PHP handle เอง
return false;
