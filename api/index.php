<?php
// نقطه ورود (Front Controller) همه درخواست‌های /api/...
// فایل api/.htaccess همه آدرس‌ها رو به اینجا می‌فرسته؛ Router با (متد + آدرس) کنترلر مناسب رو پیدا می‌کنه.
//
// بدون mod_rewrite هم کار می‌کنه:  /api/index.php/v1/products

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

use Api\AdminProductController;
use Api\AssistantController;
use Api\CartController;
use Api\CategoryController;
use Api\NewsletterController;
use Api\ProductController;
use Api\ReviewController;
use Api\SearchController;
use Api\StateController;
use Api\WishlistController;

// خطای پیش‌بینی‌نشده هم باید JSON باشه، نه صفحه HTML خطای PHP
set_exception_handler(function (Throwable $e) {
    error_log('API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());

    $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    JsonResponse::error($debug ? $e->getMessage() : 'خطای داخلی سرور. لطفاً دوباره تلاش کنید.', 500);
});

header('X-Content-Type-Options: nosniff');

$apiKey = [[Middleware::class, 'apiKey']];
$csrf = [[Middleware::class, 'csrf']];
$session = [[Middleware::class, 'session']];
$customer = [[Middleware::class, 'csrf'], [Middleware::class, 'customer']];
$admin = [[Middleware::class, 'csrf'], [Middleware::class, 'admin']];

$router = new Router('/v1');

// ---------- API عمومی ----------
// خواندن (GET) از هر دامنه‌ای مجازه (CORS). تغییرها (POST/PUT/DELETE) فقط با X-API-Key و بدون CORS:
// کلید باید سمت سرور نگه داشته بشه، نه تو جاوااسکریپتِ صفحه‌ی یه سایت دیگه.
$router->get('/products', [ProductController::class, 'index'], [], true);
$router->get('/products/{id}', [ProductController::class, 'show'], [], true);
$router->post('/products', [ProductController::class, 'store'], $apiKey);
$router->put('/products/{id}', [ProductController::class, 'update'], $apiKey);
$router->delete('/products/{id}', [ProductController::class, 'destroy'], $apiKey);

$router->get('/categories', [CategoryController::class, 'index'], [], true);
$router->get('/categories/{id}', [CategoryController::class, 'show'], [], true);
$router->post('/categories', [CategoryController::class, 'store'], $apiKey);
$router->put('/categories/{id}', [CategoryController::class, 'update'], $apiKey);
$router->delete('/categories/{id}', [CategoryController::class, 'destroy'], $apiKey);

// ---------- درخواست‌های خود سایت (با سشن؛ تغییرها با توکن CSRF) ----------
$router->get('/state', [StateController::class, 'show'], $session);

$router->get('/cart', [CartController::class, 'show'], $session);
$router->put('/cart/coupon', [CartController::class, 'applyCoupon'], $csrf);
$router->delete('/cart/coupon', [CartController::class, 'removeCoupon'], $csrf);
$router->post('/cart/items', [CartController::class, 'addItem'], $csrf);
$router->patch('/cart/items/{productId}', [CartController::class, 'updateItem'], $csrf);
$router->delete('/cart/items/{productId}', [CartController::class, 'removeItem'], $csrf);

$router->get('/wishlist', [WishlistController::class, 'index'], $session);
$router->put('/wishlist/{id}', [WishlistController::class, 'add'], $csrf);
$router->delete('/wishlist/{id}', [WishlistController::class, 'remove'], $csrf);

$router->get('/search', [SearchController::class, 'index'], $session);
$router->post('/newsletter', [NewsletterController::class, 'subscribe'], $csrf);
$router->post('/products/{id}/reviews', [ReviewController::class, 'store'], $customer);

$router->post('/assistant/messages', [AssistantController::class, 'send'], $csrf);
$router->delete('/assistant/messages', [AssistantController::class, 'reset'], $csrf);

// ---------- پنل مدیریت ----------
$router->post('/admin/products', [AdminProductController::class, 'store'], $admin);

$router->dispatch(new Request());
