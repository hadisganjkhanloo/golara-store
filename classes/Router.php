<?php

/**
 * روتر ساده: به‌جای اینکه برای هر آدرس یه فایل جدا داشته باشیم (products.php, categories.php, ...)
 * همه درخواست‌های /api/... وارد api/index.php می‌شن و اینجا با (متد HTTP + الگوی آدرس)
 * به یه متد از کنترلر وصل می‌شن:
 *
 *   $router->get('/products/{id}', [ProductController::class, 'show']);
 *
 * - {id} هر بخشی از آدرس رو می‌گیره و به‌صورت پارامتر به کنترلر می‌ده
 * - آدرسی که نباشه ۴۰۴، آدرسی که باشه ولی متدش نه ۴۰۵ (با هدر Allow) برمی‌گردونه
 * - هر مسیر می‌تونه middleware داشته باشه (مثلاً چک کلید API یا توکن CSRF) که قبل از کنترلر اجرا می‌شه
 */
class Router
{
    private array $routes = [];

    public function __construct(private string $prefix = '')
    {
    }

    public function get(string $pattern, array $handler, array $middleware = [], bool $cors = false): void
    {
        $this->add('GET', $pattern, $handler, $middleware, $cors);
    }

    public function post(string $pattern, array $handler, array $middleware = [], bool $cors = false): void
    {
        $this->add('POST', $pattern, $handler, $middleware, $cors);
    }

    public function put(string $pattern, array $handler, array $middleware = [], bool $cors = false): void
    {
        $this->add('PUT', $pattern, $handler, $middleware, $cors);
    }

    public function patch(string $pattern, array $handler, array $middleware = [], bool $cors = false): void
    {
        $this->add('PATCH', $pattern, $handler, $middleware, $cors);
    }

    public function delete(string $pattern, array $handler, array $middleware = [], bool $cors = false): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware, $cors);
    }

    private function add(string $method, string $pattern, array $handler, array $middleware, bool $cors): void
    {
        $full = $this->prefix . '/' . trim($pattern, '/');
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', rtrim($full, '/')) . '/?$#';

        $this->routes[] = [
            'method' => $method,
            'regex' => $regex,
            'handler' => $handler,
            'middleware' => $middleware,
            'cors' => $cors,
        ];
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();
        $method = $request->method();

        $allowed = [];
        $corsAllowed = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            if ($route['method'] === $method) {
                if ($route['cors']) {
                    $this->sendCorsHeaders();
                }

                foreach ($route['middleware'] as $middleware) {
                    call_user_func($middleware, $request);
                }

                [$class, $action] = $route['handler'];
                (new $class())->$action($request, $params);
                return;
            }

            $allowed[] = $route['method'];
            $corsAllowed = $corsAllowed || $route['cors'];
        }

        // درخواست OPTIONS مرورگر (preflight) برای فراخوانی از دامنه‌های دیگه
        if ($method === 'OPTIONS' && !empty($allowed)) {
            if ($corsAllowed) {
                $this->sendCorsHeaders();
            }
            header('Allow: ' . implode(', ', array_unique($allowed)));
            JsonResponse::noContent();
        }

        if (!empty($allowed)) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            JsonResponse::error('این متد برای این آدرس پشتیبانی نمی‌شود.', 405);
        }

        JsonResponse::error('آدرس مورد نظر پیدا نشد.', 404);
    }

    private function sendCorsHeaders(): void
    {
        // فقط خواندن (GET) از دامنه‌های دیگه مجازه؛ تغییرها (با X-API-Key) عمداً CORS ندارن
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
    }
}
