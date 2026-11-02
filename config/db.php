<?php
// اتصال به دیتابیس با mysqli
// از فصل ۱۹ به بعد، اتصال واقعی از طریق کلاس Database (Singleton) مدیریت می‌شه
// از فصل ۲۰ به بعد، autoload دستی رو با Composer عوض کردیم و رمزها رو از .env می‌خونیم
// $conn همچنان در دسترسه تا صفحاتی که هنوز procedural نوشته شدن بدون تغییر کار کنن

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// آدرس پایه سایت (url / absolute_url) — باید بعد از لود شدن .env بیاد چون APP_URL رو از اونجا می‌خونه
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/shop.php';
require_once __DIR__ . '/security.php';

// هدرهای امنیتی روی همه صفحه‌ها و پاسخ‌های API (باید قبل از هر خروجی ارسال بشن)
send_security_headers();

$conn = Database::getConnection();
