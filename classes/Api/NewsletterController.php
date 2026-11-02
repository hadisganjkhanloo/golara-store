<?php

namespace Api;

use JsonResponse;
use Newsletter;
use Request;

/** POST /api/v1/newsletter {email} — عضویت در خبرنامه */
class NewsletterController extends BaseController
{
    public function subscribe(Request $request, array $params): void
    {
        $email = trim((string) $request->input('email', ''));

        if (!is_valid_email($email) || mb_strlen($email) > 150) {
            JsonResponse::error('ایمیل وارد شده معتبر نیست.', 422, ['email' => 'یک ایمیل معتبر وارد کنید.']);
        }

        if ((new Newsletter())->subscribe($email)) {
            JsonResponse::success(null, 'با موفقیت در خبرنامه عضو شدید. 🌸', 201);
        }

        JsonResponse::success(null, 'این ایمیل قبلاً در خبرنامه عضو شده است.');
    }
}
