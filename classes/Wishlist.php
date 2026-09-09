<?php

/**
 * دقیقاً همون الگوی Cart: یه کلاس معمولی که فقط یه آرایه تو سشن رو مدیریت می‌کنه.
 */
class Wishlist
{
    public function __construct()
    {
        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
    }

    public function has(int $productId): bool
    {
        return in_array($productId, $_SESSION['wishlist']);
    }

    // اگه بود حذف می‌کنه، نبود اضافه می‌کنه؛ وضعیت جدید رو برمی‌گردونه
    public function toggle(int $productId): bool
    {
        if ($this->has($productId)) {
            $this->remove($productId);
            return false;
        }

        $_SESSION['wishlist'][] = $productId;
        return true;
    }

    public function remove(int $productId): void
    {
        $_SESSION['wishlist'] = array_values(array_diff($_SESSION['wishlist'], [$productId]));
    }

    public function count(): int
    {
        return count($_SESSION['wishlist']);
    }

    public function getIds(): array
    {
        return $_SESSION['wishlist'];
    }
}
