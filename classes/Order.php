<?php

class Order extends Model
{
    protected static string $table = 'orders';

    // ثابت‌های کلاس: به‌جای اینکه تو کل پروژه رشته 'paid' رو دستی تایپ کنیم
    // (و احتمال غلط تایپی بدیم)، از این ثابت‌ها استفاده می‌کنیم: Order::STATUS_PAID
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    // سفارش رو از روی آیتم‌های سبد خرید می‌سازه و ردیف‌های order_items رو هم ثبت می‌کنه
    public function createFromCartItems(int $customerId, array $cartItems, int $total): int
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "INSERT INTO orders (customer_id, total_amount, status) VALUES (?, ?, ?)"
        );
        $status = self::STATUS_PENDING;
        mysqli_stmt_bind_param($stmt, 'iis', $customerId, $total, $status);
        mysqli_stmt_execute($stmt);
        $orderId = mysqli_insert_id($this->conn);

        foreach ($cartItems as $item) {
            $stmt = mysqli_prepare(
                $this->conn,
                "INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'iisii',
                $orderId,
                $item['product']['id'],
                $item['product']['name'],
                $item['product']['price'],
                $item['qty']
            );
            mysqli_stmt_execute($stmt);
        }

        return $orderId;
    }

    public function getItems(int $orderId): array
    {
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM order_items WHERE order_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $orderId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $items[] = $row;
        }

        return $items;
    }

    public function getByCustomer(int $customerId): array
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC"
        );
        mysqli_stmt_bind_param($stmt, 'i', $customerId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $orders = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $orders[] = $row;
        }

        return $orders;
    }

    /**
     * بهینه‌سازی N+1 (فصل ۲۲): به‌جای اینکه برای هر سفارش جدا یه کوئری آیتم‌ها بزنیم
     * (که می‌شه ۱ کوئری سفارش + N کوئری آیتم)، همه آیتم‌های همه سفارش‌ها رو با یه IN(...)
     * تو یه کوئری می‌گیریم و بر اساس order_id گروه‌بندی می‌کنیم.
     */
    public function getItemsForOrders(array $orderIds): array
    {
        if (empty($orderIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $types = str_repeat('i', count($orderIds));

        $stmt = mysqli_prepare($this->conn, "SELECT * FROM order_items WHERE order_id IN ($placeholders)");
        mysqli_stmt_bind_param($stmt, $types, ...$orderIds);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $itemsByOrder = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $itemsByOrder[$row['order_id']][] = $row;
        }

        return $itemsByOrder;
    }

    public function countPaidByCustomer(int $customerId): int
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT COUNT(*) AS c FROM orders WHERE customer_id = ? AND status = ?"
        );
        $status = self::STATUS_PAID;
        mysqli_stmt_bind_param($stmt, 'is', $customerId, $status);
        mysqli_stmt_execute($stmt);
        return (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
    }

    public function setAuthority(int $orderId, string $authority): bool
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE orders SET authority = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $authority, $orderId);
        return mysqli_stmt_execute($stmt);
    }

    public function markAsPaid(int $orderId, ?string $refId): bool
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE orders SET status = ?, ref_id = ? WHERE id = ?");
        $status = self::STATUS_PAID;
        mysqli_stmt_bind_param($stmt, 'ssi', $status, $refId, $orderId);
        return mysqli_stmt_execute($stmt);
    }

    public function markAsFailed(int $orderId): bool
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE orders SET status = ? WHERE id = ?");
        $status = self::STATUS_FAILED;
        mysqli_stmt_bind_param($stmt, 'si', $status, $orderId);
        return mysqli_stmt_execute($stmt);
    }
}
