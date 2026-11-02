<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// سبد و علاقه‌مندی‌ها تو حساب (دیتابیس) می‌مونن؛ فقط نسخه‌ی داخل این مرورگر پاک می‌شه
logout_customer();

redirect(url('index.php'));
