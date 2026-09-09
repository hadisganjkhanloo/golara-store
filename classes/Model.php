<?php

/**
 * کلاس پایه (Abstract): خودش قابل ساختن نیست (نمی‌تونی "new Model()" بزنی)
 * فقط برای این ساخته شده که کلاس‌های دیگه ازش ارث‌بری کنن.
 * کارهای مشترک همه جدول‌ها (پیدا کردن، لیست گرفتن، حذف) اینجاست.
 */
abstract class Model
{
    protected static string $table;
    protected mysqli $conn;

    public function __construct()
    {
        $this->conn = Database::getConnection();
    }

    public function find(int $id): ?array
    {
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM " . static::$table . " WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        return $row ?: null;
    }

    public function all(string $orderBy = 'id DESC', ?int $limit = null): array
    {
        $sql = "SELECT * FROM " . static::$table . " ORDER BY " . $orderBy;
        if ($limit !== null) {
            $sql .= " LIMIT " . (int) $limit;
        }

        $result = mysqli_query($this->conn, $sql);
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function delete(int $id): bool
    {
        $stmt = mysqli_prepare($this->conn, "DELETE FROM " . static::$table . " WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        return mysqli_stmt_execute($stmt);
    }
}
