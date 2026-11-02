<?php
// هدرهای امنیتی؛ از config/db.php صدا زده می‌شه، پس روی همه صفحه‌ها و پاسخ‌های API اعمال می‌شه.
//
//  Content-Security-Policy : فقط اسکریپت‌ها و فونت‌های همین سایت اجرا می‌شن (جلوی XSS رو سخت‌تر می‌کنه).
//                            استایل inline مجازه چون تو بعضی المان‌ها style="..." داریم.
//                            form-action شامل درگاه پرداخت هم هست، چون بعد از ارسال فرم تسویه، مرورگر
//                            به آدرس درگاه redirect می‌شه و CSP روی redirect فرم هم اعمال می‌شه.
//  X-Frame-Options          : سایت داخل iframe سایت دیگه باز نمی‌شه (Clickjacking)
//  X-Content-Type-Options   : مرورگر نوع فایل رو حدس نمی‌زنه (عکس آپلودی هیچ‌وقت به‌عنوان اسکریپت اجرا نمی‌شه)
//  Referrer-Policy          : آدرس کامل صفحه فقط به خود سایت فرستاده می‌شه
//  Permissions-Policy       : دوربین، میکروفون و موقعیت مکانی بسته‌ان
//
// نسخه PHP: خود PHP هدر X-Powered-By رو می‌فرسته؛ اینجا حذف می‌شه.
// نسخه Apache (هدر Server) رو فقط تنظیم سرور عوض می‌کنه:  ServerTokens Prod  تو httpd.conf

function send_security_headers(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');

    $csp = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data:",
        "font-src 'self'",
        "connect-src 'self'",
        "form-action 'self' https://sandbox.zarinpal.com https://www.zarinpal.com https://zarinpal.com",
        "frame-ancestors 'self'",
        "base-uri 'self'",
        "object-src 'none'",
    ];

    header('Content-Security-Policy: ' . implode('; ', $csp));
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    if (is_https()) {
        // مرورگر تا یک سال فقط با HTTPS به این سایت وصل می‌شه
        header('Strict-Transport-Security: max-age=31536000');
    }
}
