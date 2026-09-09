<?php
// این فایل یه "اندپوینت" Ajax هست: نه هدر داره نه فوتر، فقط یه عدد ساده برمی‌گردونه.
session_start();

if (!isset($_SESSION['demo_count'])) {
    $_SESSION['demo_count'] = 0;
}

$_SESSION['demo_count']++;

// به‌جای echo کردن یه صفحه HTML کامل، فقط همین عدد رو چاپ می‌کنیم.
// جاوااسکریپت همینو می‌گیره و تو صفحه نشون می‌ده.
echo $_SESSION['demo_count'];
