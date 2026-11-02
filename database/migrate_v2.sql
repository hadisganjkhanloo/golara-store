-- =========================================================
-- گل‌آرا | مهاجرت نسخه ۲ (فقط برای دیتابیسی که قبلاً با install.sql قدیمی ساخته شده)
-- اگه دیتابیس رو از نو با database/install.sql می‌سازی، این فایل رو اجرا نکن.
-- فقط یک بار اجرا بشه.
--
--  ۱) موجودی انبار برای هر محصول (stock)
--  ۲) سبد خرید و علاقه‌مندی‌ها داخل حساب کاربری (cart_items / wishlist_items)
-- =========================================================

USE store_db;

ALTER TABLE products
    ADD COLUMN stock INT UNSIGNED NOT NULL DEFAULT 0 AFTER price;

-- موجودی نمونه (یکی کم‌موجود و یکی ناموجود، تا رفتار سایت رو بشه تست کرد)
UPDATE products SET stock = 25;
UPDATE products SET stock = 3 WHERE id = 8;
UPDATE products SET stock = 0 WHERE id = 16;

CREATE TABLE IF NOT EXISTS cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_product_cart (customer_id, product_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS wishlist_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_customer_product_wishlist (customer_id, product_id),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);
