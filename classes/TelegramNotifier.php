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
        $message = "🛍 <b>سفارش جدید تو گل‌آرا!</b>\n\n";
        $message .= "شماره سفارش: #{$order['id']}\n";
        $message .= "مبلغ: " . number_format($order['total_amount']) . " تومان\n\n";
        $message .= "محصولات:\n";

        foreach ($items as $item) {
            $message .= "• {$item['product_name']} × {$item['quantity']}\n";
        }

        if (!empty($order['ref_id'])) {
            $message .= "\nکد پیگیری: {$order['ref_id']}";
        }

        return $this->send($message);
    }
}
