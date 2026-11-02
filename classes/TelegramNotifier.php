<?php

/**
 * این کلاس فقط پیام می‌فرسته (Outbound) - منتظر پیام از تلگرام نمی‌مونه.
 * برای همین رو localhost هم بدون هیچ تنظیمات اضافه‌ای کار می‌کنه؛
 * چون همیشه *ما* هستیم که به سرور تلگرام درخواست می‌فرستیم، نه برعکس.
 */
class TelegramNotifier
{
    private string $token;
    private string $chatId;

    public function __construct(string $token, string $chatId)
    {
        $this->token = $token;
        $this->chatId = $chatId;
    }

    public function send(string $message): bool
    {
        if ($this->token === '' || $this->chatId === '') {
            return false;
        }

        $url = "https://api.telegram.org/bot{$this->token}/sendMessage";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'chat_id' => $this->chatId,
            'text' => $message,
            'parse_mode' => 'HTML',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        curl_close($ch);

        $result = json_decode($response, true);
        return $result['ok'] ?? false;
    }

    public function sendNewOrderNotification(array $order, array $items): bool
    {
        // parse_mode=HTML است؛ متن‌هایی که از کاربر میان (نام، آدرس) باید escape بشن
        $e = fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');

        $message = "🛍 <b>سفارش جدید تو گل‌آرا!</b>

";
        $message .= "شماره سفارش: #{$order['id']}
";
        $message .= "مبلغ: " . number_format($order['total_amount']) . " تومان
";

        if (!empty($order['discount_amount'])) {
            $message .= "تخفیف: " . number_format($order['discount_amount']) . " تومان (" . $e($order['coupon_code']) . ")
";
        }

        $message .= "
محصولات:
";

        foreach ($items as $item) {
            $message .= "• " . $e($item['product_name']) . " × {$item['quantity']}
";
        }

        if (!empty($order['shipping_address'])) {
            $message .= "
📦 <b>ارسال به:</b>
";
            $message .= $e($order['shipping_name']) . " — " . $e($order['shipping_phone']) . "
";
            $message .= $e($order['shipping_city']) . "، " . $e($order['shipping_address']) . "
";
            $message .= "کدپستی: " . $e($order['shipping_postal_code']) . "
";
        }

        if (!empty($order['ref_id'])) {
            $message .= "
کد پیگیری: " . $e($order['ref_id']);
        }

        return $this->send($message);
    }
}
