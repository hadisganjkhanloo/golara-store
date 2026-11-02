<?php

class Newsletter extends Model
{
    protected static string $table = 'newsletter';

    // true = ایمیل تازه ثبت شد، false = از قبل عضو بود
    public function subscribe(string $email): bool
    {
        $stmt = mysqli_prepare($this->conn, "INSERT IGNORE INTO newsletter (email) VALUES (?)");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);

        return mysqli_stmt_affected_rows($stmt) === 1;
    }
}
