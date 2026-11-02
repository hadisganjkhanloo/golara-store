<?php

namespace Api;

use Cart;
use JsonResponse;
use Request;
use Wishlist;

/**
 * GET /api/v1/state — وضعیت لحظه‌ای سبد و علاقه‌مندی‌ها.
 * هر تب که فعال می‌شه (یا تب دیگه‌ای چیزی رو عوض می‌کنه) از اینجا شمارنده‌ها رو تازه می‌کنه.
 */
class StateController extends BaseController
{
    public function show(Request $request, array $params): void
    {
        $cart = $this->cartPayload(new Cart());
        $wishlist = $this->wishlistPayload(new Wishlist());

        JsonResponse::success([
            'logged_in' => !empty($_SESSION['customer_id']),
            'cart' => ['count' => $cart['count'], 'total' => $cart['total']],
            'wishlist' => $wishlist,
        ]);
    }
}
