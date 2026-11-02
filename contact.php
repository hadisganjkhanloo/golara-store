<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

$errors = [];
$success = false;

$name = $email = $phone = $message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    // مقدارها خام ذخیره می‌شن؛ موقع نمایش با clean_input امن می‌شن
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = normalize_digits(trim($_POST['phone'] ?? ''));
    $message = trim($_POST['message'] ?? '');

    // جلوگیری از پر شدن صندوق پیام‌ها توسط ربات: ۵ پیام در ساعت از هر IP
    $ip = RateLimiter::ip();
    if (RateLimiter::tooMany('contact', $ip, 5, 3600)) {
        $errors[] = 'تعداد پیام‌های ارسالی زیاد بود؛ کمی بعد دوباره تلاش کنید.';
    } else {
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'نام را وارد کنید (حداکثر ۱۰۰ حرف).';
        }
        if (!is_valid_email($email) || mb_strlen($email) > 150) {
            $errors[] = 'ایمیل معتبر نیست.';
        }
        if (!is_valid_phone($phone)) {
            $errors[] = 'شماره موبایل معتبر نیست (فرمت صحیح: 09123456789).';
        }
        if ($message === '' || mb_strlen($message) > 3000) {
            $errors[] = 'متن پیام را وارد کنید (حداکثر ۳۰۰۰ حرف).';
        }

        if (empty($errors)) {
            RateLimiter::hit('contact', $ip);

            $stmt = mysqli_prepare($conn, "INSERT INTO messages (name, email, phone, message) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $phone, $message);
            mysqli_stmt_execute($stmt);

            $success = true;
            $name = $email = $phone = $message = '';
        }
    }
}

set_seo([
    'title' => 'تماس با ما',
    'description' => 'با پشتیبانی ' . SHOP_NAME . ' در تماس باشید؛ سوال، پیشنهاد یا انتقاد خودتان را برای ما بنویسید.',
]);
require_once __DIR__ . '/includes/header.php';

render_breadcrumbs([['خانه', url('index.php')], ['تماس با ما']]);
?>

<h1>تماس با ما</h1>

<?php if ($success): ?>
    <div class="alert alert-success" role="status">پیام شما با موفقیت ارسال شد.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?>
            <div><?php echo clean_input($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="<?php echo url('contact.php'); ?>" class="box">
    <?php echo csrf_field(); ?>

    <label for="name">نام و نام خانوادگی</label>
    <input type="text" id="name" name="name" value="<?php echo clean_input($name); ?>" maxlength="100" autocomplete="name" required>

    <label for="email">ایمیل</label>
    <input type="email" id="email" name="email" value="<?php echo clean_input($email); ?>" maxlength="150" autocomplete="email" required dir="ltr">

    <label for="phone">شماره موبایل</label>
    <input type="tel" id="phone" name="phone" value="<?php echo clean_input($phone); ?>" placeholder="09123456789" maxlength="11" autocomplete="tel" inputmode="numeric" required dir="ltr">

    <label for="message">پیام</label>
    <textarea id="message" name="message" rows="5" maxlength="3000" required><?php echo clean_input($message); ?></textarea>

    <button type="submit" class="btn">ارسال پیام</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
