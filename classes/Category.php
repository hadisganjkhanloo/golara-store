<?php

class Category extends Model
{
    protected static string $table = 'categories';

    // لیست دسته‌بندی‌ها همراه با تعداد محصول هرکدوم (برای پنل مدیریت)
    public function allWithProductCount(): array
    {
        $result = mysqli_query($this->conn, "
            SELECT c.*, COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
            GROUP BY c.id
            ORDER BY c.id ASC
        ");

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function nameExists(string $name, int $exceptId = 0): bool
    {
        $stmt = mysqli_prepare($this->conn, "SELECT id FROM categories WHERE name = ? AND id <> ?");
        mysqli_stmt_bind_param($stmt, 'si', $name, $exceptId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    public function create(string $name): int
    {
        $stmt = mysqli_prepare($this->conn, "INSERT INTO categories (name) VALUES (?)");
        mysqli_stmt_bind_param($stmt, 's', $name);
        mysqli_stmt_execute($stmt);

        return mysqli_insert_id($this->conn);
    }

    public function rename(int $id, string $name): bool
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE categories SET name = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $name, $id);

        return mysqli_stmt_execute($stmt);
    }
}
