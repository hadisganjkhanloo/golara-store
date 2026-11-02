<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$customerModel = new Customer();
$customer = $customerModel->findByResetToken($token);

$errors = [];
$done = false;

if ($customer && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $password = $_POST['password'] ?? '';

    if ($policy = password_policy_error($password)) {
        $errors[] = $policy;
    }
    if ($password !== ($_POST['password_confirm'] ?? '')) {
        $errors[] = 'رمز عبور و تکرار آن یکسان نیستند.';
    }

    if (empty($errors) && $customerModel->resetPassword($token, $password)) {
        // شمارنده‌ی تلاش‌های ناموفق این حساب هم صفر می‌شه
        RateLimiter::clear('login:email', $customer['email']);
        $done = true;
    }
}

set_seo(['title' => 'انتخاب رمز جدید', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';
?>

<h1>انتخاب رمز جدید</h1>

<?php if ($done): ?>
    <div class="alert alert-success" role="status">رمز عبور تغییر کرد. حالا می‌توانی وارد شوی.</div>
    <a href="<?php echo url('login.php'); ?>" class="btn">ورود به حساب</a>
<?php elseif (!$customer): ?>
    <div class="alert alert-error" role="alert">این لینک نامعتبر است یا منقضی شده (هر لینک فقط یک ساعت و یک‌بار قابل استفاده است).</div>
    <a href="<?php echo url('forgot_password.php'); ?>" class="btn">درخواست لینک جدید</a>
<?php else: ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
            <?php foreach ($errors as $error): ?><div><?php echo clean_input($error); ?></div><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" action="<?php echo url('reset_password.php'); ?>" class="box">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo clean_input($token); ?>">

        <label for="password">رمز عبور جدید</label>
        <input type="password" id="password" name="password" autocomplete="new-password" aria-describedby="passwordHint" required>
        <p class="form-hint" id="passwordHint">حداقل ۸ کاراکتر، شامل حرف و عدد.</p>

        <label for="password_confirm">تکرار رمز عبور جدید</label>
        <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>

        <button type="submit" class="btn">ذخیره رمز جدید</button>
    </form>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
