<?php
start_secure_session();
$cart_count = (new Cart())->count();
$wishlist_count = (new Wishlist())->count();

// بهینه‌سازی (فصل ۲۲): دسته‌بندی‌ها فقط یه‌بار در کل درخواست کوئری می‌شن،
// و همین نتیجه رو index.php و footer.php هم دوباره استفاده می‌کنن (به‌جای کوئری تکراری)
$nav_categories = isset($conn) ? (new Category())->all('id ASC') : [];

$seo = get_seo();
$page_title = $seo['title'] !== '' ? $seo['title'] . ' | ' . SHOP_NAME : SHOP_NAME . ' | فروشگاه لوازم آرایشی اورجینال';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo generate_csrf_token(); ?>">
    <meta name="api-url" content="<?php echo url('api/v1'); ?>">
    <meta name="theme-color" content="#c1536e">
    <title><?php echo clean_input($page_title); ?></title>
    <meta name="description" content="<?php echo clean_input($seo['description']); ?>">
    <link rel="canonical" href="<?php echo clean_input($seo['canonical']); ?>">
    <?php if ($seo['noindex']): ?>
        <meta name="robots" content="noindex, follow">
    <?php endif; ?>

    <meta property="og:site_name" content="<?php echo clean_input(SHOP_NAME); ?>">
    <meta property="og:locale" content="fa_IR">
    <meta property="og:type" content="<?php echo clean_input($seo['type']); ?>">
    <meta property="og:title" content="<?php echo clean_input($page_title); ?>">
    <meta property="og:description" content="<?php echo clean_input($seo['description']); ?>">
    <meta property="og:url" content="<?php echo clean_input($seo['canonical']); ?>">
    <meta property="og:image" content="<?php echo clean_input($seo['image']); ?>">
    <meta name="twitter:card" content="summary_large_image">

    <link rel="icon" type="image/svg+xml" href="<?php echo url('assets/img/favicon.svg'); ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo url('assets/img/favicon-32.png'); ?>">
    <link rel="apple-touch-icon" href="<?php echo url('assets/img/apple-touch-icon.png'); ?>">

    <!-- فونت از خود سایت سرو می‌شه (نه Google Fonts)؛ تو ایران کند یا فیلتر نمی‌شه و رندر صفحه رو معطل نمی‌کنه -->
    <link rel="preload" href="<?php echo url('assets/fonts/Vazirmatn-FD-Regular.woff2'); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="<?php echo url('assets/fonts/Vazirmatn-FD-Bold.woff2'); ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?php echo asset('assets/css/style.css'); ?>">

    <?php foreach ($seo['jsonld'] as $schema): ?>
        <script type="application/ld+json"><?php echo json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?></script>
    <?php endforeach; ?>
</head>
<body>
<a class="skip-link" href="#main-content">پرش به محتوای اصلی</a>

<div class="top-bar">
    <div class="container top-bar-inner">
        <span class="top-bar-text"><?php echo SHOP_PHONE !== '' ? 'پشتیبانی: ' . clean_input(SHOP_PHONE) : ''; ?></span>
        <span class="top-bar-promo">ارسال رایگان برای خرید بالای <?php echo number_format(FREE_SHIPPING_THRESHOLD); ?> تومان</span>
        <div class="top-bar-icons">
            <a href="<?php echo url('search.php'); ?>" class="icon-link" aria-label="جستجو"><?php echo icon('search'); ?></a>
            <?php if (!empty($_SESSION['customer_id'])): ?>
                <a href="<?php echo url('account.php'); ?>" class="icon-link" aria-label="حساب کاربری من (<?php echo clean_input($_SESSION['customer_name']); ?>)"><?php echo icon('user'); ?></a>
            <?php else: ?>
                <a href="<?php echo url('login.php'); ?>" class="icon-link" aria-label="ورود / ثبت‌نام"><?php echo icon('user'); ?></a>
            <?php endif; ?>
            <a href="<?php echo url('wishlist.php'); ?>" class="icon-link" aria-label="علاقه‌مندی‌ها">
                <?php echo icon('heart'); ?> <span class="icon-badge" id="wishlistBadge"><?php echo $wishlist_count; ?></span>
            </a>
            <a href="<?php echo url('cart.php'); ?>" class="icon-link" aria-label="سبد خرید">
                <?php echo icon('shopping-bag'); ?> <span class="icon-badge" id="cartBadge"><?php echo $cart_count; ?></span>
            </a>
        </div>
    </div>
</div>

<input type="checkbox" id="navToggle" class="nav-toggle-checkbox">

<header class="site-header">
    <div class="container logo-row">
        <label for="navToggle" class="mobile-toggle" aria-label="منو" role="button" tabindex="0"><?php echo icon('menu'); ?></label>
        <a href="<?php echo url('index.php'); ?>" class="logo">
            <span class="logo-text"><?php echo clean_input(SHOP_NAME); ?></span>
            <span class="logo-tagline">لوازم آرایشی · مراقبت پوست · عطر</span>
        </a>
        <span class="logo-spacer"></span>
    </div>
</header>

<nav class="main-nav" aria-label="منوی اصلی">
    <ul>
        <li><a href="<?php echo url('index.php'); ?>">صفحه اصلی</a></li>
        <li class="has-dropdown">
            <a href="<?php echo url('products.php'); ?>" aria-haspopup="true">دسته‌بندی محصولات <span class="caret"><?php echo icon('chevron-down'); ?></span></a>
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

<main class="container" id="main-content" tabindex="-1">
