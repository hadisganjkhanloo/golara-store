<?php

/**
 * Interface = یه قرارداد. هر کلاسی که این رو implements کنه، مجبوره
 * دقیقاً همین متدها رو با همین امضا (پارامتر و خروجی) داشته باشه.
 * فایده‌ش: اگه فردا IDPay یا Zibal رو هم اضافه کنیم، بقیه کد پروژه
 * (payment/request.php و callback.php) اصلاً نمی‌فهمه درگاه عوض شده -
 * همین یعنی Polymorphism.
 */
interface PaymentGatewayInterface
{
    public function request(int $amount, string $description, string $callbackUrl): array;

    public function verify(int $amount, string $authority): array;

    public function getPaymentUrl(string $authority): string;
}
