<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

if (!empty($_SESSION['customer_id'])) {
    redirect(url('account.php'));
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

const LOGIN_WINDOW = 900;          // ۱۵ دقیقه
const LOGIN_MAX_PER_IP = 20;       // تلاش ناموفق از یک IP
const LOGIN_MAX_PER_EMAIL = 5;     // تلاش ناموفق روی یک ایمیل (از هر IP)

$error = '';
$email = '';
$require_captcha = $_SESSION['login_attempts'] >= 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip = RateLimiter::ip();

    // قفل‌شدن بر اساس IP و ایمیل، تو دیتابیس: با پاک‌کردن کوکی یا سشن تازه دور زده نمی‌شه.
    // (شمارنده‌ی داخل سشن فقط برای نمایش کپچاست و به‌تنهایی جلوی ربات رو نمی‌گیره)
    if (RateLimiter::tooMany('login:ip', $ip, LOGIN_MAX_PER_IP, LOGIN_WINDOW)) {
        $error = 'تعداد تلاش‌های ناموفق از این شبکه زیاد بود. ' . RateLimiter::humanWait(RateLimiter::retryAfter('login:ip', $ip, LOGIN_WINDOW)) . ' دیگر دوباره امتحان کنید.';
    } elseif ($email !== '' && RateLimiter::tooMany('login:email', $email, LOGIN_MAX_PER_EMAIL, LOGIN_WINDOW)) {
        $error = 'برای این حساب تلاش ناموفق زیادی انجام شده. ' . RateLimiter::humanWait(RateLimiter::retryAfter('login:email', $email, LOGIN_WINDOW)) . ' دیگر دوباره امتحان کنید یا رمز را بازیابی کنید.';
    } else {
        $captcha_ok = true;
        if ($require_captcha) {
            $captcha_answer = isset($_POST['captcha_answer']) ? (int) normalize_digits($_POST['captcha_answer']) : null;
            $captcha_ok = isset($_SESSION['captcha_result']) && $captcha_answer === $_SESSION['captcha_result'];
        }

        if (!$captcha_ok) {
            $error = 'پاسخ سوال امنیتی درست نیست.';
        } else {
            $customer = (new Customer())->verifyLogin($email, $password);

            if ($customer) {
                // شناسه سشن عوض می‌شه و سبد/علاقه‌مندی‌های مهمان تو حساب کاربر ادغام می‌شن
                login_customer($customer);
                RateLimiter::clear('login:email', $email);
                $_SESSION['login_attempts'] = 0;
                unset($_SESSION['captcha_result']);
                redirect(url('account.php'));
            }

            // جلوگیری از Brute Force: هر تلاش ناموفق تو دیتابیس ثبت می‌شه
            RateLimiter::hit('login:ip', $ip);
            RateLimiter::hit('login:email', $email);
            $_SESSION['login_attempts']++;
            $error = 'ایمیل یا رمز عبور اشتباه است.';
        }
    }

    $require_captcha = $_SESSION['login_attempts'] >= 3;
}

$captcha_num1 = $captcha_num2 = 0;
if ($require_captcha) {
    $captcha_num1 = random_int(1, 10);
    $captcha_num2 = random_int(1, 10);
    $_SESSION['captcha_result'] = $captcha_num1 + $captcha_num2;
}

set_seo(['title' => 'ورود به حساب کاربری', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';
?>

<h1>ورود به حساب کاربری</h1>

<?php if ($error): ?>
    <div class="alert alert-error" role="alert"><?php echo clean_input($error); ?></div>
<?php endif; ?>

<?php if ($require_captcha): ?>
    <div class="alert alert-error">به دلیل چند تلاش ناموفق، لطفاً سوال امنیتی زیر رو هم جواب بده.</div>
<?php endif; ?>

<form method="post" action="<?php echo url('login.php'); ?>" class="box">
    <?php echo csrf_field(); ?>

    <label for="email">ایمیل</label>
    <input type="email" id="email" name="email" value="<?php echo clean_input($email); ?>" autocomplete="username" required dir="ltr">

    <label for="password">رمز عبور</label>
    <input type="password" id="password" name="password" autocomplete="current-password" required>

    <?php if ($require_captcha): ?>
        <label for="captcha_answer">حاصل <?php echo $captcha_num1; ?> + <?php echo $captcha_num2; ?> چند می‌شه؟</label>
        <input type="text" id="captcha_answer" name="captcha_answer" autocomplete="off" inputmode="numeric">
    <?php endif; ?>

    <button type="submit" class="btn">ورود</button>
</form>

<p style="margin-top:14px; font-size:14px;"><a href="<?php echo url('forgot_password.php'); ?>" style="color:var(--pink-dark);">رمز عبورت را فراموش کرده‌ای؟</a></p>
<p style="margin-top:6px; font-size:14px;">حساب کاربری نداری؟ <a href="<?php echo url('register.php'); ?>" style="color:var(--pink-dark);">ثبت‌نام کن</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
