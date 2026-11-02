<?php
// شروع امن سشن — همه صفحه‌ها به‌جای session_start() خام، این تابع رو صدا می‌زنن.
//
//  httponly  : جاوااسکریپت صفحه نمی‌تونه کوکی سشن رو بخونه (جلوی دزدیدنش با XSS رو می‌گیره)
//  secure    : کوکی فقط روی HTTPS فرستاده می‌شه. روی http://localhost یا http://xxx.test مرورگر
//              کوکی secure رو اصلاً قبول نمی‌کنه و ورود/سبد خرید کار نمی‌کنه، برای همین فقط وقتی
//              سایت واقعاً روی HTTPS هست (یا APP_URL با https شروع می‌شه) روشنش می‌کنیم.
//  samesite  : Lax = کوکی تو درخواست‌های ساختگی از سایت‌های دیگه (POST) فرستاده نمی‌شه، ولی وقتی
//              درگاه پرداخت کاربر رو با لینک عادی برمی‌گردونه هنوز فرستاده می‌شه (Strict اینجا
//              پرداخت رو خراب می‌کرد).
//  strict mode: شناسه سشنی که سرور نساخته باشه قبول نمی‌شه (جلوگیری از Session Fixation)

function start_secure_session(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $secure = is_https() || str_starts_with(APP_URL, 'https://');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    session_name('golara_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        // کوکی فقط برای مسیر همین پروژه؛ سشن این سایت با پروژه‌های دیگه روی همون دامنه قاطی نمی‌شه
        'path' => BASE_PATH === '' ? '/' : BASE_PATH . '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

// بعد از ورود موفق، شناسه سشن عوض می‌شه تا شناسه‌ای که قبل از ورود وجود داشت بی‌اعتبار بشه
function regenerate_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}
