<?php
// اجرای مهاجرت‌های دیتابیس به ترتیب شماره:   php bin/migrate.php
//
// هر فایل داخل database/migrations/ فقط یک بار اجرا می‌شه و تو جدول schema_migrations ثبت می‌شه.
// اگه دیتابیس رو با database/install.sql ساخته باشی، همه مهاجرت‌ها از قبل ثبت‌شده‌ان و چیزی اجرا نمی‌شه.
//
// خط اول هر فایل می‌تونه  «-- skip-if: column جدول.ستون»  باشه: اگه اون ستون از قبل
// (مثلاً با اجرای دستی نسخه‌ی قبلی) وجود داشت، مهاجرت اجرا نمی‌شه و فقط ثبت می‌شه.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('این فایل فقط از خط فرمان اجرا می‌شه.');
}

require_once __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../.env')) {
    Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
}

// php bin/migrate.php --db=اسم_دیتابیس  → مهاجرت روی یه دیتابیس دیگه (مثلاً برای تست ارتقا روی یه کپی)
foreach (array_slice($argv ?? [], 1) as $arg) {
    if (preg_match('/^--db=([A-Za-z0-9_]+)$/', $arg, $m)) {
        $_ENV['DB_NAME'] = $m[1];
    }
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = Database::getConnection();
} catch (Throwable $e) {
    fwrite(STDERR, "اتصال به دیتابیس ناموفق بود: " . $e->getMessage() . PHP_EOL);
    exit(1);
}

mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS schema_migrations (
        version VARCHAR(100) PRIMARY KEY,
        applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$applied = [];
$result = mysqli_query($conn, "SELECT version FROM schema_migrations");
while ($row = mysqli_fetch_assoc($result)) {
    $applied[$row['version']] = true;
}

function column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    mysqli_stmt_bind_param($stmt, 'ss', $table, $column);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    return mysqli_stmt_num_rows($stmt) > 0;
}

$files = glob(__DIR__ . '/../database/migrations/*.sql');
sort($files);

$ran = 0;

foreach ($files as $file) {
    $version = basename($file, '.sql');

    if (isset($applied[$version])) {
        continue;
    }

    $sql = file_get_contents($file);

    // اگه نتیجه‌ی این مهاجرت از قبل تو دیتابیس هست، فقط ثبتش می‌کنیم
    if (preg_match('/^--\s*skip-if:\s*column\s+(\w+)\.(\w+)/m', $sql, $m) && column_exists($conn, $m[1], $m[2])) {
        mysqli_query($conn, "INSERT INTO schema_migrations (version) VALUES ('" . mysqli_real_escape_string($conn, $version) . "')");
        echo "[ثبت‌شده از قبل] $version" . PHP_EOL;
        continue;
    }

    echo "[اجرا] $version ... ";

    try {
        mysqli_multi_query($conn, $sql);
        do {
            if ($res = mysqli_store_result($conn)) {
                mysqli_free_result($res);
            }
        } while (mysqli_more_results($conn) && mysqli_next_result($conn));
    } catch (Throwable $e) {
        echo 'ناموفق' . PHP_EOL;
        fwrite(STDERR, '  ' . $e->getMessage() . PHP_EOL);
        fwrite(STDERR, '  (تغییرات DDL برگشت‌پذیر نیستن؛ مشکل رو برطرف کن و دوباره اجرا کن.)' . PHP_EOL);
        exit(1);
    }

    mysqli_query($conn, "INSERT INTO schema_migrations (version) VALUES ('" . mysqli_real_escape_string($conn, $version) . "')");
    echo 'تمام' . PHP_EOL;
    $ran++;
}

echo $ran === 0 ? 'دیتابیس به‌روزه؛ مهاجرت جدیدی نبود.' . PHP_EOL : "$ran مهاجرت اجرا شد." . PHP_EOL;
