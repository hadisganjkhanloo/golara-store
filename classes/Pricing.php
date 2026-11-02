<?php

/**
 * محاسبه مبلغ نهایی: جمع کالاها − تخفیف + هزینه ارسال.
 * هم سبد خرید، هم صفحه تسویه حساب، هم ساخت سفارش از همین یه تابع استفاده می‌کنن،
 * پس عددی که مشتری می‌بینه همیشه با عددی که پرداخت می‌شه یکیه.
 */
class Pricing
{
    public static function summarize(int $subtotal, ?array $coupon): array
    {
        $discount = $coupon ? Coupon::discountFor($coupon, $subtotal) : 0;
        $afterDiscount = max(0, $subtotal - $discount);
        $shipping = shipping_cost_for($afterDiscount);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'coupon' => $coupon['code'] ?? null,
            'shipping' => $shipping,
            'total' => $afterDiscount + $shipping,
            // چقدر دیگه تا ارسال رایگان (فقط وقتی هنوز هزینه ارسال می‌گیریم)
            'free_shipping_remaining' => $shipping > 0 ? max(0, FREE_SHIPPING_THRESHOLD - $afterDiscount) : 0,
        ];
    }
}
