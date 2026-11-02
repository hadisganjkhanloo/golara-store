<?php

namespace Api;

use JsonResponse;
use Request;
use Wishlist;

/**
 * علاقه‌مندی‌ها (کاربر واردشده: دیتابیس، مهمان: سشن)
 *   GET    /api/v1/wishlist           لیست (همراه با html کارت‌ها)
 *   PUT    /api/v1/wishlist/{id}      اضافه کردن  (ایدمپوتنت: چندبار زدن اثر اضافه نداره)
 *   DELETE /api/v1/wishlist/{id}      حذف کردن    (ایدمپوتنت)
 */
class WishlistController extends BaseController
{
    public function index(Request $request, array $params): void
    {
        JsonResponse::success($this->wishlistPayload(new Wishlist(), true));
    }

    public function add(Request $request, array $params): void
    {
        $wishlist = new Wishlist();

        if (!$wishlist->add((int) $params['id'])) {
            JsonResponse::error('این محصول دیگر وجود ندارد.', 404);
        }

        JsonResponse::success(['active' => true] + $this->wishlistPayload($wishlist), 'به علاقه‌مندی‌ها اضافه شد.');
    }

    public function remove(Request $request, array $params): void
    {
        $wishlist = new Wishlist();
        $wishlist->remove((int) $params['id']);

        JsonResponse::success(['active' => false] + $this->wishlistPayload($wishlist), 'از علاقه‌مندی‌ها حذف شد.');
    }
}
