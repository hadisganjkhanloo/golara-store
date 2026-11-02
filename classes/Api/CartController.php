<?php

namespace Api;

use Cart;
use JsonResponse;
use Middleware;
use RateLimiter;
use Request;

/**
 * سبد خرید (کاربر واردشده: دیتابیس، مهمان: سشن)
 *   GET    /api/v1/cart                    محتوای سبد (همراه با html جدول)
 *   POST   /api/v1/cart/items              افزودن   {product_id, quantity?}
 *   PATCH  /api/v1/cart/items/{productId}  تغییر تعداد {quantity}
 *   DELETE /api/v1/cart/items/{productId}  حذف
 */
class CartController extends BaseController
{
    public function show(Request $request, array $params): void
    {
        JsonResponse::success($this->cartPayload(new Cart(), true));
    }

    public function addItem(Request $request, array $params): void
    {
        $productId = (int) $request->input('product_id', 0);
        $quantity = max(1, (int) $request->input('quantity', 1));

        if ($productId <= 0) {
            JsonResponse::error('اطلاعات ارسالی معتبر نیست.', 422, ['product_id' => 'شناسه محصول الزامی است.']);
        }

        $cart = new Cart();
        $result = $cart->add($productId, $quantity);

        $this->respond($cart, $result, 'به سبد خرید اضافه شد.', 201);
    }

    public function updateItem(Request $request, array $params): void
    {
        $quantity = $this->intOrNull($request->input('quantity'));

        if ($quantity === null) {
            JsonResponse::error('اطلاعات ارسالی معتبر نیست.', 422, ['quantity' => 'تعداد باید عدد صحیح نامنفی باشد.']);
        }

        $cart = new Cart();
        $result = $cart->setQuantity((int) $params['productId'], $quantity);

        $this->respond($cart, $result, 'سبد خرید به‌روز شد.', 200, true);
    }

    public function removeItem(Request $request, array $params): void
    {
        $cart = new Cart();
        $cart->remove((int) $params['productId']);

        JsonResponse::success($this->cartPayload($cart, true), 'از سبد خرید حذف شد.');
    }

    // PUT /cart/coupon {code}  — اعمال کد تخفیف
    public function applyCoupon(Request $request, array $params): void
    {
        $code = trim((string) $request->input('code', ''));

        // حدس‌زدن کدهای تخفیف: ۱۰ تلاش در ۱۰ دقیقه برای هر IP
        $ip = RateLimiter::ip();
        if (RateLimiter::tooMany('coupon', $ip, 10, 600)) {
            Middleware::tooManyRequests(RateLimiter::retryAfter('coupon', $ip, 600));
        }

        if ($code === '') {
            JsonResponse::error('کد تخفیف را وارد کنید.', 422, ['code' => 'کد تخفیف را وارد کنید.']);
        }

        $cart = new Cart();
        $result = $cart->applyCoupon($code);

        if (!$result['ok']) {
            RateLimiter::hit('coupon', $ip);
            JsonResponse::error($result['error'], 422, ['code' => $result['error']], $this->cartPayload($cart, true));
        }

        JsonResponse::success($this->cartPayload($cart, true), 'کد تخفیف اعمال شد.');
    }

    // DELETE /cart/coupon
    public function removeCoupon(Request $request, array $params): void
    {
        $cart = new Cart();
        $cart->removeCoupon();

        JsonResponse::success($this->cartPayload($cart, true), 'کد تخفیف حذف شد.');
    }

    private function respond(Cart $cart, array $result, string $successMessage, int $successStatus, bool $withHtml = false): void
    {
        $payload = $this->cartPayload($cart, $withHtml) + ['quantity' => $result['quantity'], 'max' => $result['max']];

        switch ($result['status']) {
            case Cart::OK:
                JsonResponse::success($payload, $successMessage, $successStatus);
                break;
            case Cart::NOT_FOUND:
                JsonResponse::error('این محصول دیگر وجود ندارد.', 404, [], $payload);
                break;
            case Cart::OUT_OF_STOCK:
                JsonResponse::error('این کالا در حال حاضر ناموجود است.', 409, [], $payload);
                break;
            default:
                JsonResponse::error('حداکثر تعداد مجاز این کالا ' . $result['max'] . ' عدد است.', 409, [], $payload);
        }
    }
}
