<?php

namespace Api;

use JsonResponse;
use Request;

/**
 * دستیار هوشمند (Gemini)
 *   POST   /api/v1/assistant/messages  {message}  پیام کاربر → جواب مدل
 *   DELETE /api/v1/assistant/messages              شروع گفتگوی جدید
 */
class AssistantController extends BaseController
{
    public function __construct()
    {
        require_once __DIR__ . '/../../ai/config.php';
    }

    public function send(Request $request, array $params): void
    {
        $message = trim((string) $request->input('message', ''));

        if ($message === '') {
            JsonResponse::error('پیام خالی است.', 422, ['message' => 'پیام را بنویسید.']);
        }

        if (GEMINI_API_KEY === '' || GEMINI_API_KEY === 'PASTE_YOUR_API_KEY_HERE') {
            JsonResponse::error('کلید Gemini API هنوز در فایل .env تنظیم نشده.', 503);
        }

        $_SESSION['ai_chat_history'] = $_SESSION['ai_chat_history'] ?? [];

        // پیام کاربر رو به تاریخچه اضافه می‌کنیم تا مدل زمینه مکالمه رو داشته باشه
        $_SESSION['ai_chat_history'][] = ['role' => 'user', 'parts' => [['text' => $message]]];

        // فقط ۱۰ پیام آخر رو می‌فرستیم که درخواست سنگین نشه
        $history = array_slice($_SESSION['ai_chat_history'], -10);

        $body = [
            'system_instruction' => [
                'parts' => [
                    ['text' => 'تو دستیار مشاوره زیبایی فروشگاه اینترنتی لوازم آرایشی «گل‌آرا» هستی. به فارسی، دوستانه و کوتاه (حداکثر ۴ تا ۵ جمله) به سوالات مشتری‌ها درباره آرایش، مراقبت پوست و محصولات آرایشی جواب بده. اگه کسی سوال کاملاً بی‌ربطی پرسید، مودبانه بگو تخصصت فقط حوزه زیبایی و آرایشه.'],
                ],
            ],
            'contents' => $history,
        ];

        $ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // پیام ناموفق نباید تو تاریخچه بمونه، وگرنه گفتگوی بعدی خراب می‌شه
        if ($curlError || $httpCode !== 200) {
            array_pop($_SESSION['ai_chat_history']);
        }

        if ($curlError) {
            JsonResponse::error('خطا در اتصال به سرور هوش مصنوعی: ' . $curlError, 502);
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            JsonResponse::error($result['error']['message'] ?? ('خطای نامشخص از Gemini (کد ' . $httpCode . ')'), 502);
        }

        $reply = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$reply) {
            array_pop($_SESSION['ai_chat_history']);
            JsonResponse::error('جوابی از هوش مصنوعی دریافت نشد.', 502);
        }

        $_SESSION['ai_chat_history'][] = ['role' => 'model', 'parts' => [['text' => $reply]]];

        JsonResponse::success(['reply' => $reply]);
    }

    public function reset(Request $request, array $params): void
    {
        $_SESSION['ai_chat_history'] = [];

        JsonResponse::success(null, 'گفتگوی جدید شروع شد.');
    }
}
