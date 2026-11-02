<?php
// نسخه بدون جاوااسکریپتِ عضویت در خبرنامه (وقتی Ajax در دسترس نیست)؛ فرم اصلی صفحه اول از /api/v1/newsletter استفاده می‌کنه
// منطق (نرخ‌محدودی، ایمیل تأیید) همون منطق API است، اینجا فقط به‌جای JSON یه پیام تو سشن می‌ذاره.

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $email = trim($_POST['email'] ?? '');
    $ip = RateLimiter::ip();

    if (!is_valid_email($email) || mb_strlen($email) > 150) {
        $_SESSION['newsletter_message'] = 'ایمیل وارد شده معتبر نیست.';
    } elseif (RateLimiter::tooMany('newsletter:ip', $ip, 5, 3600) || RateLimiter::tooMany('newsletter:email', $email, 3, 3600)) {
        $_SESSION['newsletter_message'] = 'درخواست‌های شما زیاد بود؛ کمی بعد دوباره تلاش کنید.';
    } else {
        RateLimiter::hit('newsletter:ip', $ip);
        RateLimiter::hit('newsletter:email', $email);

        $result = (new Newsletter())->subscribe($email);

        if ($result['status'] === Newsletter::ALREADY) {
            $_SESSION['newsletter_message'] = 'این ایمیل قبلاً در خبرنامه عضو و تأیید شده است.';
        } else {
            Newsletter::sendConfirmation($email, $result['token']);
            $_SESSION['newsletter_message'] = 'ایمیل تأیید برایتان ارسال شد؛ برای فعال‌شدن عضویت روی لینک داخل آن بزنید.'
                . (Mailer::driver() === 'log' ? ' (حالت توسعه: ایمیل در فایل storage/mail.log ذخیره شد.)' : '');
        }
    }
}

// به همون بخش خبرنامه برمی‌گردیم، نه بالای صفحه
redirect(url('index.php#newsletter'));
