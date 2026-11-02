-- =========================================================
-- گل‌آرا | اسکریپت نصب کامل دیتابیس (یه فایل، یه اجرا)
-- این فایل همه جدول‌ها رو می‌سازه و داده‌های اولیه (دسته‌بندی،
-- محصولات نمونه، چند کد تخفیف) رو وارد می‌کنه.
--
-- نکته امنیتی: اکانت ادمین و کلید API اینجا نیست (رمز و کلیدِ ثابتِ داخل ریپوی عمومی
-- یعنی هر کسی می‌تونه ازش استفاده کنه). بعد از نصب، اولین بار که /admin/ رو باز کنی،
-- صفحه «ساخت اولین مدیر» میاد؛ کلید API هم از پنل مدیریت ساخته می‌شه.
--
-- اگه دیتابیس نسخه‌ی قدیمی داری، به‌جای این فایل:   php bin/migrate.php
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
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    image VARCHAR(255) DEFAULT NULL,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_products_deleted (deleted_at),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    token CHAR(40) NULL DEFAULT NULL,
    confirmed_at DATETIME NULL DEFAULT NULL,
    unsubscribed_at DATETIME NULL DEFAULT NULL
);

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    total_amount INT NOT NULL,
    subtotal INT NOT NULL DEFAULT 0,
    shipping_cost INT NOT NULL DEFAULT 0,
    discount_amount INT NOT NULL DEFAULT 0,
    coupon_code VARCHAR(40) NULL DEFAULT NULL,
    shipping_name VARCHAR(100) NULL DEFAULT NULL,
    shipping_phone VARCHAR(20) NULL DEFAULT NULL,
    shipping_city VARCHAR(80) NULL DEFAULT NULL,
    shipping_postal_code VARCHAR(10) NULL DEFAULT NULL,
    shipping_address VARCHAR(500) NULL DEFAULT NULL,
    status ENUM('pending', 'paid', 'failed') NOT NULL DEFAULT 'pending',
    fulfillment ENUM('new', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'new',
    tracking_code VARCHAR(60) NULL DEFAULT NULL,
    authority VARCHAR(64) DEFAULT NULL,
    ref_id VARCHAR(64) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- ردیف خرید سفارش‌ها هیچ‌وقت با حذف محصول پاک نمی‌شه (RESTRICT)؛ محصول با deleted_at «حذف نرم» می‌شه
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    price INT NOT NULL,
    quantity INT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
);

-- کلید خام ذخیره نمی‌شه؛ فقط هش SHA-256 و ۸ حرف اولش (برای شناسایی تو لیست)
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_hash CHAR(64) NOT NULL,
    key_prefix VARCHAR(12) NULL,
    label VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_used_at DATETIME NULL DEFAULT NULL,
    UNIQUE KEY uq_api_keys_hash (key_hash)
);

-- سبد خرید و علاقه‌مندی‌های کاربرِ واردشده (مهمان‌ها فقط تو سشن نگه داشته می‌شن)
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_product_cart (customer_id, product_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_product_wishlist (customer_id, product_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    type ENUM('percent', 'fixed') NOT NULL DEFAULT 'percent',
    value INT UNSIGNED NOT NULL,
    min_order INT UNSIGNED NOT NULL DEFAULT 0,
    max_uses INT UNSIGNED NULL DEFAULT NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NULL DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_token (token_hash),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE rate_limits (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    scope VARCHAR(40) NOT NULL,
    identifier VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_limits_lookup (scope, identifier, created_at)
);

CREATE TABLE schema_migrations (
    version VARCHAR(100) PRIMARY KEY,
    applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ---------- داده‌های اولیه ----------

INSERT INTO categories (id, name) VALUES
(1, 'آرایش صورت'),
(2, 'آرایش چشم و لب'),
(3, 'مراقبت پوست'),
(4, 'عطر و ادکلن');

-- موجودی نمونه: یکی کم‌موجود (id=8) و یکی ناموجود (id=16)، تا رفتار سایت رو بشه تست کرد
INSERT INTO products (id, category_id, name, description, price, stock, image) VALUES
(1, 1, 'کرم پودر استی لادر Double Wear', 'کرم پودر ماندگار استی لادر با پوشش کامل و مقاوم در برابر رطوبت', 1250000, 25, 'esteelauder_doublewear.jpg'),
(2, 1, 'برس آرایش صورت زوئیوا', 'برس حرفه‌ای زوئیوا برای پخش یکنواخت کرم پودر و رژگونه', 480000, 25, 'zoeva_brush.jpg'),
(3, 2, 'رژ لب مک Ruby Woo', 'رژ لب ماندگار و افسانه‌ای مک با رنگ قرمز مات خالص', 890000, 25, 'mac_rubywoo.jpg'),
(4, 2, 'ریمل لورآل Voluminous', 'ریمل حجم‌دهنده لورآل پاریس، ضدآب با برس مخصوص کرلی کردن', 420000, 25, 'loreal_voluminous.jpg'),
(5, 2, 'پالت سایه چشم گلدن رز', 'پالت ۱۲ رنگ گلدن رز با بافت نرم و پخش‌شونده', 650000, 25, 'goldenrose_palette.jpg'),
(6, 3, 'سرم ویتامین C اردینری', 'سرم روشن‌کننده The Ordinary با غلظت بالای ویتامین C', 780000, 25, 'ordinary_vitaminc.jpg'),
(7, 3, 'کرم مرطوب‌کننده سراوی', 'کرم آبرسان CeraVe با سراماید، مناسب پوست خشک و حساس', 590000, 25, 'cerave_cream.jpg'),
(8, 4, 'عطر دیور ژادور (J''adore)', 'عطر زنانه لوکس دیور با رایحه گلی و ماندگاری بالا', 2450000, 3, 'dior_jadore.jpg'),
(9, 4, 'عطر ورساچه Bright Crystal', 'ادکلن زنانه ورساچه با رایحه تازه و گلی، مناسب استفاده روزانه', 1680000, 25, 'versace_brightcrystal.jpg'),
(14, 1, 'رژگونه نیکس Blush', 'رژگونه پودری نیکس با رنگدانه بالا و پخش‌شوندگی روان', 380000, 25, 'nyx_blush.jpg'),
(15, 3, 'سرم نیاسینامید اردینری', 'سرم کنترل‌کننده چربی و منافذ The Ordinary Niacinamide 10% + Zinc 1%', 590000, 25, 'ordinary_niacinamide.jpg'),
(16, 4, 'عطر گوچی Bloom', 'عطر زنانه گوچی با رایحه گلی سفید و یاسمن، ماندگاری بالا', 2190000, 0, 'gucci_bloom.jpg');

-- دو کد تخفیف برای تست: GOLARA10 (۱۰٪ برای خرید بالای ۵۰۰ هزار تومان) و WELCOME50 (۵۰ هزار تومان برای خرید بالای ۳۰۰ هزار)
INSERT INTO coupons (code, type, value, min_order, max_uses, expires_at) VALUES
('GOLARA10', 'percent', 10, 500000, NULL, NULL),
('WELCOME50', 'fixed', 50000, 300000, 100, NULL);

-- مهاجرت‌های این نسخه از قبل اعمال شده‌ان؛ bin/migrate.php چیزی دوباره اجرا نمی‌کنه
INSERT INTO schema_migrations (version) VALUES
('001_stock_cart_wishlist'),
('002_orders_security_catalog');
