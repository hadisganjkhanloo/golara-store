<?php

/**
 * چک‌هایی که قبل از اجرای کنترلر انجام می‌شن؛ اگه شرط برقرار نباشه همون‌جا
 * جواب خطای JSON داده می‌شه و کنترلر اصلاً اجرا نمی‌شه.
 */
class Middleware
{
    // مسیرهای تغییردهنده در API عمومی: هدر X-API-Key باید یکی از کلیدهای جدول api_keys باشه
    public static function apiKey(Request $request): void
    {
        $key = $request->header('X-API-Key');

        if ($key === '') {
            JsonResponse::error('کلید API ارسال نشده. هدر X-API-Key را اضافه کنید.', 401);
        }

        // حدس‌زدن کلید: بیشتر از ۲۰ کلید اشتباه تو ۱۰ دقیقه از یک IP = مسدود
        $ip = RateLimiter::ip();
        if (RateLimiter::tooMany('apikey:fail', $ip, 20, 600)) {
            self::tooManyRequests(RateLimiter::retryAfter('apikey:fail', $ip, 600));
        }

        $row = (new ApiKey())->verify($key);

        if (!$row) {
            RateLimiter::hit('apikey:fail', $ip);
            JsonResponse::error('کلید API نامعتبر است.', 401);
        }

        // هر کلید در دقیقه حداکثر ۱۲۰ درخواست تغییردهنده
        $identifier = 'key' . $row['id'];
        if (RateLimiter::tooMany('api:key', $identifier, 120, 60)) {
            self::tooManyRequests(RateLimiter::retryAfter('api:key', $identifier, 60));
        }
        RateLimiter::hit('api:key', $identifier);
    }

    public static function tooManyRequests(int $retryAfterSeconds): void
    {
        header('Retry-After: ' . $retryAfterSeconds);
        JsonResponse::error('تعداد درخواست‌ها بیش از حد مجاز است. ' . RateLimiter::humanWait($retryAfterSeconds) . ' دیگر دوباره تلاش کنید.', 429);
    }

    // درخواست‌هایی که از خود صفحه‌های سایت (با سشن) میان باید توکن CSRF درست داشته باشن
    public static function csrf(Request $request): void
    {
        start_secure_session();

        if (!csrf_is_valid($request->header('X-CSRF-Token') ?: (string) $request->input('csrf_token', ''))) {
            JsonResponse::error('نشست شما منقضی شده؛ صفحه را رفرش کنید و دوباره تلاش کنید.', 403);
        }
    }

    public static function customer(Request $request): void
    {
        start_secure_session();

        if (empty($_SESSION['customer_id'])) {
            JsonResponse::error('برای این کار باید وارد حساب کاربری شوید.', 401);
        }
    }

    public static function admin(Request $request): void
    {
        start_secure_session();

        if (empty($_SESSION['admin_id'])) {
            JsonResponse::error('دسترسی غیرمجاز. لطفاً دوباره وارد پنل مدیریت شوید.', 401);
        }
    }

    // درخواست‌های سشن‌دار بدون این، اصلاً سشن شروع نمی‌کنن؛ برای کنترلرهایی که سبد/علاقه‌مندی دارن
    public static function session(Request $request): void
    {
        start_secure_session();
    }
}
