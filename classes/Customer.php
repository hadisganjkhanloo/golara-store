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

        if ($customer && password_verify($password, $customer['password'])) {
            return $customer;
        }

        return null;
    }
}
