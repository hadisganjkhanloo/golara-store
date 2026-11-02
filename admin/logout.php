<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();

// فقط بخش مدیر پاک می‌شه (اگه همین مرورگر مشتری هم وارد باشه، خارج نمی‌شه)
unset($_SESSION['admin_id'], $_SESSION['admin_username']);
regenerate_session();

redirect(url('admin/login.php'));
