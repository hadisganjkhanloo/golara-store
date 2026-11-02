<?php
// آدرس پایه سایت — هیچ‌جای پروژه نباید مسیر ثابتی مثل "/danshjoyar/..." داشته باشه.
//
// دو حالت:
//  ۱) اگه APP_URL تو .env پر شده باشه (مثلاً https://golara-store.test) همون استفاده می‌شه
//  ۲) اگه خالی باشه، از روی خود درخواست تشخیص داده می‌شه (برای localhost/پوشه یا دامنه .test هر دو جواب می‌ده)

// پشت پروکسی/لودبالانسر هم تشخیص می‌ده درخواست اصلی HTTPS بوده یا نه
function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

$appUrl = trim($_ENV['APP_URL'] ?? '');

if ($appUrl !== '') {
    $appUrl = rtrim($appUrl, '/');
    $basePath = rtrim((string) parse_url($appUrl, PHP_URL_PATH), '/');
} else {
    // مسیر پایه = آدرس اسکریپت درخواستی منهای مسیرش نسبت به ریشه پروژه
    // مثلاً SCRIPT_NAME=/danshjoyar/admin/login.php و فایل=<root>/admin/login.php  =>  /danshjoyar
    $basePath = '';
    $root = str_replace('\\', '/', dirname(__DIR__));
    $scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    if ($scriptFile !== '' && stripos($scriptFile, $root . '/') === 0) {
        $relative = substr($scriptFile, strlen($root));
        if (strcasecmp(substr($scriptName, -strlen($relative)), $relative) === 0) {
            $basePath = substr($scriptName, 0, -strlen($relative));
        }
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    $appUrl = (is_https() ? 'https' : 'http') . '://' . $host . $basePath;
}

define('BASE_PATH', $basePath);   // مثلاً '' یا '/danshjoyar'  (بدون / آخر)
define('APP_URL', $appUrl);       // مثلاً http://golara-store.test  (بدون / آخر)

// آدرس نسبی به ریشه سایت: برای href / src / fetch / redirect
// url('assets/css/style.css')  =>  /assets/css/style.css  یا  /danshjoyar/assets/css/style.css
function url(string $path = ''): string
{
    return BASE_PATH . '/' . ltrim($path, '/');
}

// آدرس کامل با دامنه: برای جاهایی که سرور بیرونی باید بهش برگرده (مثل callback درگاه پرداخت)
function absolute_url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

// آدرس فایل استاتیک همراه با زمان آخرین تغییرش (?v=...): بعد از هر تغییر CSS/JS مرورگر نسخه‌ی کهنه رو از کش نمی‌ده
function asset(string $path): string
{
    $file = dirname(__DIR__) . '/' . ltrim($path, '/');
    $version = is_file($file) ? filemtime($file) : 0;

    return url($path) . '?v=' . $version;
}
