<?php
// تنظیمات درگاه پرداخت زرین‌پال - محیط Sandbox (تست، بدون پول واقعی)
// وقتی سایت واقعی روی هاست رفت، فقط کافیه merchant_id واقعی رو تو .env بذاری
// و آدرس‌ها رو از sandbox.zarinpal.com به www.zarinpal.com عوض کنی

define('ZARINPAL_MERCHANT_ID', $_ENV['ZARINPAL_MERCHANT_ID'] ?? '00000000-0000-0000-0000-000000000000');
define('ZARINPAL_REQUEST_URL', 'https://sandbox.zarinpal.com/pg/v4/payment/request.json');
define('ZARINPAL_VERIFY_URL', 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json');
define('ZARINPAL_STARTPAY_URL', 'https://sandbox.zarinpal.com/pg/StartPay/');
define('ZARINPAL_CALLBACK_URL', 'http://localhost/danshjoyar/payment/callback.php');
