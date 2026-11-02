<?php

/**
 * سبد خرید با دو محل ذخیره‌سازی:
 *  - مهمان (وارد نشده): آرایه داخل سشن
 *  - کاربر واردشده: جدول cart_items تو دیتابیس، پس با خروج/ورود دوباره، یا از یه دستگاه دیگه هم سر جاشه
 *
 * موقع ورود، سبد مهمان با سبد ذخیره‌شده تو حساب ادغام می‌شه (mergeGuestCart).
 * موجودی انبار هم همیشه چک می‌شه: نمی‌شه بیشتر از موجودی (و بیشتر از MAX_QTY_PER_ITEM) تو سبد گذاشت.
 */
class Cart
{
    public const OK = 'ok';
    public const NOT_FOUND = 'not_found';
    public const OUT_OF_STOCK = 'out_of_stock';
    public const LIMIT_REACHED = 'limit_reached';

    private mysqli $conn;
    private ?int $customerId;

    public function __construct(?int $customerId = null)
    {
        $this->conn = Database::getConnection();
        $this->customerId = $customerId ?? (!empty($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null);

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    // ---------- افزودن / تغییر / حذف ----------

    /**
     * @return array{status:string, quantity:int, max:int}
     *   quantity = تعداد این محصول تو سبد بعد از عملیات، max = بیشترین تعداد مجاز (موجودی یا سقف هر کالا)
     */
    public function add(int $productId, int $qty = 1): array
    {
        $product = (new Product())->find($productId);
        $current = $this->stored()[$productId] ?? 0;

        if (!$product) {
            return ['status' => self::NOT_FOUND, 'quantity' => 0, 'max' => 0];
        }

        $stock = (int) $product['stock'];

        if ($stock <= 0) {
            return ['status' => self::OUT_OF_STOCK, 'quantity' => $current, 'max' => 0];
        }

        $allowed = min($stock, MAX_QTY_PER_ITEM);
        $wanted = $current + max(1, $qty);

        if ($wanted > $allowed) {
            return ['status' => self::LIMIT_REACHED, 'quantity' => $current, 'max' => $allowed];
        }

        $this->store($productId, $wanted);

        return ['status' => self::OK, 'quantity' => $wanted, 'max' => $allowed];
    }

    // تعداد دقیق رو مشخص می‌کنه (برای دکمه‌های + و − تو صفحه سبد)؛ صفر یعنی حذف
    public function setQuantity(int $productId, int $qty): array
    {
        if ($qty <= 0) {
            $this->remove($productId);
            return ['status' => self::OK, 'quantity' => 0, 'max' => 0];
        }

        $product = (new Product())->find($productId);

        if (!$product) {
            $this->remove($productId);
            return ['status' => self::NOT_FOUND, 'quantity' => 0, 'max' => 0];
        }

        $stock = (int) $product['stock'];
        $current = $this->stored()[$productId] ?? 0;

        if ($stock <= 0) {
            return ['status' => self::OUT_OF_STOCK, 'quantity' => $current, 'max' => 0];
        }

        $allowed = min($stock, MAX_QTY_PER_ITEM);

        if ($qty > $allowed) {
            return ['status' => self::LIMIT_REACHED, 'quantity' => $current, 'max' => $allowed];
        }

        $this->store($productId, $qty);

        return ['status' => self::OK, 'quantity' => $qty, 'max' => $allowed];
    }

    public function remove(int $productId): void
    {
        $this->store($productId, 0);
    }

    public function clear(): void
    {
        $_SESSION['cart'] = [];
        unset($_SESSION['coupon_code']);

        if ($this->customerId !== null) {
            $stmt = mysqli_prepare($this->conn, "DELETE FROM cart_items WHERE customer_id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $this->customerId);
            mysqli_stmt_execute($stmt);
        }
    }

    // ---------- خواندن ----------

    // تعداد کل کالاهای داخل سبد (جمع تعداد همه ردیف‌ها)؛ عددی که کنار آیکون سبد نشون داده می‌شه
    public function count(): int
    {
        if ($this->customerId !== null) {
            // محصولِ حذف‌شده (deleted_at) تو شمارنده نمیاد
            $stmt = mysqli_prepare(
                $this->conn,
                "SELECT COALESCE(SUM(ci.quantity), 0) AS c
                 FROM cart_items ci JOIN products p ON p.id = ci.product_id AND p.deleted_at IS NULL
                 WHERE ci.customer_id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'i', $this->customerId);
            mysqli_stmt_execute($stmt);
            return (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
        }

        return array_sum($this->validGuestItems());
    }

    public function isEmpty(): bool
    {
        return $this->count() === 0;
    }

    public function getRawItems(): array
    {
        return $this->stored();
    }

    // محصولات داخل سبد همراه با تعداد، جمع هر ردیف و وضعیت موجودی
    public function getItemsWithProducts(): array
    {
        $quantities = $this->stored();

        if (empty($quantities)) {
            return [];
        }

        $products = [];
        foreach ((new Product())->getByIds(array_keys($quantities)) as $product) {
            $products[(int) $product['id']] = $product;
        }

        $items = [];
        foreach ($quantities as $productId => $qty) {
            if (!isset($products[$productId])) {
                continue;
            }

            $product = $products[$productId];
            $stock = (int) $product['stock'];

            $items[] = [
                'product' => $product,
                'qty' => (int) $qty,
                'line_total' => (int) $product['price'] * (int) $qty,
                'stock' => $stock,
                'in_stock' => $stock > 0,
                // false = موجودی از تعداد داخل سبد کمتر شده (مثلاً یکی دیگه زودتر خریده)
                'enough' => $stock >= $qty,
            ];
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

    // ---------- کد تخفیف ----------

    /**
     * کد تخفیف ذخیره‌شده تو سشن رو با جمع فعلی سبد دوباره اعتبارسنجی می‌کنه.
     * @return array{coupon:?array, error:string}
     */
    public function coupon(int $subtotal): array
    {
        $code = $_SESSION['coupon_code'] ?? '';

        if ($code === '' || $subtotal <= 0) {
            return ['coupon' => null, 'error' => ''];
        }

        $check = (new Coupon())->validate($code, $subtotal);

        if (!$check['ok']) {
            // کد دیگه معتبر نیست (مثلاً با حذف یه کالا از حداقل خرید پایین‌تر رفتیم)
            unset($_SESSION['coupon_code']);
            return ['coupon' => null, 'error' => $check['error']];
        }

        return ['coupon' => $check['coupon'], 'error' => ''];
    }

    /** @return array{ok:bool, error:string} */
    public function applyCoupon(string $code): array
    {
        $check = (new Coupon())->validate($code, $this->getTotal());

        if ($check['ok']) {
            $_SESSION['coupon_code'] = $check['coupon']['code'];
        }

        return ['ok' => $check['ok'], 'error' => $check['error']];
    }

    public function removeCoupon(): void
    {
        unset($_SESSION['coupon_code']);
    }

    // مبلغ‌ها (جمع کالاها، تخفیف، ارسال، قابل پرداخت) + خطای کد تخفیف اگه کد دیگه معتبر نبود
    public function summary(?array $items = null): array
    {
        $items ??= $this->getItemsWithProducts();
        $subtotal = array_sum(array_column($items, 'line_total'));

        $couponInfo = $this->coupon($subtotal);

        return Pricing::summarize($subtotal, $couponInfo['coupon']) + ['coupon_error' => $couponInfo['error']];
    }

    /**
     * تعداد داخل سبد رو با موجودی فعلی هماهنگ می‌کنه (بالای موجودی → کم می‌شه، ناموجود → حذف)
     * و لیست تغییرها رو برمی‌گردونه تا به کاربر نشون بدیم.
     */
    public function syncWithStock(): array
    {
        $changes = [];

        foreach ($this->getItemsWithProducts() as $item) {
            $id = (int) $item['product']['id'];

            if (!$item['in_stock']) {
                $this->remove($id);
                $changes[] = ['product' => $item['product']['name'], 'from' => $item['qty'], 'to' => 0];
            } elseif (!$item['enough']) {
                $this->store($id, $item['stock']);
                $changes[] = ['product' => $item['product']['name'], 'from' => $item['qty'], 'to' => $item['stock']];
            }
        }

        return $changes;
    }

    // ---------- ادغام سبد مهمان بعد از ورود ----------

    public static function mergeGuestCart(int $customerId): void
    {
        $guest = $_SESSION['cart'] ?? [];
        $_SESSION['cart'] = [];

        if (empty($guest)) {
            return;
        }

        $cart = new self($customerId);
        $product = new Product();

        foreach ($guest as $productId => $qty) {
            $row = $product->find((int) $productId);
            if (!$row || (int) $row['stock'] <= 0) {
                continue;
            }

            $merged = ($cart->stored()[(int) $productId] ?? 0) + (int) $qty;
            // از موجودی انبار و سقف هر کالا بیشتر نمی‌ذاریم
            $cart->store((int) $productId, min($merged, (int) $row['stock'], MAX_QTY_PER_ITEM));
        }
    }

    // ---------- ذخیره‌سازی داخلی ----------

    // [شناسه محصول => تعداد]
    private function stored(): array
    {
        if ($this->customerId === null) {
            return $this->validGuestItems();
        }

        $stmt = mysqli_prepare($this->conn, "SELECT product_id, quantity FROM cart_items WHERE customer_id = ? ORDER BY id");
        mysqli_stmt_bind_param($stmt, 'i', $this->customerId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $items = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $items[(int) $row['product_id']] = (int) $row['quantity'];
        }

        return $items;
    }

    // محصولی که بعد از اضافه شدن به سبد مهمان حذف شده، همین‌جا از سشن هم پاک می‌شه
    private function validGuestItems(): array
    {
        $items = $_SESSION['cart'];

        if (empty($items)) {
            return [];
        }

        $existing = array_column((new Product())->getByIds(array_keys($items)), 'id');
        $existing = array_map('intval', $existing);

        foreach (array_keys($items) as $productId) {
            if (!in_array((int) $productId, $existing, true)) {
                unset($items[$productId]);
            }
        }

        $_SESSION['cart'] = $items;

        return $items;
    }

    private function store(int $productId, int $qty): void
    {
        if ($this->customerId === null) {
            if ($qty <= 0) {
                unset($_SESSION['cart'][$productId]);
            } else {
                $_SESSION['cart'][$productId] = $qty;
            }
            return;
        }

        if ($qty <= 0) {
            $stmt = mysqli_prepare($this->conn, "DELETE FROM cart_items WHERE customer_id = ? AND product_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $this->customerId, $productId);
        } else {
            // UNIQUE روی (کاربر، محصول) هست؛ اگه ردیف بود فقط تعدادش عوض می‌شه
            $stmt = mysqli_prepare(
                $this->conn,
                "INSERT INTO cart_items (customer_id, product_id, quantity) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)"
            );
            mysqli_stmt_bind_param($stmt, 'iii', $this->customerId, $productId, $qty);
        }

        mysqli_stmt_execute($stmt);
    }
}
