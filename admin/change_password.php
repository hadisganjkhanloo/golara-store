<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

$adminModel = new Admin();
$admin = $adminModel->find((int) $_SESSION['admin_id']);

if (!$admin) {
    unset($_SESSION['admin_id'], $_SESSION['admin_username']);
    redirect(url('admin/login.php'));
}

$forced = !empty($_SESSION['admin_must_change_password']);
$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['password'] ?? '';

    if (RateLimiter::tooMany('admin:pwchange', (string) $admin['id'], 5, 900)) {
        $errors[] = 'تلاش‌های ناموفق زیاد بود؛ چند دقیقه دیگر دوباره امتحان کنید.';
    } elseif (!password_verify($current, $admin['password'])) {
        RateLimiter::hit('admin:pwchange', (string) $admin['id']);
        $errors[] = 'رمز عبور فعلی درست نیست.';
    } else {
        if ($policy = password_policy_error($new)) {
            $errors[] = $policy;
        }
        if ($new === $current) {
            $errors[] = 'رمز جدید باید با رمز فعلی فرق داشته باشد.';
        }
        if ($new !== ($_POST['password_confirm'] ?? '')) {
            $errors[] = 'رمز جدید و تکرار آن یکسان نیستند.';
        }

        if (empty($errors)) {
            $adminModel->changePassword((int) $admin['id'], $new);
            unset($_SESSION['admin_must_change_password']);
            regenerate_session();
            $done = true;
            $forced = false;
        }
    }
}

require_once __DIR__ . '/_header.php';
?>

<h1>تغییر رمز عبور مدیر</h1>

<?php if ($forced): ?>
    <div class="alert alert-error" role="alert">رمز فعلی شما یک رمز پیش‌فرض و عمومی است (در ریپوی پروژه نوشته شده). برای ادامه باید رمز جدید انتخاب کنید.</div>
<?php endif; ?>

<?php if ($done): ?>
    <div class="alert alert-success" role="status">رمز عبور تغییر کرد.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?><div><?php echo clean_input($error); ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="change_password.php" class="box">
    <?php echo csrf_field(); ?>

    <label for="current_password">رمز عبور فعلی</label>
    <input type="password" id="current_password" name="current_password" autocomplete="current-password" required>

    <label for="password">رمز عبور جدید</label>
    <input type="password" id="password" name="password" autocomplete="new-password" aria-describedby="passwordHint" required>
    <p class="form-hint" id="passwordHint">حداقل ۸ کاراکتر، شامل حرف و عدد.</p>

    <label for="password_confirm">تکرار رمز عبور جدید</label>
    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>

    <button type="submit" class="btn">تغییر رمز</button>
</form>

<?php require_once __DIR__ . '/_footer.php'; ?>
