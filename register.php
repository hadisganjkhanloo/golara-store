<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// این بخش باید قبل از header.php اجرا بشه، چون ممکنه بخواد redirect کنه
// و بعد از اینکه HTML چاپ شد دیگه نمی‌شه هدر جدید فرستاد
if (!empty($_SESSION['customer_id'])) {
    redirect('account.php');
}

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $name = clean_input($_POST['name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($name === '') {
        $errors[] = 'نام را وارد کنید.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'ایمیل معتبر نیست.';
    }
    if (strlen($password) < 6) {
        $errors[] = 'رمز عبور باید حداقل ۶ کاراکتر باشد.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'رمز عبور و تکرار آن یکسان نیستند.';
    }

    $customerModel = new Customer();

    if (empty($errors) && $customerModel->emailExists($email)) {
        $errors[] = 'این ایمیل قبلاً ثبت‌نام کرده است.';
    }

    if (empty($errors)) {
        $customerId = $customerModel->register($name, $email, $password);
        login_customer(['id' => $customerId, 'name' => $name]);

        redirect(url('account.php'));
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<h1>ثبت‌نام</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $error): ?>
            <div><?php echo $error; ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="register.php" class="box">
    <?php echo csrf_field(); ?>

    <label for="name">نام و نام خانوادگی</label>
    <input type="text" id="name" name="name" value="<?php echo $name; ?>">

    <label for="email">ایمیل</label>
    <input type="text" id="email" name="email" value="<?php echo $email; ?>">

    <label for="password">رمز عبور</label>
    <input type="password" id="password" name="password">

    <label for="password_confirm">تکرار رمز عبور</label>
    <input type="password" id="password_confirm" name="password_confirm">

    <button type="submit" class="btn">ثبت‌نام</button>
</form>

<p style="margin-top:14px; font-size:14px;">قبلاً ثبت‌نام کردی؟ <a href="login.php" style="color:var(--pink-dark);">وارد شو</a></p>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
