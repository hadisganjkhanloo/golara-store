<?php

class Product extends Model
{
    protected static string $table = 'products';

    // موجودی کمتر یا مساوی این عدد = «کم‌موجود» (برای نمایش هشدار تو سایت و پنل مدیریت)
    public const LOW_STOCK_THRESHOLD = 5;

    /**
     * محصول رو پیدا می‌کنه. محصول «حذف‌شده» (deleted_at پر) پیدا نمی‌شه، مگه اینکه $includeDeleted بدیم
     * (پنل مدیریت و صفحه سفارش‌ها برای دیدن محصولاتِ حذف‌شده‌ی داخل سفارش‌های قدیمی).
     */
    public function find(int $id, bool $includeDeleted = false): ?array
    {
        $sql = "SELECT * FROM products WHERE id = ?" . ($includeDeleted ? '' : ' AND deleted_at IS NULL');
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    public function getByCategory(int $categoryId, ?int $limit = null): array
    {
        return $this->paginate(['category_id' => $categoryId], 1, $limit ?? 100)['items'];
    }

    public function getByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = mysqli_prepare(
            $this->conn,
            $this->baseSelect(false) . " WHERE p.deleted_at IS NULL AND p.id IN ($placeholders)"
        );
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($ids)), ...$ids);
        mysqli_stmt_execute($stmt);

        return $this->fetchAll($stmt);
    }

    /**
     * لیست محصولات با فیلتر، مرتب‌سازی و صفحه‌بندی (LIMIT/OFFSET).
     * هم صفحه فروشگاه، هم جستجو، هم پنل مدیریت، هم API از همین یه متد استفاده می‌کنن.
     *
     * فیلترها: q, category_id, in_stock, stock_state (in|low|out), min_price, max_price, sort,
     *          deleted ('' = فقط فعال‌ها، 'only' = فقط حذف‌شده‌ها، 'all' = همه؛ برای پنل مدیریت)
     * sort: newest (پیش‌فرض) | oldest | price_asc | price_desc | name | stock_asc | stock_desc | bestseller
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $where = [];
        $types = '';
        $params = [];

        switch ($filters['deleted'] ?? '') {
            case 'only':
                $where[] = 'p.deleted_at IS NOT NULL';
                break;
            case 'all':
                break;
            default:
                $where[] = 'p.deleted_at IS NULL';
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = 'p.name LIKE ?';
            $types .= 's';
            $params[] = '%' . addcslashes($q, '%_\\') . '%';
        }

        $categoryId = (int) ($filters['category_id'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'p.category_id = ?';
            $types .= 'i';
            $params[] = $categoryId;
        }

        if (!empty($filters['in_stock'])) {
            $where[] = 'p.stock > 0';
        }

        switch ($filters['stock_state'] ?? '') {
            case 'in':
                $where[] = 'p.stock > ' . self::LOW_STOCK_THRESHOLD;
                break;
            case 'low':
                $where[] = 'p.stock > 0 AND p.stock <= ' . self::LOW_STOCK_THRESHOLD;
                break;
            case 'out':
                $where[] = 'p.stock = 0';
                break;
        }

        if (isset($filters['min_price']) && $filters['min_price'] !== '') {
            $where[] = 'p.price >= ?';
            $types .= 'i';
            $params[] = (int) $filters['min_price'];
        }
        if (isset($filters['max_price']) && $filters['max_price'] !== '') {
            $where[] = 'p.price <= ?';
            $types .= 'i';
            $params[] = (int) $filters['max_price'];
        }

        $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $sort = $filters['sort'] ?? 'newest';

        $orderSql = match ($sort) {
            'oldest' => 'p.created_at ASC, p.id ASC',
            'price_asc' => 'p.price ASC, p.id DESC',
            'price_desc' => 'p.price DESC, p.id DESC',
            'name' => 'p.name ASC',
            'stock_asc' => 'p.stock ASC, p.id DESC',
            'stock_desc' => 'p.stock DESC, p.id DESC',
            // پرفروش‌ها: بر اساس مجموع تعداد فروخته‌شده در سفارش‌های «پرداخت‌شده»؛ بین هم‌تعدادها جدیدتر جلوتره
            'bestseller' => 'sold DESC, p.created_at DESC, p.id DESC',
            default => 'p.created_at DESC, p.id DESC',
        };

        $stmt = mysqli_prepare($this->conn, "SELECT COUNT(*) AS c FROM products p $whereSql");
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $total = (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);

        $perPage = max(1, $perPage);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = ($page - 1) * $perPage;

        // کوئری مجموع فروش فقط وقتی لازمه که بر اساس پرفروش بودن مرتب کنیم
        $sql = $this->baseSelect($sort === 'bestseller') . " $whereSql ORDER BY $orderSql LIMIT ? OFFSET ?";
        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, $types . 'ii', ...array_merge($params, [$perPage, $offset]));
        mysqli_stmt_execute($stmt);

        return [
            'items' => $this->fetchAll($stmt),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'pages' => $pages,
        ];
    }

    // پرفروش‌ترین‌ها؛ تا وقتی فروشی ثبت نشده، جدیدترین محصولات نشون داده می‌شن
    public function bestsellers(int $limit = 8): array
    {
        return $this->paginate(['sort' => 'bestseller'], 1, $limit)['items'];
    }

    // محصولات هم‌دسته (به‌جز خود محصول)؛ موجودها و جدیدترها اول
    public function related(array $product, int $limit = 4): array
    {
        if (empty($product['category_id'])) {
            return [];
        }

        $stmt = mysqli_prepare(
            $this->conn,
            $this->baseSelect(false) . "
             WHERE p.deleted_at IS NULL AND p.category_id = ? AND p.id <> ?
             ORDER BY (p.stock > 0) DESC, p.created_at DESC LIMIT ?"
        );
        $categoryId = (int) $product['category_id'];
        $id = (int) $product['id'];
        mysqli_stmt_bind_param($stmt, 'iii', $categoryId, $id, $limit);
        mysqli_stmt_execute($stmt);

        return $this->fetchAll($stmt);
    }

    // برای sitemap.xml
    public function allForSitemap(): array
    {
        $result = mysqli_query($this->conn, "SELECT id, COALESCE(updated_at, created_at) AS modified FROM products WHERE deleted_at IS NULL ORDER BY id");

        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }

    public function countLowStock(): int
    {
        $result = mysqli_query(
            $this->conn,
            "SELECT COUNT(*) AS c FROM products WHERE deleted_at IS NULL AND stock <= " . self::LOW_STOCK_THRESHOLD
        );

        return (int) mysqli_fetch_assoc($result)['c'];
    }

    public function create(array $data): int
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "INSERT INTO products (category_id, name, description, price, stock, image) VALUES (?, ?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'issiis',
            $data['category_id'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['stock'],
            $data['image']
        );
        mysqli_stmt_execute($stmt);

        return mysqli_insert_id($this->conn);
    }

    public function update(int $id, array $data): bool
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, image = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'issiisi',
            $data['category_id'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['stock'],
            $data['image'],
            $id
        );

        return mysqli_stmt_execute($stmt);
    }

    /**
     * «حذف» محصول = پنهان‌کردنش از فروشگاه (deleted_at). ردیف دیتابیس می‌مونه، پس سفارش‌های قبلی،
     * آمار پرفروش‌ها و نظرات از بین نمی‌رن. از سبد و علاقه‌مندی‌ها هم خودبه‌خود حذف می‌شه.
     */
    public function softDelete(int $id): bool
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE products SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        return mysqli_stmt_affected_rows($stmt) === 1;
    }

    public function restore(int $id): bool
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE products SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        return mysqli_stmt_affected_rows($stmt) === 1;
    }

    /**
     * کم کردن موجودی به‌صورت اتمیک: شرط «stock >= تعداد» داخل خود UPDATE هست،
     * پس اگه دو نفر همزمان آخرین عدد انبار رو بخرن، فقط یکیشون موفق می‌شه (بدون Race Condition).
     */
    public function decrementStock(int $id, int $qty): bool
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
        mysqli_stmt_bind_param($stmt, 'iii', $qty, $id, $qty);
        mysqli_stmt_execute($stmt);

        return mysqli_stmt_affected_rows($stmt) === 1;
    }

    public function incrementStock(int $id, int $qty): void
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE products SET stock = stock + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $qty, $id);
        mysqli_stmt_execute($stmt);
    }

    // SELECT مشترک: اطلاعات محصول + نام دسته + میانگین و تعداد نظرها (برای ستاره‌ی روی کارت) [+ تعداد فروش]
    private function baseSelect(bool $withSales): string
    {
        $salesSelect = $withSales ? ', COALESCE(s.sold, 0) AS sold' : '';
        $salesJoin = $withSales ? "
            LEFT JOIN (
                SELECT oi.product_id, SUM(oi.quantity) AS sold
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id AND o.status = 'paid'
                GROUP BY oi.product_id
            ) s ON s.product_id = p.id" : '';

        return "
            SELECT p.*, c.name AS category_name,
                   COALESCE(rv.rating_avg, 0) AS rating_avg, COALESCE(rv.rating_count, 0) AS rating_count $salesSelect
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN (
                SELECT product_id, AVG(rating) AS rating_avg, COUNT(*) AS rating_count FROM reviews GROUP BY product_id
            ) rv ON rv.product_id = p.id $salesJoin";
    }

    private function fetchAll(mysqli_stmt $stmt): array
    {
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
    }
}
