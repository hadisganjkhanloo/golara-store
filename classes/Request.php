<?php

/**
 * یه پوشش کوچیک دور $_GET / $_POST / بدنه JSON / هدرها،
 * تا کنترلرها مستقیم با ابرسراسری‌ها کار نکنن.
 */
class Request
{
    private ?array $json = null;

    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /**
     * مسیر درخواست نسبت به API، مثلاً /v1/products/5
     * هم برای /api/v1/products/5 (با rewrite) و هم /api/index.php/v1/products/5 (بدون rewrite) کار می‌کنه
     */
    public function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $uri = rawurldecode($uri);

        if (BASE_PATH !== '' && str_starts_with($uri, BASE_PATH)) {
            $uri = substr($uri, strlen(BASE_PATH));
        }

        $uri = preg_replace('#^/api(/index\.php)?#', '', $uri);

        return '/' . trim($uri, '/');
    }

    public function query(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    // بدنه JSON (Content-Type: application/json) به آرایه تبدیل می‌شه؛ اگه JSON نبود، آرایه خالی
    public function json(): array
    {
        if ($this->json === null) {
            $decoded = json_decode(file_get_contents('php://input') ?: '', true);
            $this->json = is_array($decoded) ? $decoded : [];
        }

        return $this->json;
    }

    // مقدار ورودی: اول بدنه JSON، بعد فرم (POST)، بعد کوئری‌استرینگ
    public function input(string $key, $default = null)
    {
        return $this->json()[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST, $this->json());
    }

    public function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public function header(string $name): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return (string) ($_SERVER[$key] ?? '');
    }
}
