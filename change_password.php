<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();
require_customer_login();

$customerModel = new Customer();
$customer = $customerModel->find((int) $_SESSION['customer_id']);

if (!$customer) {
    logout_customer();
    redirect(url('login.php'));
}

$errors = [];
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $current = $_POST['current_password'] ?? '';
    $new = $_POST['password'] ?? '';

    // تغییر رمز هم می‌تونه برای حدس‌زدن رمز فعلی سوءاستفاده بشه؛ پس نرخ‌محدود است
    if (RateLimiter::tooMany('pwchange', (string) $customer['id'], 5, 900)) {
        $errors[] = 'تلاش‌های ناموفق زیاد بود؛ چند دقیقه دیگر دوباره امتحان کنید.';
    } elseif (!password_verify($current, $customer['password'])) {
        RateLimiter::hit('pwchange', (string) $customer['id']);
        $errors[] = 'رمز عبور فعلی درست نیست.';
    } else {
        if ($policy = password_policy_error($new)) {
            $errors[] = $policy;
        }
        if ($new !== ($_POST['password_confirm'] ?? '')) {
            $errors[] = 'رمز جدید و تکرار آن یکسان نیستند.';
        }

        if (empty($errors)) {
            $customerModel->updatePassword((int) $customer['id'], $new);
            regenerate_session();
            $done = true;
        }
    }
}

set_seo(['title' => 'تغییر رمز عبور', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';

render_breadcrumbs([['خانه', url('index.php')], ['حساب کاربری', url('account.php')], ['تغییر رمز عبور']]);
?>

<h1>تغییر رمز عبور</h1>

<?php if ($done): ?>
    <div class="alert alert-success" role="status">رمز عبور تغییر کرد.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?><div><?php echo clean_input($error); ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo url('change_password.php'); ?>" class="box">
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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
