<?php
// تنظیمات ربات تلگرام - برای اطلاع‌رسانی سفارش جدید به ادمین
// توکن و Chat ID از .env خونده می‌شن (هیچ‌وقت commit نمی‌شن)

define('TELEGRAM_BOT_TOKEN', $_ENV['TELEGRAM_BOT_TOKEN'] ?? '');
define('TELEGRAM_ADMIN_CHAT_ID', $_ENV['TELEGRAM_ADMIN_CHAT_ID'] ?? '');
