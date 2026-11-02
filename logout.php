<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// خروج فقط با POST + توکن CSRF؛ باز کردن این آدرس با لینک یا عکس مخفی کاربر رو خارج نمی‌کنه
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    // سبد و علاقه‌مندی‌ها تو حساب (دیتابیس) می‌مونن؛ فقط نسخه‌ی داخل این مرورگر پاک می‌شه
    logout_customer();

    redirect(url('index.php'));
}

redirect(url(!empty($_SESSION['customer_id']) ? 'account.php' : 'index.php'));
