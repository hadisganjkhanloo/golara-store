<?php

namespace Api;

use JsonResponse;
use Mailer;
use Middleware;
use Newsletter;
use RateLimiter;
use Request;

/** POST /api/v1/newsletter {email} — درخواست عضویت در خبرنامه (با ایمیل تأیید) */
class NewsletterController extends BaseController
{
    public function subscribe(Request $request, array $params): void
    {
        $email = trim((string) $request->input('email', ''));

        if (!is_valid_email($email) || mb_strlen($email) > 150) {
            JsonResponse::error('ایمیل وارد شده معتبر نیست.', 422, ['email' => 'یک ایمیل معتبر وارد کنید.']);
        }

        // جلوگیری از سوءاستفاده (ایمیل‌بمب‌کردن یه نفر یا پر کردن لیست): هر IP ۵ درخواست و هر ایمیل ۳ درخواست در ساعت
        $ip = RateLimiter::ip();
        foreach ([['newsletter:ip', $ip, 5], ['newsletter:email', $email, 3]] as [$scope, $id, $max]) {
            if (RateLimiter::tooMany($scope, $id, $max, 3600)) {
                Middleware::tooManyRequests(RateLimiter::retryAfter($scope, $id, 3600));
            }
        }
        RateLimiter::hit('newsletter:ip', $ip);
        RateLimiter::hit('newsletter:email', $email);

        $result = (new Newsletter())->subscribe($email);

        if ($result['status'] === Newsletter::ALREADY) {
            JsonResponse::success(null, 'این ایمیل قبلاً در خبرنامه عضو و تأیید شده است.');
        }

        Newsletter::sendConfirmation($email, $result['token']);

        $message = 'ایمیل تأیید برایتان ارسال شد؛ برای فعال‌شدن عضویت روی لینک داخل آن بزنید.';
        if (Mailer::driver() === 'log') {
            $message .= ' (حالت توسعه: ایمیل در فایل storage/mail.log ذخیره شد.)';
        }

        JsonResponse::success(null, $message, 202);
    }
}
