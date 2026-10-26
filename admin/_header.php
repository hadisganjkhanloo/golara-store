<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_admin_login();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>پنل مدیریت فروشگاه</title>
    <link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>
<div class="admin-nav">
    <div class="container" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <a href="dashboard.php">محصولات</a>
            <a href="add_product.php">افزودن محصول</a>
            <a href="<?php echo url('index.php'); ?>" target="_blank">مشاهده سایت</a>
        </div>
        <div>
            <span style="color:#94a3b8;">خوش آمدید، <?php echo clean_input($_SESSION['admin_username']); ?></span>
            <a href="logout.php" style="margin-right:12px;">خروج</a>
        </div>
    </div>
</div>
<main class="container" style="padding-top:24px;">
