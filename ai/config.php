<?php
// تنظیمات اتصال به Gemini (هوش مصنوعی گوگل)
// از فصل ۲۰: کلید واقعی دیگه اینجا نیست، از فایل .env خونده می‌شه (که هیچ‌وقت commit نمی‌شه)

define('GEMINI_API_KEY', $_ENV['GEMINI_API_KEY'] ?? '');
define('GEMINI_MODEL', $_ENV['GEMINI_MODEL'] ?? 'gemini-3.6-flash');
define('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent');
