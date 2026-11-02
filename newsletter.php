<?php
// نسخه بدون جاوااسکریپتِ عضویت در خبرنامه (وقتی Ajax در دسترس نیست)؛ فرم اصلی صفحه اول از /api/v1/newsletter استفاده می‌کنه

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $email = trim($_POST['email'] ?? '');

    if (is_valid_email($email)) {
        $added = (new Newsletter())->subscribe($email);
        $_SESSION['newsletter_message'] = $added
            ? 'با موفقیت در خبرنامه عضو شدید.'
            : 'این ایمیل قبلاً در خبرنامه عضو شده است.';
    } else {
        $_SESSION['newsletter_message'] = 'ایمیل وارد شده معتبر نیست.';
    }
}

// به همون بخش خبرنامه برمی‌گردیم، نه بالای صفحه
redirect(url('index.php#newsletter'));
