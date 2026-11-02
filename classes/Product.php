<?php

class Product extends Model
{
    protected static string $table = 'products';

    // موجودی کمتر یا مساوی این عدد = «کم‌موجود» (برای نمایش هشدار تو سایت و پنل مدیریت)
    public const LOW_STOCK_THRESHOLD = 5;

    public function getByCategory(int $categoryId, ?int $limit = null): array
    {
        $sql = "SELECT * FROM products WHERE category_id = ? ORDER BY created_at DESC";
        if ($limit !== null) {
            $sql .= " LIMIT " . (int) $limit;
        }

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $categoryId);
        mysqli_stmt_execute($stmt);

        return $this->fetchAll($stmt);
    }

    public function getByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));

        $stmt = mysqli_prepare($this->conn, "SELECT * FROM products WHERE id IN ($placeholders)");
        mysqli_stmt_bind_param($stmt, $types, ...$ids);
        mysqli_stmt_execute($stmt);

        return $this->fetchAll($stmt);
    }

    /**
     * لیست محصولات با فیلتر، مرتب‌سازی و صفحه‌بندی (LIMIT/OFFSET).
     * هم صفحه فروشگاه، هم جستجو، هم پنل مدیریت، هم API از همین یه متد استفاده می‌کنن.
     *
     * فیلترها: q, category_id, in_stock, stock_state (in|low|out), min_price, max_price, sort
     * sort: newest (پیش‌فرض) | oldest | price_asc | price_desc | name | bestseller
     */
    public function paginate(array $filters = [], int $page = 1, int $perPage = 12): array
    {
        $where = [];
        $types = '';
        $params = [];

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

        $orderSql = match ($filters['sort'] ?? 'newest') {
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

        $sql = "
            SELECT p.*, c.name AS category_name, COALESCE(s.sold, 0) AS sold
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            LEFT JOIN (
                SELECT oi.product_id, SUM(oi.quantity) AS sold
                FROM order_items oi
                JOIN orders o ON o.id = oi.order_id AND o.status = 'paid'
                GROUP BY oi.product_id
            ) s ON s.product_id = p.id
            $whereSql
            ORDER BY $orderSql
            LIMIT ? OFFSET ?
        ";
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
