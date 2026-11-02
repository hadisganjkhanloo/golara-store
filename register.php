<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// این بخش باید قبل از header.php اجرا بشه، چون ممکنه بخواد redirect کنه
// و بعد از اینکه HTML چاپ شد دیگه نمی‌شه هدر جدید فرستاد
if (!empty($_SESSION['customer_id'])) {
    redirect(url('account.php'));
}

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    // جلوگیری از ساخت انبوه حساب توسط ربات: ۱۰ ثبت‌نام در ساعت از هر IP
    $ip = RateLimiter::ip();
    if (RateLimiter::tooMany('register', $ip, 10, 3600)) {
        $errors[] = 'تعداد ثبت‌نام از این شبکه زیاد بود. ' . RateLimiter::humanWait(RateLimiter::retryAfter('register', $ip, 3600)) . ' دیگر دوباره امتحان کنید.';
    } else {
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'نام را وارد کنید (حداکثر ۱۰۰ حرف).';
        }
        if (!is_valid_email($email) || mb_strlen($email) > 150) {
            $errors[] = 'ایمیل معتبر نیست.';
        }
        if ($policy = password_policy_error($password)) {
            $errors[] = $policy;
        }
        if ($password !== $password_confirm) {
            $errors[] = 'رمز عبور و تکرار آن یکسان نیستند.';
        }

        $customerModel = new Customer();

        if (empty($errors) && $customerModel->emailExists($email)) {
            $errors[] = 'این ایمیل قبلاً ثبت‌نام کرده است.';
        }

        if (empty($errors)) {
            RateLimiter::hit('register', $ip);
            $customerId = $customerModel->register($name, $email, $password);
            login_customer(['id' => $customerId, 'name' => $name]);

            redirect(url('account.php'));
        }
    }
}

set_seo(['title' => 'ثبت‌نام', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';
?>

<h1>ثبت‌نام</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?>
            <div><?php echo clean_input($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo url('register.php'); ?>" class="box">
    <?php echo csrf_field(); ?>

    <label for="name">نام و نام خانوادگی</label>
    <input type="text" id="name" name="name" value="<?php echo clean_input($name); ?>" maxlength="100" autocomplete="name" required>

    <label for="email">ایمیل</label>
    <input type="email" id="email" name="email" value="<?php echo clean_input($email); ?>" maxlength="150" autocomplete="email" required dir="ltr">

    <label for="password">رمز عبور</label>
    <input type="password" id="password" name="password" autocomplete="new-password" aria-describedby="passwordHint" required>
    <p class="form-hint" id="passwordHint">حداقل ۸ کاراکتر، شامل حرف و عدد.</p>

    <label for="password_confirm">تکرار رمز عبور</label>
    <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>

    <button type="submit" class="btn">ثبت‌نام</button>
</form>

<p style="margin-top:14px; font-size:14px;">قبلاً ثبت‌نام کردی؟ <a href="<?php echo url('login.php'); ?>" style="color:var(--pink-dark);">وارد شو</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
