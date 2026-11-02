<?php

/**
 * کلیدهای API. خود کلید فقط یک بار (موقع ساخت) به مدیر نشون داده می‌شه؛ تو دیتابیس فقط
 * هش SHA-256 اون ذخیره می‌شه، پس حتی با لو رفتن دیتابیس کسی کلیدهای واقعی رو نداره.
 * (کلید خودش ۱۶۰ بیت تصادفیه، پس برخلاف رمز عبور، هش سریع و ساده کافیه.)
 */
class ApiKey extends Model
{
    protected static string $table = 'api_keys';

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /** @return array{id:int, key:string} کلید خام فقط همین‌جا برمی‌گرده */
    public function generate(string $label): array
    {
        $plain = 'gk_' . bin2hex(random_bytes(20));
        $hash = self::hash($plain);
        $prefix = substr($plain, 0, 8);

        $stmt = mysqli_prepare($this->conn, "INSERT INTO api_keys (key_hash, key_prefix, label) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'sss', $hash, $prefix, $label);
        mysqli_stmt_execute($stmt);

        return ['id' => mysqli_insert_id($this->conn), 'key' => $plain];
    }

    // کلید معتبره؟ اگه آره ردیفش (برای نرخ‌محدودی هر کلید) برمی‌گرده و آخرین زمان استفاده ثبت می‌شه
    public function verify(string $plain): ?array
    {
        if ($plain === '' || strlen($plain) > 100) {
            return null;
        }

        $hash = self::hash($plain);
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM api_keys WHERE key_hash = ?");
        mysqli_stmt_bind_param($stmt, 's', $hash);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$row) {
            return null;
        }

        $stmt = mysqli_prepare($this->conn, "UPDATE api_keys SET last_used_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $row['id']);
        mysqli_stmt_execute($stmt);

        return $row;
    }

    public function list(): array
    {
        $result = mysqli_query($this->conn, "SELECT id, key_prefix, label, created_at, last_used_at FROM api_keys ORDER BY id DESC");

        return mysqli_fetch_all($result, MYSQLI_ASSOC);
    }
}
