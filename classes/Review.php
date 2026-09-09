<?php

class Review extends Model
{
    protected static string $table = 'reviews';

    // نظرات یه محصول رو همراه با اسم مشتری (JOIN) برمی‌گردونه
    public function getForProduct(int $productId): array
    {
        $stmt = mysqli_prepare($this->conn, "
            SELECT reviews.*, customers.name AS customer_name
            FROM reviews
            JOIN customers ON reviews.customer_id = customers.id
            WHERE reviews.product_id = ?
            ORDER BY reviews.created_at DESC
        ");
        mysqli_stmt_bind_param($stmt, 'i', $productId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $reviews = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $reviews[] = $row;
        }

        return $reviews;
    }

    public function getAverageForProduct(int $productId): array
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT AVG(rating) AS average, COUNT(*) AS count FROM reviews WHERE product_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $productId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return [
            'average' => $row['average'] !== null ? round((float) $row['average'], 1) : 0,
            'count' => (int) $row['count'],
        ];
    }

    public function hasReviewed(int $productId, int $customerId): bool
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT id FROM reviews WHERE product_id = ? AND customer_id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $productId, $customerId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    // اگه به هر دلیلی (حتی race condition) نظر تکراری بود، دیتابیس با قید UNIQUE جلوش رو می‌گیره
    // و اینجا false برمی‌گردونیم، نه یه خطای خام از mysqli
    public function create(int $productId, int $customerId, int $rating, string $comment): int|false
    {
        $stmt = mysqli_prepare(
            $this->conn,
            "INSERT INTO reviews (product_id, customer_id, rating, comment) VALUES (?, ?, ?, ?)"
        );
        mysqli_stmt_bind_param($stmt, 'iiis', $productId, $customerId, $rating, $comment);

        if (!mysqli_stmt_execute($stmt)) {
            return false;
        }

        return mysqli_insert_id($this->conn);
    }

    public function countForCustomer(int $customerId): int
    {
        $stmt = mysqli_prepare($this->conn, "SELECT COUNT(*) AS c FROM reviews WHERE customer_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $customerId);
        mysqli_stmt_execute($stmt);
        return (int) (mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] ?? 0);
    }
}
