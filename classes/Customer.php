<?php

class Customer extends Model
{
    protected static string $table = 'customers';

    public function findByEmail(string $email): ?array
    {
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM customers WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $row ?: null;
    }

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    // ثبت‌نام: رمز رو خودش هش می‌کنه، مسئولیتش با خود کلاسه نه صفحه‌ای که صداش می‌زنه
    public function register(string $name, string $email, string $password): int
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = mysqli_prepare($this->conn, "INSERT INTO customers (name, email, password) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sss', $name, $email, $hashedPassword);
        mysqli_stmt_execute($stmt);

        return mysqli_insert_id($this->conn);
    }

    // ورود: خودش چک می‌کنه ایمیل هست و رمز درسته، فقط true/false برمی‌گردونه
    public function verifyLogin(string $email, string $password): ?array
    {
        $customer = $this->findByEmail($email);

        // اگه ایمیل وجود نداشته باشه هم یه بار هش بررسی می‌شه تا زمان پاسخ، وجود یا نبود ایمیل رو لو نده
        $hash = $customer['password'] ?? '$2y$10$usesomesillystringforsaltuQ3Yp1H0mU5s8nQ6cX1bV7wE9rT2yZ';
        $valid = password_verify($password, $hash);

        return ($customer && $valid) ? $customer : null;
    }

    // ---------- بازیابی رمز ----------

    /**
     * توکن یک‌بارمصرف بازیابی رمز؛ خود توکن فقط تو لینک ایمیل هست و تو دیتابیس فقط هشش ذخیره می‌شه.
     * توکن‌های قبلیِ استفاده‌نشده‌ی همین کاربر باطل می‌شن.
     */
    public function createResetToken(int $customerId): string
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);

        $stmt = mysqli_prepare($this->conn, "DELETE FROM password_resets WHERE customer_id = ? AND used_at IS NULL");
        mysqli_stmt_bind_param($stmt, 'i', $customerId);
        mysqli_stmt_execute($stmt);

        $stmt = mysqli_prepare(
            $this->conn,
            "INSERT INTO password_resets (customer_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 60 MINUTE)"
        );
        mysqli_stmt_bind_param($stmt, 'is', $customerId, $hash);
        mysqli_stmt_execute($stmt);

        return $token;
    }

    // مشتریِ صاحب این توکن (اگه توکن معتبر، مصرف‌نشده و منقضی‌نشده باشه)
    public function findByResetToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $hash = hash('sha256', $token);
        $stmt = mysqli_prepare(
            $this->conn,
            "SELECT c.* FROM password_resets r JOIN customers c ON c.id = r.customer_id
             WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW()"
        );
        mysqli_stmt_bind_param($stmt, 's', $hash);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    public function resetPassword(string $token, string $newPassword): bool
    {
        $customer = $this->findByResetToken($token);
        if (!$customer) {
            return false;
        }

        mysqli_begin_transaction($this->conn);

        $this->updatePassword((int) $customer['id'], $newPassword);

        $stmt = mysqli_prepare($this->conn, "UPDATE password_resets SET used_at = NOW() WHERE token_hash = ?");
        $hash = hash('sha256', $token);
        mysqli_stmt_bind_param($stmt, 's', $hash);
        mysqli_stmt_execute($stmt);

        mysqli_commit($this->conn);

        return true;
    }

    public function updatePassword(int $customerId, string $newPassword): void
    {
        $stmt = mysqli_prepare($this->conn, "UPDATE customers SET password = ? WHERE id = ?");
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        mysqli_stmt_bind_param($stmt, 'si', $hash, $customerId);
        mysqli_stmt_execute($stmt);
    }
}
