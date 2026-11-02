<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

if (!empty($_SESSION['customer_id'])) {
    redirect('account.php');
}

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

$error = '';
$email = '';
$require_captcha = $_SESSION['login_attempts'] >= 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $captcha_ok = true;
    if ($require_captcha) {
        $captcha_answer = isset($_POST['captcha_answer']) ? (int) $_POST['captcha_answer'] : null;
        $captcha_ok = isset($_SESSION['captcha_result']) && $captcha_answer === $_SESSION['captcha_result'];
    }

    if (!$captcha_ok) {
        $error = 'پاسخ سوال امنیتی درست نیست.';
    } else {
        $customerModel = new Customer();
        $customer = $customerModel->verifyLogin($email, $password);

        if ($customer) {
            // شناسه سشن عوض می‌شه و سبد/علاقه‌مندی‌های مهمان تو حساب کاربر ادغام می‌شن
            login_customer($customer);
            $_SESSION['login_attempts'] = 0;
            unset($_SESSION['captcha_result']);
            redirect(url('account.php'));
        } else {
            // جلوگیری از Brute Force: بعد از چندبار تلاش ناموفق، دفعه بعد کپچا لازم می‌شه
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

require_once __DIR__ . '/includes/header.php';
?>

<h1>ورود به حساب کاربری</h1>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<?php if ($require_captcha): ?>
    <div class="alert alert-error">به دلیل چند تلاش ناموفق، لطفاً سوال امنیتی زیر رو هم جواب بده.</div>
<?php endif; ?>

<form method="post" action="login.php" class="box">
    <?php echo csrf_field(); ?>

    <label for="email">ایمیل</label>
    <input type="text" id="email" name="email" value="<?php echo $email; ?>">

    <label for="password">رمز عبور</label>
    <input type="password" id="password" name="password">

    <?php if ($require_captcha): ?>
        <label for="captcha_answer">حاصل <?php echo $captcha_num1; ?> + <?php echo $captcha_num2; ?> چند می‌شه؟</label>
        <input type="text" id="captcha_answer" name="captcha_answer" autocomplete="off">
    <?php endif; ?>

    <button type="submit" class="btn">ورود</button>
</form>

<p style="margin-top:14px; font-size:14px;">حساب کاربری نداری؟ <a href="register.php" style="color:var(--pink-dark);">ثبت‌نام کن</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
