<?php
// includes/config.php — Railway version
// ดึงค่าจาก Environment Variables ที่ Railway inject ให้

define('DB_HOST', getenv('MYSQLHOST')     ?: getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('MYSQLUSER')     ?: getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'elearning_db');
define('DB_PORT', getenv('MYSQLPORT')     ?: getenv('DB_PORT') ?: '3306');

define('PASS_THRESHOLD',   80);
define('POST_MAX_ATTEMPTS', 2);

// เชื่อมต่อ — Railway ใช้ port จาก env
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
$conn->set_charset('utf8mb4');
if ($conn->connect_error) {
    die('เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $conn->connect_error);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function redirect($url) { header("Location: $url"); exit; }
function requireLogin() { if (empty($_SESSION['user_id'])) redirect('/login.php'); }
function requireAdmin() { requireLogin(); if ($_SESSION['role']!=='admin') redirect('/index.php'); }
