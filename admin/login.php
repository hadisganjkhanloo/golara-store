<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

if (!isset($_SESSION['admin_login_attempts'])) {
    $_SESSION['admin_login_attempts'] = 0;
}

$error = '';
$require_captcha = $_SESSION['admin_login_attempts'] >= 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $username = clean_input($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $captcha_ok = true;
    if ($require_captcha) {
        $captcha_answer = isset($_POST['captcha_answer']) ? (int) $_POST['captcha_answer'] : null;
        $captcha_ok = isset($_SESSION['admin_captcha_result']) && $captcha_answer === $_SESSION['admin_captcha_result'];
    }

    if (!$captcha_ok) {
        $error = 'پاسخ سوال امنیتی درست نیست.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM admins WHERE username = ?");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $admin = mysqli_fetch_assoc($result);

        if ($admin && password_verify($password, $admin['password'])) {
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_login_attempts'] = 0;
            unset($_SESSION['admin_captcha_result']);
            redirect('dashboard.php');
        } else {
            $_SESSION['admin_login_attempts']++;
            $error = 'نام کاربری یا رمز عبور اشتباه است.';
        }
    }

    $require_captcha = $_SESSION['admin_login_attempts'] >= 3;
}

$captcha_num1 = $captcha_num2 = 0;
if ($require_captcha) {
    $captcha_num1 = random_int(1, 10);
    $captcha_num2 = random_int(1, 10);
    $_SESSION['admin_captcha_result'] = $captcha_num1 + $captcha_num2;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود به پنل مدیریت</title>
    <link rel="stylesheet" href="/danshjoyar/assets/css/style.css">
</head>
<body>
<main class="container" style="padding-top:60px;">
    <h1>ورود مدیر</h1>

    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if ($require_captcha): ?>
        <div class="alert alert-error">به دلیل چند تلاش ناموفق، لطفاً سوال امنیتی زیر رو هم جواب بده.</div>
    <?php endif; ?>

    <form method="post" action="login.php" class="box">
        <?php echo csrf_field(); ?>

        <label for="username">نام کاربری</label>
        <input type="text" id="username" name="username" required>

        <label for="password">رمز عبور</label>
        <input type="password" id="password" name="password" required>

        <?php if ($require_captcha): ?>
            <label for="captcha_answer">حاصل <?php echo $captcha_num1; ?> + <?php echo $captcha_num2; ?> چند می‌شه؟</label>
            <input type="text" id="captcha_answer" name="captcha_answer" autocomplete="off">
        <?php endif; ?>

        <button type="submit" class="btn">ورود</button>
    </form>
</main>
</body>
</html>
