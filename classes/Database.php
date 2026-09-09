<?php

/**
 * الگوی طراحی Singleton: هر چقدر هم که new Database بسازیم،
 * همیشه یه اتصال واحد به دیتابیس داریم (نه یه اتصال جدید هر بار)
 */
class Database
{
    private static ?mysqli $connection = null;

    // private یعنی هیچ‌کس از بیرون کلاس نمی‌تونه با "new Database()" شیء بسازه
    private function __construct()
    {
    }

    public static function getConnection(): mysqli
    {
        if (self::$connection === null) {
            self::$connection = mysqli_connect(
                $_ENV['DB_HOST'] ?? 'localhost',
                $_ENV['DB_USER'] ?? 'root',
                $_ENV['DB_PASS'] ?? '',
                $_ENV['DB_NAME'] ?? 'store_db'
            );

            if (!self::$connection) {
                die('خطا در اتصال به دیتابیس: ' . mysqli_connect_error());
            }

            mysqli_set_charset(self::$connection, 'utf8mb4');
        }

        return self::$connection;
    }
}
