<?php
// robots.txt پویا (آدرس sitemap باید کامل و با دامنه‌ی واقعی باشه؛ برای همین فایل ثابت نیست).
// آدرس /robots.txt با rewrite تو .htaccess به این فایل می‌رسه.

require_once __DIR__ . '/config/db.php';

header('Content-Type: text/plain; charset=utf-8');

$lines = [
    'User-agent: *',
    'Allow: /',
    // بخش‌های خصوصی یا بی‌ارزش برای موتور جستجو
    'Disallow: ' . url('admin/'),
    'Disallow: ' . url('api/'),
    'Disallow: ' . url('cart.php'),
    'Disallow: ' . url('checkout.php'),
    'Disallow: ' . url('account.php'),
    'Disallow: ' . url('orders.php'),
    'Disallow: ' . url('order.php'),
    'Disallow: ' . url('payment/'),
    'Disallow: ' . url('login.php'),
    'Disallow: ' . url('register.php'),
    'Disallow: ' . url('wishlist.php'),
    'Disallow: ' . url('search.php'),
    '',
    'Sitemap: ' . absolute_url('sitemap.xml'),
];

echo implode("\n", $lines) . "\n";
