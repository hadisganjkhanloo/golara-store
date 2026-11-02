<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();

$adminModel = new Admin();

// هنوز هیچ مدیری ساخته نشده؛ اولین بار باید «ساخت اولین مدیر» انجام بشه
if ($adminModel->total() === 0) {
    redirect(url('admin/setup.php'));
}

if (!empty($_SESSION['admin_id'])) {
    redirect(url('admin/dashboard.php'));
}

if (!isset($_SESSION['admin_login_attempts'])) {
    $_SESSION['admin_login_attempts'] = 0;
}

const ADMIN_LOGIN_WINDOW = 900;      // ۱۵ دقیقه
const ADMIN_LOGIN_MAX_PER_IP = 10;
const ADMIN_LOGIN_MAX_PER_USER = 5;

$error = '';
$require_captcha = $_SESSION['admin_login_attempts'] >= 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = RateLimiter::ip();

    // قفل‌شدن بر اساس IP و نام کاربری تو دیتابیس؛ با پاک‌کردن کوکی دور زده نمی‌شه
    if (RateLimiter::tooMany('admin:ip', $ip, ADMIN_LOGIN_MAX_PER_IP, ADMIN_LOGIN_WINDOW)) {
        $error = 'تعداد تلاش‌های ناموفق زیاد بود. ' . RateLimiter::humanWait(RateLimiter::retryAfter('admin:ip', $ip, ADMIN_LOGIN_WINDOW)) . ' دیگر دوباره امتحان کنید.';
    } elseif ($username !== '' && RateLimiter::tooMany('admin:user', $username, ADMIN_LOGIN_MAX_PER_USER, ADMIN_LOGIN_WINDOW)) {
        $error = 'برای این نام کاربری تلاش ناموفق زیادی انجام شده. ' . RateLimiter::humanWait(RateLimiter::retryAfter('admin:user', $username, ADMIN_LOGIN_WINDOW)) . ' دیگر دوباره امتحان کنید.';
    } else {
        $captcha_ok = true;
        if ($require_captcha) {
            $captcha_answer = isset($_POST['captcha_answer']) ? (int) normalize_digits($_POST['captcha_answer']) : null;
            $captcha_ok = isset($_SESSION['admin_captcha_result']) && $captcha_answer === $_SESSION['admin_captcha_result'];
        }

        if (!$captcha_ok) {
            $error = 'پاسخ سوال امنیتی درست نیست.';
        } else {
            $admin = $adminModel->verifyLogin($username, $password);

            if ($admin) {
                // شناسه سشن بعد از ورود عوض می‌شه (جلوگیری از Session Fixation)
                regenerate_session();
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_login_attempts'] = 0;
                unset($_SESSION['admin_captcha_result']);
                RateLimiter::clear('admin:user', $username);

                // رمزی که تو ریپوی عمومی (نسخه‌های قبلی) نوشته شده بود؛ تا عوض نکنه به پنل دسترسی نداره
                if (Admin::isKnownDefaultPassword($password)) {
                    $_SESSION['admin_must_change_password'] = true;
                    redirect(url('admin/change_password.php'));
                }

                redirect(url('admin/orders.php'));
            }

            RateLimiter::hit('admin:ip', $ip);
            RateLimiter::hit('admin:user', $username);
            $_SESSION['admin_login_attempts']++;
            $error = 'نام کاربری یا رمز عبور اشتباه است.';
        }
    }

    $require_captcha = $_SESSION['admin_login_attempts'] >= 3;
}

$captcha_num1 = $captcha_num2 = 0;
if ($require_captcha) {
    $captcha_num1 = random_int(1, 10);
    $captcha_num2 = random_int(1, 10);
    $_SESSION['admin_captcha_result'] = $captcha_num1 + $captcha_num2;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>ورود به پنل مدیریت</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('assets/img/favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>">
</head>
<body>
<main class="container" style="padding-top:60px;">
    <h1>ورود مدیر</h1>

    <?php if ($error): ?>
        <div class="alert alert-error" role="alert"><?php echo clean_input($error); ?></div>
    <?php endif; ?>

    <?php if ($require_captcha): ?>
        <div class="alert alert-error">به دلیل چند تلاش ناموفق، لطفاً سوال امنیتی زیر رو هم جواب بده.</div>
    <?php endif; ?>

    <form method="post" action="login.php" class="box">
        <?php echo csrf_field(); ?>

        <label for="username">نام کاربری</label>
        <input type="text" id="username" name="username" autocomplete="username" required dir="ltr">

        <label for="password">رمز عبور</label>
        <input type="password" id="password" name="password" autocomplete="current-password" required>

        <?php if ($require_captcha): ?>
            <label for="captcha_answer">حاصل <?php echo $captcha_num1; ?> + <?php echo $captcha_num2; ?> چند می‌شه؟</label>
            <input type="text" id="captcha_answer" name="captcha_answer" autocomplete="off" inputmode="numeric">
        <?php endif; ?>

        <button type="submit" class="btn">ورود</button>
    </form>
</main>
</body>
</html>
