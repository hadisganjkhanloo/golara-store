<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

if (!empty($_SESSION['customer_id'])) {
    redirect(url('account.php'));
}

$sent = false;
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $email = trim($_POST['email'] ?? '');
    $ip = RateLimiter::ip();

    if (!is_valid_email($email)) {
        $error = 'ایمیل معتبر نیست.';
    } elseif (RateLimiter::tooMany('reset:ip', $ip, 5, 3600) || RateLimiter::tooMany('reset:email', $email, 3, 3600)) {
        $error = 'درخواست‌های بازیابی زیاد بود. حداکثر ساعت دیگر دوباره تلاش کنید.';
    } else {
        RateLimiter::hit('reset:ip', $ip);
        RateLimiter::hit('reset:email', $email);

        $customerModel = new Customer();
        $customer = $customerModel->findByEmail($email);

        if ($customer) {
            $token = $customerModel->createResetToken((int) $customer['id']);
            Mailer::send(
                $customer['email'],
                'بازیابی رمز عبور ' . SHOP_NAME,
                "سلام " . $customer['name'] . "،\n\nبرای انتخاب رمز جدید روی لینک زیر بزن (تا یک ساعت معتبره):\n" .
                absolute_url('reset_password.php?token=' . $token) .
                "\n\nاگه تو این درخواست رو ندادی، این ایمیل رو نادیده بگیر؛ رمزت تغییری نمی‌کنه."
            );
        }

        // چه ایمیل وجود داشته باشه چه نه، همین پیام نشون داده می‌شه (تا کسی نتونه ایمیل‌های ثبت‌شده رو حدس بزنه)
        $sent = true;
    }
}

set_seo(['title' => 'بازیابی رمز عبور', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';
?>

<h1>بازیابی رمز عبور</h1>

<?php if ($sent): ?>
    <div class="alert alert-success" role="status">
        اگر این ایمیل در سایت ثبت‌نام کرده باشد، لینک بازیابی برایش ارسال شد.
        <?php if (Mailer::driver() === 'log'): ?>
            <br><small>(حالت توسعه: ایمیل به‌جای ارسال، در فایل <span dir="ltr">storage/mail.log</span> ذخیره شد.)</small>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?php echo clean_input($error); ?></div>
    <?php endif; ?>

    <form method="post" action="<?php echo url('forgot_password.php'); ?>" class="box">
        <?php echo csrf_field(); ?>
        <label for="email">ایمیل حساب کاربری</label>
        <input type="email" id="email" name="email" value="<?php echo clean_input($email); ?>" required autocomplete="email" dir="ltr">
        <button type="submit" class="btn">ارسال لینک بازیابی</button>
    </form>
<?php endif; ?>

<p style="margin-top:14px; font-size:14px;"><a href="<?php echo url('login.php'); ?>" style="color:var(--pink-dark);">بازگشت به صفحه ورود</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
