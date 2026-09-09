<?php

class Product extends Model
{
    protected static string $table = 'products';

    public function getByCategory(int $categoryId, ?int $limit = null): array
    {
        $sql = "SELECT * FROM products WHERE category_id = ? ORDER BY created_at DESC";
        if ($limit !== null) {
            $sql .= " LIMIT " . (int) $limit;
        }

        $stmt = mysqli_prepare($this->conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $categoryId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }

        return $products;
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
        $result = mysqli_stmt_get_result($stmt);

        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }

        return $products;
    }

    public function search(string $query): array
    {
        // فقط حروف فارسی/انگلیسی، عدد و فاصله مجاز است
        if (!preg_match('/^[\p{L}\p{N}\s]+$/u', $query)) {
            return ['error' => 'عبارت جستجو فقط می‌تواند شامل حروف، عدد و فاصله باشد.', 'products' => []];
        }

        $stmt = mysqli_prepare($this->conn, "SELECT * FROM products WHERE name LIKE ? ORDER BY created_at DESC");
        $like = '%' . $query . '%';
        mysqli_stmt_bind_param($stmt, 's', $like);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $products = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $products[] = $row;
        }

        return ['error' => null, 'products' => $products];
    }

    public function create(array $data): int
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "INSERT INTO products (category_id, name, description, price, image) VALUES (?, ?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'issis',
            $data['category_id'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['image']
        );
        mysqli_stmt_execute($stmt);

        return mysqli_insert_id($this->conn);
    }

    public function update(int $id, array $data): bool
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, image = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param(
            $stmt,
            'issisi',
            $data['category_id'],
            $data['name'],
            $data['description'],
            $data['price'],
            $data['image'],
            $id
        );

        return mysqli_stmt_execute($stmt);
    }
}
