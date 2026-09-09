-- دیتابیس فروشگاه اینترنتی
CREATE DATABASE IF NOT EXISTS store_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE store_db;

CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL
);

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price INT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO categories (name) VALUES ('لوازم دیجیتال'), ('پوشاک'), ('کتاب و لوازم تحریر');

INSERT INTO products (category_id, name, description, price, image) VALUES
(1, 'هدفون بی‌سیم', 'هدفون بلوتوثی با کیفیت صدای بالا و باتری ۲۰ ساعته', 850000, NULL),
(1, 'پاوربانک ۲۰۰۰۰', 'شارژر همراه با دو پورت خروجی سریع', 620000, NULL),
(2, 'تی‌شرت مردانه', 'تی‌شرت نخی سایزبندی کامل', 210000, NULL),
(3, 'دفتر یادداشت', 'دفتر ۱۰۰ برگ جلد سخت', 65000, NULL);

-- رمز عبور پیش‌فرض ادمین: admin123 (هش شده با password_hash در فایل seed_admin.php ساخته میشه)
