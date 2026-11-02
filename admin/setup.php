<?php
// ساخت اولین مدیر. فقط وقتی کار می‌کنه که هنوز هیچ مدیری تو دیتابیس نباشه.
//
// به‌جای یه رمز پیش‌فرض (که تو ریپوی عمومی همه می‌بینن)، صاحب سایت بعد از نصب خودش رمز انتخاب می‌کنه.
// اگه سایت رو روی اینترنت می‌ذاری و می‌خوای مطمئن باشی کس دیگه‌ای زودتر مدیر نمی‌شه، تو .env یه
// ADMIN_SETUP_KEY بذار؛ اون‌وقت این فرم بدون اون کلید قبول نمی‌شه.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();

$adminModel = new Admin();

if ($adminModel->total() > 0) {
    redirect(url('admin/login.php'));
}

$setupKey = trim($_ENV['ADMIN_SETUP_KEY'] ?? '');
$errors = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = RateLimiter::ip();

    if (RateLimiter::tooMany('admin:setup', $ip, 10, 900)) {
        $errors[] = 'تلاش‌های زیاد؛ چند دقیقه دیگر دوباره امتحان کنید.';
    } else {
        RateLimiter::hit('admin:setup', $ip);

        if ($setupKey !== '' && !hash_equals($setupKey, (string) ($_POST['setup_key'] ?? ''))) {
            $errors[] = 'کلید نصب (ADMIN_SETUP_KEY) درست نیست.';
        }
        if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
            $errors[] = 'نام کاربری ۳ تا ۵۰ کاراکتر و فقط شامل حروف انگلیسی، عدد و _ . - باشد.';
        }
        if ($policy = password_policy_error($password)) {
            $errors[] = $policy;
        }
        if ($password !== ($_POST['password_confirm'] ?? '')) {
            $errors[] = 'رمز عبور و تکرار آن یکسان نیستند.';
        }

        if (empty($errors)) {
            $id = $adminModel->create($username, $password);

            // اولین مدیر بلافاصله وارد می‌شه
            regenerate_session();
            $_SESSION['admin_id'] = $id;
            $_SESSION['admin_username'] = $username;

            redirect(url('admin/orders.php'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>ساخت اولین مدیر</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('assets/img/favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>">
</head>
<body>
<main class="container" style="padding-top:60px;">
    <h1>ساخت اولین مدیر</h1>
    <p style="margin-bottom:16px; font-size:14px; color:var(--plum-soft);">هنوز هیچ مدیری ساخته نشده. نام کاربری و رمز عبور مدیر فروشگاه را انتخاب کنید.</p>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
            <?php foreach ($errors as $error): ?><div><?php echo clean_input($error); ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="setup.php" class="box">
        <?php echo csrf_field(); ?>

        <?php if ($setupKey !== ''): ?>
            <label for="setup_key">کلید نصب (ADMIN_SETUP_KEY از فایل .env)</label>
            <input type="password" id="setup_key" name="setup_key" autocomplete="off" required>
        <?php endif; ?>

        <label for="username">نام کاربری</label>
        <input type="text" id="username" name="username" value="<?php echo clean_input($username); ?>" autocomplete="username" required dir="ltr">

        <label for="password">رمز عبور</label>
        <input type="password" id="password" name="password" autocomplete="new-password" aria-describedby="passwordHint" required>
        <p class="form-hint" id="passwordHint">حداقل ۸ کاراکتر، شامل حرف و عدد.</p>

        <label for="password_confirm">تکرار رمز عبور</label>
        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>

        <button type="submit" class="btn">ساخت مدیر و ورود</button>
    </form>
</main>
</body>
</html>
