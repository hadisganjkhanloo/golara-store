<?php

namespace Api;

use Cart;
use Product;
use Wishlist;

/**
 * پایه‌ی همه کنترلرهای API: شکل‌دهی یکدست داده‌ها که هر کنترلر خودش دوباره ننویسه.
 */
abstract class BaseController
{
    // نمایش عمومی یک محصول در API (فیلدهای داخلی دیتابیس مستقیم بیرون نمی‌رن)
    protected function productPayload(array $p): array
    {
        $stock = (int) ($p['stock'] ?? 0);

        return [
            'id' => (int) $p['id'],
            'name' => $p['name'],
            'description' => $p['description'],
            'price' => (int) $p['price'],
            'stock' => $stock,
            'in_stock' => $stock > 0,
            'category_id' => $p['category_id'] !== null ? (int) $p['category_id'] : null,
            'category_name' => $p['category_name'] ?? null,
            'image_url' => $p['image'] ? absolute_url('uploads/products/' . $p['image']) : null,
            'url' => absolute_url('product.php?id=' . $p['id']),
            'created_at' => $p['created_at'] ?? null,
        ];
    }

    // خلاصه سبد خرید؛ با $withHtml جدول آماده‌ی صفحه سبد هم داخلش میاد
    protected function cartPayload(Cart $cart, bool $withHtml = false): array
    {
        $items = $cart->getItemsWithProducts();
        $total = 0;
        foreach ($items as $item) {
            $total += $item['line_total'];
        }

        $payload = [
            'count' => $cart->count(),
            'total' => $total,
            'items' => array_map(fn($i) => [
                'product_id' => (int) $i['product']['id'],
                'name' => $i['product']['name'],
                'price' => (int) $i['product']['price'],
                'quantity' => $i['qty'],
                'line_total' => $i['line_total'],
                'stock' => $i['stock'],
                'enough' => $i['enough'],
            ], $items),
        ];

        if ($withHtml) {
            ob_start();
            render_cart_table($items, $total);
            $payload['html'] = ob_get_clean();
        }

        return $payload;
    }

    protected function wishlistPayload(Wishlist $wishlist, bool $withHtml = false): array
    {
        $ids = $wishlist->ids();

        $payload = ['count' => count($ids), 'ids' => $ids];

        if ($withHtml) {
            ob_start();
            render_wishlist_grid($wishlist->products());
            $payload['html'] = ob_get_clean();
        }

        return $payload;
    }

    // عدد صحیح نامنفی از ورودی (رشته‌ی عددی یا عدد)؛ null یعنی معتبر نیست
    protected function intOrNull($value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }
        if (is_string($value) && preg_match('/^[0-9]+$/', $value)) {
            return (int) $value;
        }

        return null;
    }
}
