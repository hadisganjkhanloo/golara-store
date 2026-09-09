<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $email = clean_input($_POST['email'] ?? '');

    if (is_valid_email($email)) {
        $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO newsletter (email) VALUES (?)");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $_SESSION['newsletter_message'] = 'با موفقیت در خبرنامه عضو شدید.';
    } else {
        $_SESSION['newsletter_message'] = 'ایمیل وارد شده معتبر نیست.';
    }
}

redirect('index.php');
