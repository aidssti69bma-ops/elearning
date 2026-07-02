<?php
require_once 'includes/config.php';

// ล้าง session
session_destroy();

// ล้าง cookie
setcookie('el_name',       '', time()-3600, '/');
setcookie('el_position',   '', time()-3600, '/');
setcookie('el_department', '', time()-3600, '/');
setcookie('el_phone',      '', time()-3600, '/');

redirect('/login.php');
