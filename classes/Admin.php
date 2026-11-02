<?php

class Admin extends Model
{
    protected static string $table = 'admins';

    // رمزهایی که تو نسخه‌های قبلیِ پروژه (و ریپوی عمومی) نوشته شده بودن؛ ادمینی که با این رمز وارد بشه مجبوره عوضش کنه
    private const KNOWN_DEFAULT_PASSWORDS = ['admin123'];

    public function findByUsername(string $username): ?array
    {
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM admins WHERE username = ?");
        mysqli_stmt_bind_param($stmt, 's', $username);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    public function total(): int
    {
        return (int) mysqli_fetch_assoc(mysqli_query($this->conn, "SELECT COUNT(*) AS c FROM admins"))['c'];
    }

    public function verifyLogin(string $username, string $password): ?array
    {
        $admin = $this->findByUsername($username);

        // حتی اگه کاربر وجود نداشته باشه، یه بار password_verify اجرا می‌شه تا زمان پاسخ فرقی نکنه
        // و از روی سرعت جواب نشه فهمید نام کاربری درسته یا نه
        $hash = $admin['password'] ?? '$2y$10$usesomesillystringforsaltuQ3Yp1H0mU5s8nQ6cX1bV7wE9rT2yZ';
        $valid = password_verify($password, $hash);

        return ($admin && $valid) ? $admin : null;
    }

    public function create(string $username, string $password): int
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($this->conn, "INSERT INTO admins (username, password) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'ss', $username, $hash);
        mysqli_stmt_execute($stmt);

        return mysqli_insert_id($this->conn);
    }

    public function changePassword(int $id, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = mysqli_prepare($this->conn, "UPDATE admins SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
        mysqli_stmt_execute($stmt);
    }

    public static function isKnownDefaultPassword(string $password): bool
    {
        return in_array($password, self::KNOWN_DEFAULT_PASSWORDS, true);
    }
}
