<?php
// اندپوینت Ajax برای چت با دستیار هوشمند گل‌آرا (Gemini)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../ai/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

verify_csrf_token();

if (!isset($_SESSION['ai_chat_history'])) {
    $_SESSION['ai_chat_history'] = [];
}

// پاک کردن تاریخچه گفتگو (شروع مکالمه جدید)
if (isset($_POST['reset'])) {
    $_SESSION['ai_chat_history'] = [];
    echo json_encode(['success' => true]);
    exit;
}

$message = trim($_POST['message'] ?? '');

if ($message === '') {
    echo json_encode(['success' => false, 'error' => 'پیام خالی است.']);
    exit;
}

if (GEMINI_API_KEY === 'PASTE_YOUR_API_KEY_HERE' || GEMINI_API_KEY === '') {
    echo json_encode(['success' => false, 'error' => 'کلید Gemini API هنوز تو فایل ai/config.php تنظیم نشده.']);
    exit;
}

// پیام کاربر رو به تاریخچه اضافه می‌کنیم تا مدل زمینه مکالمه رو داشته باشه
$_SESSION['ai_chat_history'][] = ['role' => 'user', 'parts' => [['text' => $message]]];

// فقط ۱۰ پیام آخر رو می‌فرستیم که درخواست سنگین نشه
$history = array_slice($_SESSION['ai_chat_history'], -10);

$request_body = [
    'system_instruction' => [
        'parts' => [
            ['text' => 'تو دستیار مشاوره زیبایی فروشگاه اینترنتی لوازم آرایشی «گل‌آرا» هستی. به فارسی، دوستانه و کوتاه (حداکثر ۴ تا ۵ جمله) به سوالات مشتری‌ها درباره آرایش، مراقبت پوست و محصولات آرایشی جواب بده. اگه کسی سوال کاملاً بی‌ربطی پرسید، مودبانه بگو تخصصت فقط حوزه زیبایی و آرایشه.'],
        ],
    ],
    'contents' => $history,
];

$ch = curl_init(GEMINI_API_URL . '?key=' . GEMINI_API_KEY);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_body));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
$curl_error = curl_error($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curl_error) {
    echo json_encode(['success' => false, 'error' => 'خطا در اتصال به سرور هوش مصنوعی: ' . $curl_error]);
    exit;
}

$result = json_decode($response, true);

if ($http_code !== 200) {
    $api_error = $result['error']['message'] ?? ('خطای نامشخص از Gemini (کد ' . $http_code . ')');
    echo json_encode(['success' => false, 'error' => $api_error]);
    exit;
}

$reply = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$reply) {
    echo json_encode(['success' => false, 'error' => 'جوابی از هوش مصنوعی دریافت نشد.']);
    exit;
}

// جواب مدل رو هم به تاریخچه اضافه می‌کنیم تا سوال بعدی زمینه داشته باشه
$_SESSION['ai_chat_history'][] = ['role' => 'model', 'parts' => [['text' => $reply]]];

echo json_encode(['success' => true, 'reply' => $reply]);
