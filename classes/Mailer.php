<?php

/**
 * ارسال ایمیل (بازیابی رمز، تأیید خبرنامه).
 *
 *  MAIL_DRIVER=log   (پیش‌فرض) ایمیل‌ها به‌جای ارسال، تو فایل storage/mail.log نوشته می‌شن؛
 *                    برای توسعه و تست، چون روی XAMPP سرور ایمیل نصب نیست
 *  MAIL_DRIVER=mail  با تابع mail() خود PHP ارسال می‌شه (روی هاست واقعی)
 *  MAIL_FROM         آدرس فرستنده
 */
class Mailer
{
    public static function driver(): string
    {
        return strtolower(trim($_ENV['MAIL_DRIVER'] ?? 'log')) === 'mail' ? 'mail' : 'log';
    }

    public static function send(string $to, string $subject, string $body): bool
    {
        // جلوگیری از Header Injection: توی ایمیل گیرنده و موضوع، خط جدید ممنوعه
        $to = str_replace(["\r", "\n"], '', $to);
        $subject = str_replace(["\r", "\n"], ' ', $subject);

        if (self::driver() === 'mail') {
            $from = str_replace(["\r", "\n"], '', $_ENV['MAIL_FROM'] ?? ('no-reply@' . parse_url(APP_URL, PHP_URL_HOST)));
            $headers = "From: $from\r\nContent-Type: text/plain; charset=UTF-8\r\n";

            return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
        }

        $dir = dirname(__DIR__) . '/storage';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $entry = '[' . date('Y-m-d H:i:s') . "] TO: $to\nSUBJECT: $subject\n$body\n" . str_repeat('-', 60) . "\n";

        return file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
    }
}
