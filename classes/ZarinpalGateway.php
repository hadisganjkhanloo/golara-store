<?php

class ZarinpalGateway implements PaymentGatewayInterface
{
    private string $merchantId;
    private string $requestUrl;
    private string $verifyUrl;
    private string $startPayUrl;

    public function __construct(string $merchantId, string $requestUrl, string $verifyUrl, string $startPayUrl)
    {
        $this->merchantId = $merchantId;
        $this->requestUrl = $requestUrl;
        $this->verifyUrl = $verifyUrl;
        $this->startPayUrl = $startPayUrl;
    }

    public function request(int $amount, string $description, string $callbackUrl): array
    {
        $data = [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'currency' => 'IRT',
            'description' => $description,
            'callback_url' => $callbackUrl,
        ];

        $result = $this->post($this->requestUrl, $data);
        $authority = $result['data']['authority'] ?? null;

        if (!$authority) {
            return ['success' => false, 'authority' => null];
        }

        return ['success' => true, 'authority' => $authority];
    }

    public function verify(int $amount, string $authority): array
    {
        $data = [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'currency' => 'IRT',
            'authority' => $authority,
        ];

        $result = $this->post($this->verifyUrl, $data);
        $code = $result['data']['code'] ?? null;

        // کد ۱۰۰ یعنی تایید موفق، کد ۱۰۱ یعنی قبلاً هم تایید شده بوده (بازم موفقه)
        if ($code === 100 || $code === 101) {
            return ['success' => true, 'ref_id' => $result['data']['ref_id'] ?? null];
        }

        return ['success' => false, 'ref_id' => null];
    }

    public function getPaymentUrl(string $authority): string
    {
        return $this->startPayUrl . $authority;
    }

    private function post(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }
}
