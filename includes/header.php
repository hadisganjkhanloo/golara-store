<?php
start_secure_session();
$cart_count = (new Cart())->count();
$wishlist_count = (new Wishlist())->count();

// بهینه‌سازی (فصل ۲۲): دسته‌بندی‌ها فقط یه‌بار در کل درخواست کوئری می‌شن،
// و همین نتیجه رو index.php و footer.php هم دوباره استفاده می‌کنن (به‌جای کوئری تکراری)
$nav_categories = isset($conn) ? (new Category())->all('id ASC') : [];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <title>گل‌آرا | فروشگاه لوازم آرایشی اورجینال</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>">
</head>
<body>

<div class="top-bar">
    <div class="container top-bar-inner">
        <span class="top-bar-text">پشتیبانی: ۰۹۹۰۹۵۹۲۵۱۴</span>
        <span class="top-bar-promo">ارسال رایگان برای خرید بالای ۲,۰۰۰,۰۰۰ تومان</span>
        <div class="top-bar-icons">
            <a href="<?php echo url('search.php'); ?>" class="icon-link" title="جستجو"><?php echo icon('search'); ?></a>
            <?php if (!empty($_SESSION['customer_id'])): ?>
                <a href="<?php echo url('account.php'); ?>" class="icon-link" title="حساب کاربری من (<?php echo clean_input($_SESSION['customer_name']); ?>)"><?php echo icon('user'); ?></a>
            <?php else: ?>
                <a href="<?php echo url('login.php'); ?>" class="icon-link" title="ورود / ثبت‌نام"><?php echo icon('user'); ?></a>
            <?php endif; ?>
            <a href="<?php echo url('wishlist.php'); ?>" class="icon-link" title="علاقه‌مندی‌ها">
                <?php echo icon('heart'); ?> <span class="icon-badge" id="wishlistBadge"><?php echo $wishlist_count; ?></span>
            </a>
            <a href="<?php echo url('cart.php'); ?>" class="icon-link" title="سبد خرید">
                <?php echo icon('shopping-bag'); ?> <span class="icon-badge" id="cartBadge"><?php echo $cart_count; ?></span>
            </a>
        </div>
    </div>
</div>

<input type="checkbox" id="navToggle" class="nav-toggle-checkbox">

<header class="site-header">
    <div class="container logo-row">
        <label for="navToggle" class="mobile-toggle" aria-label="منو"><?php echo icon('menu'); ?></label>
        <a href="<?php echo url('index.php'); ?>" class="logo">
            <span class="logo-text">گل‌آرا</span>
            <span class="logo-tagline">لوازم آرایشی · مراقبت پوست · عطر</span>
        </a>
        <span class="logo-spacer"></span>
    </div>
</header>

<nav class="main-nav">
    <ul>
        <li><a href="<?php echo url('index.php'); ?>">صفحه اصلی</a></li>
        <li class="has-dropdown">
            <a href="<?php echo url('products.php'); ?>">دسته‌بندی محصولات <span class="caret"><?php echo icon('chevron-down'); ?></span></a>
            <ul class="dropdown">
                <?php foreach ($nav_categories as $cat): ?>
                    <li><a href="<?php echo url('products.php?category=' . $cat['id']); ?>"><?php echo clean_input($cat['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </li>
        <li><a href="<?php echo url('search.php'); ?>">جستجو</a></li>
        <li><a href="<?php echo url('wishlist.php'); ?>">علاقه‌مندی‌ها</a></li>
        <li><a href="<?php echo url('contact.php'); ?>">تماس با ما</a></li>
    </ul>
</nav>

<main class="container">
