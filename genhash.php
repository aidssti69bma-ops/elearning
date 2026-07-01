<?php
// ลบไฟล์นี้ทิ้งหลังใช้งาน!
$pass = $_GET['p'] ?? '123456';
echo '<pre>';
echo 'Password: ' . htmlspecialchars($pass) . "\n";
echo 'Hash: ' . password_hash($pass, PASSWORD_BCRYPT) . "\n";
echo '</pre>';
echo '<form>Password: <input name="p" value="'.htmlspecialchars($pass).'"> <button>Generate</button></form>';
echo '<p style="color:red">⚠️ ลบไฟล์นี้ทิ้งหลังใช้งาน!</p>';
