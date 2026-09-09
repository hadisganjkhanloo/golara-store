<?php

/**
 * برخلاف Product یا Customer، این کلاس اصلاً از Model ارث‌بری نمی‌کنه
 * چون سبد خرید یه جدول تو دیتابیس نیست، فقط یه آرایه تو سشنه.
 * یعنی: هر کلاسی لازم نیست حتماً به یه جدول وصل باشه.
 */
class Cart
{
    private mysqli $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    public function add(int $productId, int $qty = 1): void
    {
        if (isset($_SESSION['cart'][$productId])) {
            $_SESSION['cart'][$productId] += $qty;
        } else {
            $_SESSION['cart'][$productId] = $qty;
        }
    }

    public function remove(int $productId): void
    {
        unset($_SESSION['cart'][$productId]);
    }

    public function clear(): void
    {
        $_SESSION['cart'] = [];
    }

    public function count(): int
    {
        return count($_SESSION['cart']);
    }

    public function isEmpty(): bool
    {
        return empty($_SESSION['cart']);
    }

    public function getRawItems(): array
    {
        return $_SESSION['cart'];
    }

    // لیست محصولات داخل سبد رو همراه با تعداد و جمع هر ردیف برمی‌گردونه
    public function getItemsWithProducts(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        $ids = array_keys($_SESSION['cart']);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $stmt = mysqli_prepare($this->conn, "SELECT * FROM products WHERE id IN ($placeholders)");
        mysqli_stmt_bind_param($stmt, $types, ...$ids);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $items = [];
        while ($product = mysqli_fetch_assoc($result)) {
            $qty = $_SESSION['cart'][$product['id']];
            $lineTotal = $product['price'] * $qty;
            $items[] = ['product' => $product, 'qty' => $qty, 'line_total' => $lineTotal];
        }

        return $items;
    }

    public function getTotal(): int
    {
        $total = 0;
        foreach ($this->getItemsWithProducts() as $item) {
            $total += $item['line_total'];
        }
        return $total;
    }
}
