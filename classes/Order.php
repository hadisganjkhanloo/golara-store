<?php

class Order extends Model
{
    protected static string $table = 'orders';

    // ثابت‌های کلاس: به‌جای اینکه تو کل پروژه رشته 'paid' رو دستی تایپ کنیم
    // (و احتمال غلط تایپی بدیم)، از این ثابت‌ها استفاده می‌کنیم: Order::STATUS_PAID
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    // وضعیت ارسال (بعد از پرداخت موفق، مدیر عوضش می‌کنه)
    public const FULFILLMENT_NEW = 'new';
    public const FULFILLMENT_PROCESSING = 'processing';
    public const FULFILLMENT_SHIPPED = 'shipped';
    public const FULFILLMENT_DELIVERED = 'delivered';
    public const FULFILLMENT_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'در انتظار پرداخت',
        self::STATUS_PAID => 'پرداخت‌شده',
        self::STATUS_FAILED => 'ناموفق',
    ];

    public const FULFILLMENT_LABELS = [
        self::FULFILLMENT_NEW => 'ثبت‌شده',
        self::FULFILLMENT_PROCESSING => 'در حال آماده‌سازی',
        self::FULFILLMENT_SHIPPED => 'ارسال‌شده',
        self::FULFILLMENT_DELIVERED => 'تحویل‌شده',
        self::FULFILLMENT_CANCELLED => 'لغوشده',
    ];

    // سفارش‌های pending بیشتر از این مدت (دقیقه) پرداخت‌نشده بمونن، موجودی رزروشون آزاد می‌شه
    public const PENDING_TTL_MINUTES = 30;

    /**
     * سفارش رو از روی آیتم‌های سبد می‌سازه و هم‌زمان موجودی انبار رو «رزرو» (کم) می‌کنه.
     * همه‌چیز داخل یه تراکنشه: اگه موجودی یکی از کالاها کافی نباشه، هیچ‌چیز ثبت نمی‌شه (rollback).
     *
     * @param array $shipping مشخصات تحویل: name, phone, city, postal_code, address
     * @param array $pricing  خروجی Pricing::summarize (جمع کالاها، تخفیف، ارسال، قابل پرداخت، کد تخفیف)
     * @return array{ok:bool, order_id:int, error:string}
     */
    public function createWithReservation(int $customerId, array $cartItems, array $shipping, array $pricing): array
    {
        $productModel = new Product();

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
                "INSERT INTO orders
                    (customer_id, total_amount, subtotal, shipping_cost, discount_amount, coupon_code,
                     shipping_name, shipping_phone, shipping_city, shipping_postal_code, shipping_address, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $status = self::STATUS_PENDING;
            mysqli_stmt_bind_param(
                $stmt,
                'iiiiisssssss',
                $customerId,
                $pricing['total'],
                $pricing['subtotal'],
                $pricing['shipping'],
                $pricing['discount'],
                $pricing['coupon'],
                $shipping['name'],
                $shipping['phone'],
                $shipping['city'],
                $shipping['postal_code'],
                $shipping['address'],
                $status
            );
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

        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }

    public function getByCustomer(int $customerId): array
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT * FROM orders WHERE customer_id = ? ORDER BY created_at DESC"
        );
        mysqli_stmt_bind_param($stmt, 'i', $customerId);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    }

    // سفارش، فقط اگه مال همین مشتری باشه (مشتری نباید سفارش بقیه رو با عوض‌کردن id تو آدرس ببینه)
    public function findForCustomer(int $orderId, int $customerId): ?array
    {
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM orders WHERE id = ? AND customer_id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $orderId, $customerId);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    // آخرین مشخصات تحویلِ مشتری؛ برای پرکردن خودکار فرم تسویه حساب
    public function lastShippingFor(int $customerId): array
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT shipping_name AS name, shipping_phone AS phone, shipping_city AS city,
                    shipping_postal_code AS postal_code, shipping_address AS address
             FROM orders WHERE customer_id = ? AND shipping_address IS NOT NULL ORDER BY id DESC LIMIT 1"
        );
        mysqli_stmt_bind_param($stmt, 'i', $customerId);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
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

        // کد تخفیف فقط با پرداخت موفق «مصرف» می‌شه
        if ($ok && !empty($order['coupon_code'])) {
            (new Coupon())->incrementUsage($order['coupon_code']);
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

    // ---------- پنل مدیریت ----------

    /**
     * لیست سفارش‌ها برای مدیر، با نام مشتری، فیلتر و صفحه‌بندی.
     * فیلترها: status, fulfillment, q (شماره سفارش یا نام/ایمیل مشتری)
     */
    public function paginateForAdmin(array $filters, int $page, int $perPage): array
    {
        $where = [];
        $types = '';
        $params = [];

        if (isset(self::STATUS_LABELS[$filters['status'] ?? ''])) {
            $where[] = 'o.status = ?';
            $types .= 's';
            $params[] = $filters['status'];
        }
        if (isset(self::FULFILLMENT_LABELS[$filters['fulfillment'] ?? ''])) {
            $where[] = 'o.fulfillment = ?';
            $types .= 's';
            $params[] = $filters['fulfillment'];
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(o.id = ? OR c.name LIKE ? OR c.email LIKE ? OR o.shipping_name LIKE ?)';
            $types .= 'isss';
            array_push($params, (int) normalize_digits($q), $like, $like, $like);
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $from = "FROM orders o JOIN customers c ON c.id = o.customer_id $whereSql";

        $stmt = mysqli_prepare($this->conn, "SELECT COUNT(*) AS c $from");
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $total = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];

        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT o.*, c.name AS customer_name, c.email AS customer_email $from ORDER BY o.id DESC LIMIT ? OFFSET ?"
        );
        mysqli_stmt_bind_param($stmt, $types . 'ii', ...array_merge($params, [$perPage, $offset]));
        mysqli_stmt_execute($stmt);

        return [
            'items' => mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    public function findWithCustomer(int $orderId): ?array
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT o.*, c.name AS customer_name, c.email AS customer_email
             FROM orders o JOIN customers c ON c.id = o.customer_id WHERE o.id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $orderId);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    // وضعیت ارسال و کد رهگیری؛ فقط برای سفارش پرداخت‌شده معنی داره
    public function updateFulfillment(int $orderId, string $fulfillment, ?string $trackingCode): bool
    {
        if (!isset(self::FULFILLMENT_LABELS[$fulfillment])) {
            return false;
        }

        $trackingCode = $trackingCode !== null ? preg_replace('/[^A-Za-z0-9\-_]/', '', $trackingCode) : null;
        $trackingCode = ($trackingCode === '' || $trackingCode === null) ? null : substr($trackingCode, 0, 60);

        $order = $this->find($orderId);
        if (!$order || $order['status'] !== self::STATUS_PAID) {
            return false;
        }

        $stmt = mysqli_prepare($this->conn, "UPDATE orders SET fulfillment = ?, tracking_code = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $fulfillment, $trackingCode, $orderId);
        mysqli_stmt_execute($stmt);

        return true;
    }

    // آمار کلی برای بالای صفحه سفارش‌های مدیر
    public function stats(): array
    {
        $result = mysqli_query($this->conn, "
            SELECT
                SUM(status = 'paid') AS paid_orders,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount END), 0) AS revenue,
                SUM(status = 'paid' AND fulfillment IN ('new', 'processing')) AS to_ship,
                SUM(status = 'pending') AS pending_orders
            FROM orders
        ");
        $row = mysqli_fetch_assoc($result);

        return array_map('intval', array_map(fn($v) => $v ?? 0, $row));
    }
}
