<?php

/**
 * علاقه‌مندی‌ها؛ مثل Cart دو محل ذخیره‌سازی داره:
 * مهمان → سشن، کاربر واردشده → جدول wishlist_items (با ورود، لیست مهمان ادغام می‌شه).
 *
 * به‌جای toggle (اگه بود حذف، نبود اضافه) دو عمل مشخص داریم: add و remove.
 * toggle وقتی چند تب باز باشه خطرناکه: تب قدیمی فکر می‌کنه قلب خاموشه و با کلیک،
 * به‌جای اضافه کردن، چیزی که تب دیگه اضافه کرده رو حذف می‌کنه. add/remove ایدمپوتنت هستن.
 */
class Wishlist
{
    // نتیجه ids() رو تو طول یه درخواست نگه می‌داریم تا برای هر کارت محصول یه کوئری جدا نزنیم
    private static array $cache = [];

    private mysqli $conn;
    private ?int $customerId;

    public function __construct(?int $customerId = null)
    {
        $this->conn = Database::getConnection();
        $this->customerId = $customerId ?? (!empty($_SESSION['customer_id']) ? (int) $_SESSION['customer_id'] : null);

        if (!isset($_SESSION['wishlist'])) {
            $_SESSION['wishlist'] = [];
        }
    }

    public function has(int $productId): bool
    {
        return in_array($productId, $this->ids(), true);
    }

    // true = به لیست اضافه شد (یا از قبل بود)، false = همچین محصولی وجود نداره
    public function add(int $productId): bool
    {
        if ((new Product())->find($productId) === null) {
            return false;
        }

        if ($this->customerId === null) {
            if (!in_array($productId, $_SESSION['wishlist'], true)) {
                $_SESSION['wishlist'][] = $productId;
            }
        } else {
            $stmt = mysqli_prepare($this->conn, "INSERT IGNORE INTO wishlist_items (customer_id, product_id) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ii', $this->customerId, $productId);
            mysqli_stmt_execute($stmt);
        }

        $this->forget();
        return true;
    }

    public function remove(int $productId): void
    {
        if ($this->customerId === null) {
            $_SESSION['wishlist'] = array_values(array_diff($_SESSION['wishlist'], [$productId]));
        } else {
            $stmt = mysqli_prepare($this->conn, "DELETE FROM wishlist_items WHERE customer_id = ? AND product_id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $this->customerId, $productId);
            mysqli_stmt_execute($stmt);
        }

        $this->forget();
    }

    public function count(): int
    {
        return count($this->ids());
    }

    // شناسه محصولاتِ موجود تو لیست، به ترتیب اضافه شدن
    public function ids(): array
    {
        $key = $this->customerId === null ? 'guest' : 'c' . $this->customerId;

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        if ($this->customerId === null) {
            $ids = array_map('intval', $_SESSION['wishlist']);
            if (!empty($ids)) {
                // محصولی که از دیتابیس حذف شده نباید تو شمارنده بیاد
                $existing = array_map('intval', array_column((new Product())->getByIds($ids), 'id'));
                $ids = array_values(array_filter($ids, fn($id) => in_array($id, $existing, true)));
                $_SESSION['wishlist'] = $ids;
            }
        } else {
            // محصولِ حذف‌شده (deleted_at) تو لیست و شمارنده نمیاد
            $stmt = mysqli_prepare(
                $this->conn,
                "SELECT w.product_id FROM wishlist_items w
                 JOIN products p ON p.id = w.product_id AND p.deleted_at IS NULL
                 WHERE w.customer_id = ? ORDER BY w.id"
            );
            mysqli_stmt_bind_param($stmt, 'i', $this->customerId);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            $ids = [];
            while ($row = mysqli_fetch_assoc($result)) {
                $ids[] = (int) $row['product_id'];
            }
        }

        return self::$cache[$key] = $ids;
    }

    // برای سازگاری با کدهای قبلی
    public function getIds(): array
    {
        return $this->ids();
    }

    // خود محصولات لیست، به ترتیب اضافه شدن (برای صفحه علاقه‌مندی‌ها)
    public function products(): array
    {
        $byId = [];
        foreach ((new Product())->getByIds($this->ids()) as $product) {
            $byId[(int) $product['id']] = $product;
        }

        $ordered = [];
        foreach ($this->ids() as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    // بعد از ورود، لیست مهمان تو حساب کاربر ریخته می‌شه
    public static function mergeGuestList(int $customerId): void
    {
        $guest = array_map('intval', $_SESSION['wishlist'] ?? []);
        $_SESSION['wishlist'] = [];

        $wishlist = new self($customerId);
        foreach ($guest as $productId) {
            $wishlist->add($productId);
        }

        self::$cache = [];
    }

    private function forget(): void
    {
        self::$cache = [];
    }
}
