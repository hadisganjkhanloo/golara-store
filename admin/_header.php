<?php
start_secure_session();
require_admin_login();

$admin_page = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>پنل مدیریت فروشگاه</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>">
</head>
<body>
<div class="admin-nav">
    <div class="container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div>
            <a href="dashboard.php" class="<?php echo in_array($admin_page, ['dashboard.php', 'edit_product.php', 'confirm_delete.php'], true) ? 'is-current' : ''; ?>">محصولات</a>
            <a href="add_product.php" class="<?php echo $admin_page === 'add_product.php' ? 'is-current' : ''; ?>">افزودن محصول</a>
            <a href="categories.php" class="<?php echo $admin_page === 'categories.php' ? 'is-current' : ''; ?>">دسته‌بندی‌ها</a>
            <a href="<?php echo url('index.php'); ?>" target="_blank">مشاهده سایت</a>
        </div>
        <div>
            <span style="color:#d9c4d3;">خوش آمدید، <?php echo clean_input($_SESSION['admin_username']); ?></span>
            <a href="logout.php" style="margin-right:12px;">خروج</a>
        </div>
    </div>
</div>
<main class="container" style="padding-top:24px;">
