<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();

// خروج فقط با POST + توکن CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    // فقط بخش مدیر پاک می‌شه (اگه همین مرورگر مشتری هم وارد باشه، خارج نمی‌شه)
    unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['admin_must_change_password']);
    regenerate_session();

    redirect(url('admin/login.php'));
}

redirect(url(!empty($_SESSION['admin_id']) ? 'admin/dashboard.php' : 'admin/login.php'));
