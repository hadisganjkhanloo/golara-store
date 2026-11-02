<?php

/**
 * محدودکردن تعداد تلاش‌ها (Rate Limit) با ذخیره‌سازی تو دیتابیس.
 *
 * شمارنده‌ی کپچای قبلی داخل سشن بود؛ ربات کافیه کوکی نفرسته تا همیشه یه سشن تازه (و شمارنده صفر) داشته باشه.
 * اینجا شمارش بر اساس آدرس IP و هویت هدف (ایمیل / نام کاربری) تو دیتابیسه، پس با پاک‌کردن کوکی دور زده نمی‌شه.
 *
 *   if (RateLimiter::tooMany('login:ip', RateLimiter::ip(), 20, 900)) { ... }   // ۲۰ تلاش در ۱۵ دقیقه
 *   RateLimiter::hit('login:ip', RateLimiter::ip());
 */
class RateLimiter
{
    public static function ip(): string
    {
        // فقط REMOTE_ADDR؛ هدر X-Forwarded-For رو کاربر می‌تونه جعل کنه، مگر اینکه پشت پروکسی مورد اعتماد باشیم
        return $_SERVER['REMOTE_ADDR'] ?? 'cli';
    }

    public static function hit(string $scope, string $identifier): void
    {
        $conn = Database::getConnection();
        $identifier = mb_strtolower(mb_substr($identifier, 0, 190));

        $stmt = mysqli_prepare($conn, "INSERT INTO rate_limits (scope, identifier) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ss', $scope, $identifier);
        mysqli_stmt_execute($stmt);

        // هر از گاهی ردیف‌های قدیمی پاک می‌شن تا جدول بزرگ نشه
        if (random_int(1, 100) === 1) {
            mysqli_query($conn, "DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)");
        }
    }

    public static function count(string $scope, string $identifier, int $windowSeconds): int
    {
        $conn = Database::getConnection();
        $identifier = mb_strtolower(mb_substr($identifier, 0, 190));

        $stmt = mysqli_prepare(
            $conn,
            "SELECT COUNT(*) AS c FROM rate_limits WHERE scope = ? AND identifier = ? AND created_at > (NOW() - INTERVAL ? SECOND)"
        );
        mysqli_stmt_bind_param($stmt, 'ssi', $scope, $identifier, $windowSeconds);
        mysqli_stmt_execute($stmt);

        return (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
    }

    public static function tooMany(string $scope, string $identifier, int $max, int $windowSeconds): bool
    {
        return self::count($scope, $identifier, $windowSeconds) >= $max;
    }

    // چند ثانیه دیگه تا قدیمی‌ترین تلاشِ داخل پنجره منقضی بشه (برای پیام «N دقیقه دیگه امتحان کن»)
    public static function retryAfter(string $scope, string $identifier, int $windowSeconds): int
    {
        $conn = Database::getConnection();
        $identifier = mb_strtolower(mb_substr($identifier, 0, 190));

        $stmt = mysqli_prepare(
            $conn,
            "SELECT TIMESTAMPDIFF(SECOND, NOW(), MIN(created_at) + INTERVAL ? SECOND) AS s
             FROM rate_limits WHERE scope = ? AND identifier = ? AND created_at > (NOW() - INTERVAL ? SECOND)"
        );
        mysqli_stmt_bind_param($stmt, 'issi', $windowSeconds, $scope, $identifier, $windowSeconds);
        mysqli_stmt_execute($stmt);
        $seconds = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['s'] ?? 0);

        return max(1, $seconds);
    }

    // بعد از ورود موفق، شمارنده‌ی اون هویت صفر می‌شه
    public static function clear(string $scope, string $identifier): void
    {
        $stmt = mysqli_prepare(Database::getConnection(), "DELETE FROM rate_limits WHERE scope = ? AND identifier = ?");
        $identifier = mb_strtolower(mb_substr($identifier, 0, 190));
        mysqli_stmt_bind_param($stmt, 'ss', $scope, $identifier);
        mysqli_stmt_execute($stmt);
    }

    public static function humanWait(int $seconds): string
    {
        return $seconds >= 60 ? ceil($seconds / 60) . ' دقیقه' : $seconds . ' ثانیه';
    }
}
