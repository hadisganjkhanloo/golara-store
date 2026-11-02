<?php

class Order extends Model
{
    protected static string $table = 'orders';

    // ثابت‌های کلاس: به‌جای اینکه تو کل پروژه رشته 'paid' رو دستی تایپ کنیم
    // (و احتمال غلط تایپی بدیم)، از این ثابت‌ها استفاده می‌کنیم: Order::STATUS_PAID
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    // سفارش‌های pending بیشتر از این مدت (دقیقه) پرداخت‌نشده بمونن، موجودی رزروشون آزاد می‌شه
    public const PENDING_TTL_MINUTES = 30;

    /**
     * سفارش رو از روی آیتم‌های سبد می‌سازه و هم‌زمان موجودی انبار رو «رزرو» (کم) می‌کنه.
     * همه‌چیز داخل یه تراکنشه: اگه موجودی یکی از کالاها کافی نباشه، هیچ‌چیز ثبت نمی‌شه (rollback).
     *
     * @return array{ok:bool, order_id:int, error:string}
     */
    public function createWithReservation(int $customerId, array $cartItems): array
    {
        $productModel = new Product();
        $total = 0;
        foreach ($cartItems as $item) {
            $total += $item['product']['price'] * $item['qty'];
        }

        mysqli_begin_transaction($this->conn);

        try {
            foreach ($cartItems as $item) {
                if (!$productModel->decrementStock((int) $item['product']['id'], (int) $item['qty'])) {
                    mysqli_rollback($this->conn);
                    return [
                        'ok' => false,
                        'order_id' => 0,
                        'error' => 'موجودی «' . $item['product']['name'] . '» برای این تعداد کافی نیست.',
                    ];
                }
            }

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

            mysqli_commit($this->conn);
        } catch (Throwable $e) {
            mysqli_rollback($this->conn);
            throw $e;
        }

        return ['ok' => true, 'order_id' => $orderId, 'error' => ''];
    }

    // سفارش‌های پرداخت‌نشده یک مشتری رو لغو می‌کنه و موجودیشون رو برمی‌گردونه (قبل از ساخت سفارش جدید)
    public function cancelPendingForCustomer(int $customerId): void
    {
        $stmt = mysqli_prepare($this->conn, "SELECT id FROM orders WHERE customer_id = ? AND status = ?");
        $status = self::STATUS_PENDING;
        mysqli_stmt_bind_param($stmt, 'is', $customerId, $status);
        mysqli_stmt_execute($stmt);

        foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $row) {
            $this->markAsFailed((int) $row['id']);
        }
    }

    // سفارش‌های رهاشده (کاربر رفته درگاه و برنگشته) رو از رزرو انبار درمیاره
    public function releaseExpired(): void
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT id FROM orders WHERE status = ? AND created_at < (NOW() - INTERVAL ? MINUTE)"
        );
        $status = self::STATUS_PENDING;
        $minutes = self::PENDING_TTL_MINUTES;
        mysqli_stmt_bind_param($stmt, 'si', $status, $minutes);
        mysqli_stmt_execute($stmt);

        foreach (mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC) as $row) {
            $this->markAsFailed((int) $row['id']);
        }
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
        $order = $this->find($orderId);
        if (!$order || $order['status'] === self::STATUS_PAID) {
            return false;
        }

        $stmt = mysqli_prepare($this->conn, "UPDATE orders SET status = ?, ref_id = ? WHERE id = ?");
        $status = self::STATUS_PAID;
        mysqli_stmt_bind_param($stmt, 'ssi', $status, $refId, $orderId);
        $ok = mysqli_stmt_execute($stmt);

        // اگه سفارش قبلاً به‌خاطر گذشتن مهلت لغو شده بود (موجودیش برگشته بود) ولی پول واقعاً پرداخت شده،
        // موجودی رو دوباره از انبار برمی‌داریم (تا جایی که هست؛ هیچ‌وقت منفی نمی‌شه)
        if ($ok && $order['status'] === self::STATUS_FAILED) {
            foreach ($this->getItems($orderId) as $item) {
                $stmt = mysqli_prepare($this->conn, "UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
                mysqli_stmt_bind_param($stmt, 'ii', $item['quantity'], $item['product_id']);
                mysqli_stmt_execute($stmt);
            }
        }

        return $ok;
    }

    /**
     * سفارش pending رو ناموفق می‌کنه و موجودی رزروشده رو به انبار برمی‌گردونه.
     * شرط «status = pending» تو خود UPDATE باعث می‌شه حتی با دو درخواست همزمان،
     * موجودی فقط یک بار برگرده.
     */
    public function markAsFailed(int $orderId): bool
    {
        mysqli_begin_transaction($this->conn);

        $stmt = mysqli_prepare($this->conn, "UPDATE orders SET status = ? WHERE id = ? AND status = ?");
        $failed = self::STATUS_FAILED;
        $pending = self::STATUS_PENDING;
        mysqli_stmt_bind_param($stmt, 'sis', $failed, $orderId, $pending);
        mysqli_stmt_execute($stmt);
        $changed = mysqli_stmt_affected_rows($stmt) === 1;

        if ($changed) {
            $productModel = new Product();
            foreach ($this->getItems($orderId) as $item) {
                $productModel->incrementStock((int) $item['product_id'], (int) $item['quantity']);
            }
        }

        mysqli_commit($this->conn);

        return $changed;
    }
}
