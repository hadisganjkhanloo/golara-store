<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

$errors = [];
$success = false;

$name = $email = $phone = $message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $name = clean_input($_POST['name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $phone = clean_input($_POST['phone'] ?? '');
    $message = clean_input($_POST['message'] ?? '');

    if ($name === '') {
        $errors[] = 'نام را وارد کنید.';
    }
    if (!is_valid_email($email)) {
        $errors[] = 'ایمیل معتبر نیست.';
    }
    if (!is_valid_phone($phone)) {
        $errors[] = 'شماره موبایل معتبر نیست (فرمت صحیح: 09123456789).';
    }
    if ($message === '') {
        $errors[] = 'متن پیام را وارد کنید.';
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO messages (name, email, phone, message) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $phone, $message);
        mysqli_stmt_execute($stmt);

        $success = true;
        $name = $email = $phone = $message = '';
    }
}
?>

<h1>تماس با ما</h1>

<?php if ($success): ?>
    <div class="alert alert-success">پیام شما با موفقیت ارسال شد.</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $error): ?>
            <div><?php echo $error; ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="contact.php" class="box">
    <?php echo csrf_field(); ?>

    <label for="name">نام و نام خانوادگی</label>
    <input type="text" id="name" name="name" value="<?php echo $name; ?>">

    <label for="email">ایمیل</label>
    <input type="text" id="email" name="email" value="<?php echo $email; ?>">

    <label for="phone">شماره موبایل</label>
    <input type="text" id="phone" name="phone" value="<?php echo $phone; ?>" placeholder="09123456789">

    <label for="message">پیام</label>
    <textarea id="message" name="message" rows="5"><?php echo $message; ?></textarea>

    <button type="submit" class="btn">ارسال پیام</button>
</form>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
