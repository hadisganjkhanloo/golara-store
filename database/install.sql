-- =========================================================
-- گل‌آرا | اسکریپت نصب کامل دیتابیس (یه فایل، یه اجرا)
-- این فایل همه جدول‌ها رو می‌سازه و داده‌های اولیه (دسته‌بندی،
-- محصولات نمونه، اکانت ادمین، کلید API تستی) رو وارد می‌کنه.
-- =========================================================

CREATE DATABASE IF NOT EXISTS store_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE store_db;

-- ---------- جدول‌ها ----------

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

CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    customer_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_product_review (product_id, customer_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    total_amount INT NOT NULL,
    status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    authority VARCHAR(64) DEFAULT NULL,
    ref_id VARCHAR(64) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    price INT NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    api_key VARCHAR(64) NOT NULL UNIQUE,
    label VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ---------- داده‌های اولیه ----------

INSERT INTO categories (id, name) VALUES
(1, 'آرایش صورت'),
(2, 'آرایش چشم و لب'),
(3, 'مراقبت پوست'),
(4, 'عطر و ادکلن');

INSERT INTO products (id, category_id, name, description, price, image) VALUES
(1, 1, 'کرم پودر استی لادر Double Wear', 'کرم پودر ماندگار استی لادر با پوشش کامل و مقاوم در برابر رطوبت', 1250000, 'esteelauder_doublewear.jpg'),
(2, 1, 'برس آرایش صورت زوئیوا', 'برس حرفه‌ای زوئیوا برای پخش یکنواخت کرم پودر و رژگونه', 480000, 'zoeva_brush.jpg'),
(3, 2, 'رژ لب مک Ruby Woo', 'رژ لب ماندگار و افسانه‌ای مک با رنگ قرمز مات خالص', 890000, 'mac_rubywoo.jpg'),
(4, 2, 'ریمل لورآل Voluminous', 'ریمل حجم‌دهنده لورآل پاریس، ضدآب با برس مخصوص کرلی کردن', 420000, 'loreal_voluminous.jpg'),
(5, 2, 'پالت سایه چشم گلدن رز', 'پالت ۱۲ رنگ گلدن رز با بافت نرم و پخش‌شونده', 650000, 'goldenrose_palette.jpg'),
(6, 3, 'سرم ویتامین C اردینری', 'سرم روشن‌کننده The Ordinary با غلظت بالای ویتامین C', 780000, 'ordinary_vitaminc.jpg'),
(7, 3, 'کرم مرطوب‌کننده سراوی', 'کرم آبرسان CeraVe با سراماید، مناسب پوست خشک و حساس', 590000, 'cerave_cream.jpg'),
(8, 4, 'عطر دیور ژادور (J''adore)', 'عطر زنانه لوکس دیور با رایحه گلی و ماندگاری بالا', 2450000, 'dior_jadore.jpg'),
(9, 4, 'عطر ورساچه Bright Crystal', 'ادکلن زنانه ورساچه با رایحه تازه و گلی، مناسب استفاده روزانه', 1680000, 'versace_brightcrystal.jpg'),
(14, 1, 'رژگونه نیکس Blush', 'رژگونه پودری نیکس با رنگدانه بالا و پخش‌شوندگی روان', 380000, 'nyx_blush.jpg'),
(15, 3, 'سرم نیاسینامید اردینری', 'سرم کنترل‌کننده چربی و منافذ The Ordinary Niacinamide 10% + Zinc 1%', 590000, 'ordinary_niacinamide.jpg'),
(16, 4, 'عطر گوچی Bloom', 'عطر زنانه گوچی با رایحه گلی سفید و یاسمن، ماندگاری بالا', 2190000, 'gucci_bloom.jpg');

-- یوزر ادمین پیش‌فرض: admin / admin123
INSERT INTO admins (username, password) VALUES
('admin', '$2y$10$HF3K/MfBGFlk/2V2fz2Cf.W/XUo9faLdTpFK7w5bz1L/WH4z4kCVC');

-- یه کلید API تستی برای امتحان کردن api/v1/products.php
INSERT INTO api_keys (api_key, label) VALUES
('181c932d22925cd01d7d0b75ef8b7bf35fb374b5', 'کلید تست پیش‌فرض');
