<?php

/**
 * قالب یکدست خروجی همه اندپوینت‌های JSON (هم API عمومی، هم درخواست‌های Ajax خود سایت).
 *
 * موفق:  { "success": true,  "message": "...", "data": { ... } }
 * ناموفق: { "success": false, "message": "...", "errors": { "field": "..." } }
 *
 * کد وضعیت HTTP هم درست تنظیم می‌شه (۲۰۰/۲۰۱ موفق، ۴۰۰ ورودی غلط، ۴۰۱ ورود لازم،
 * ۴۰۳ دسترسی نیست، ۴۰۴ پیدا نشد، ۴۰۹ تداخل مثل کمبود موجودی، ۴۲۲ اعتبارسنجی)
 * پس سمت جاوااسکریپت می‌شه فقط با response.ok تشخیص داد کار انجام شده یا نه.
 */
class JsonResponse
{
    public static function success($data = null, string $message = '', int $status = 200): void
    {
        self::send(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, array $errors = [], $data = null): void
    {
        $payload = ['success' => false, 'message' => $message];

        if (!empty($errors)) {
            $payload['errors'] = $errors;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }

        self::send($payload, $status);
    }

    public static function noContent(): void
    {
        if (defined('JSON_RESPONSE_THROW')) {
            throw new JsonResponseSent([], 204);
        }

        http_response_code(204);
        exit;
    }

    private static function send(array $payload, int $status): void
    {
        // تو تست‌های خودکار (tests/run.php) به‌جای exit یه استثنا پرتاب می‌شه تا خروجی قابل بررسی باشه
        if (defined('JSON_RESPONSE_THROW')) {
            throw new JsonResponseSent($payload, $status);
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
