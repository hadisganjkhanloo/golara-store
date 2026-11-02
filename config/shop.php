<?php
// مشخصات فروشگاه و قوانین ارسال. همه‌چیز از .env خونده می‌شه، پس شماره تلفن و ایمیل واقعی
// داخل کد (و ریپوی عمومی) نمی‌ره. هرچی خالی باشه تو سایت نشون داده نمی‌شه.

define('SHOP_NAME', trim($_ENV['SHOP_NAME'] ?? '') ?: 'گل‌آرا');
define('SHOP_PHONE', trim($_ENV['SHOP_PHONE'] ?? ''));        // مثلا 02112345678 (نمایش تو هدر و فوتر)
define('SHOP_WHATSAPP', trim($_ENV['SHOP_WHATSAPP'] ?? ''));  // مثلا 989121234567 (دکمه واتساپ؛ بدون + و صفر اول)
define('SHOP_EMAIL', trim($_ENV['SHOP_EMAIL'] ?? ''));
define('SHOP_ADDRESS', trim($_ENV['SHOP_ADDRESS'] ?? ''));

// ارسال رایگان از این مبلغ (تومان) به بالا؛ پایین‌تر از اون هزینه ثابت ارسال اضافه می‌شه
define('FREE_SHIPPING_THRESHOLD', (int) ($_ENV['FREE_SHIPPING_THRESHOLD'] ?? 2000000));
define('SHIPPING_COST', (int) ($_ENV['SHIPPING_COST'] ?? 80000));

// حداکثر تعداد از یک کالا تو یک سبد (جلوی قفل‌کردن کل موجودی انبار توسط یک نفر رو می‌گیره)
define('MAX_QTY_PER_ITEM', 10);

function shipping_cost_for(int $subtotalAfterDiscount): int
{
    return $subtotalAfterDiscount >= FREE_SHIPPING_THRESHOLD || $subtotalAfterDiscount <= 0 ? 0 : SHIPPING_COST;
}
