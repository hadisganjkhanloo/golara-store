<?php
start_secure_session();
require_admin_login();

$admin_page = basename($_SERVER['SCRIPT_NAME']);
$nav = [
    'orders.php' => ['سفارش‌ها', ['orders.php', 'order.php']],
    'dashboard.php' => ['محصولات', ['dashboard.php', 'edit_product.php', 'confirm_delete.php']],
    'add_product.php' => ['افزودن محصول', ['add_product.php']],
    'categories.php' => ['دسته‌بندی‌ها', ['categories.php']],
    'coupons.php' => ['کدهای تخفیف', ['coupons.php']],
    'api_keys.php' => ['کلیدهای API', ['api_keys.php']],
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <meta name="api-url" content="<?php echo url('api/v1'); ?>">
    <meta name="robots" content="noindex, nofollow">
    <title>پنل مدیریت فروشگاه</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo url('assets/img/favicon.svg'); ?>">
    <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>">
</head>
<body>
<a class="skip-link" href="#main-content">پرش به محتوای اصلی</a>
<nav class="admin-nav" aria-label="منوی مدیریت">
    <div class="container" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
        <div>
            <?php foreach ($nav as $file => [$label, $pages]): ?>
                <a href="<?php echo $file; ?>" class="<?php echo in_array($admin_page, $pages, true) ? 'is-current' : ''; ?>"><?php echo $label; ?></a>
            <?php endforeach; ?>
            <a href="<?php echo url('index.php'); ?>" target="_blank" rel="noopener">مشاهده سایت</a>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
            <span style="color:#e6d3df;">خوش آمدید، <?php echo clean_input($_SESSION['admin_username']); ?></span>
            <a href="change_password.php" style="margin:0;">تغییر رمز</a>
            <form method="post" action="logout.php" class="inline-form">
                <?php echo csrf_field(); ?>
                <button type="submit" class="admin-logout">خروج</button>
            </form>
        </div>
    </div>
</nav>
<main class="container" id="main-content" tabindex="-1" style="padding-top:24px;">
