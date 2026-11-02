<?php

class Coupon extends Model
{
    protected static string $table = 'coupons';

    public static function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', normalize_digits(trim($code))));
    }

    public function findByCode(string $code): ?array
    {
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM coupons WHERE code = ?");
        $code = self::normalize($code);
        mysqli_stmt_bind_param($stmt, 's', $code);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    /**
     * کد تخفیف برای این مبلغ سبد قابل استفاده‌ست؟
     * @return array{ok:bool, coupon:?array, error:string}
     */
    public function validate(string $code, int $subtotal): array
    {
        $coupon = $this->findByCode($code);
        $fail = fn(string $message) => ['ok' => false, 'coupon' => null, 'error' => $message];

        if (!$coupon || !(int) $coupon['is_active']) {
            return $fail('این کد تخفیف معتبر نیست.');
        }
        if ($coupon['expires_at'] !== null && strtotime($coupon['expires_at']) < time()) {
            return $fail('مهلت استفاده از این کد تخفیف تمام شده است.');
        }
        if ($coupon['max_uses'] !== null && (int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
            return $fail('ظرفیت استفاده از این کد تخفیف تمام شده است.');
        }
        if ($subtotal < (int) $coupon['min_order']) {
            return $fail('این کد برای خرید بالای ' . format_price($coupon['min_order']) . ' قابل استفاده است.');
        }

        return ['ok' => true, 'coupon' => $coupon, 'error' => ''];
    }

    // مبلغ تخفیف (هیچ‌وقت بیشتر از جمع سبد نمی‌شه)
    public static function discountFor(array $coupon, int $subtotal): int
    {
        $discount = $coupon['type'] === 'percent'
            ? intdiv($subtotal * min(100, (int) $coupon['value']), 100)
            : (int) $coupon['value'];

        return max(0, min($discount, $subtotal));
    }

    public function incrementUsage(string $code): void
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE coupons SET used_count = used_count + 1 WHERE code = ?");
        mysqli_stmt_bind_param($stmt, 's', $code);
        mysqli_stmt_execute($stmt);
    }

    // ---------- پنل مدیریت ----------

    public function create(string $code, string $type, int $value, int $minOrder, ?int $maxUses, ?string $expiresAt): int
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "INSERT INTO coupons (code, type, value, min_order, max_uses, expires_at) VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'ssiiis', $code, $type, $value, $minOrder, $maxUses, $expiresAt);
        mysqli_stmt_execute($stmt);

        return mysqli_insert_id($this->conn);
    }

    public function setActive(int $id, bool $active): void
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE coupons SET is_active = ? WHERE id = ?");
        $flag = $active ? 1 : 0;
        mysqli_stmt_bind_param($stmt, 'ii', $flag, $id);
        mysqli_stmt_execute($stmt);
    }
}
