-- skip-if: column products.deleted_at
-- =========================================================
-- مهاجرت ۰۰۲:
--  ۱) حذف نرم محصول (تاریخچه فروش با حذف محصول پاک نشه)
--  ۲) آدرس تحویل، هزینه ارسال، کد تخفیف و وضعیت ارسال تو سفارش
--  ۳) کلید API هش‌شده (کلید خام دیگه تو دیتابیس نیست)
--  ۴) رمز عوض‌کردن اجباری ادمین پیش‌فرض، بازیابی رمز مشتری
--  ۵) تأیید دومرحله‌ای و لغو عضویت خبرنامه
--  ۶) جدول نرخ‌محدودی (rate limit)
-- =========================================================

-- ---------- ۱) حذف نرم محصول ----------
ALTER TABLE products
    ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL AFTER image,
    ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
    ADD INDEX idx_products_deleted (deleted_at);

UPDATE products SET updated_at = created_at;

-- محصولی که فروش داشته با حذف مستقیم از دیتابیس هم پاک نمی‌شه (قبلاً CASCADE بود و ردیف خرید سفارش‌ها رو هم می‌برد)
ALTER TABLE order_items DROP FOREIGN KEY order_items_ibfk_2;
ALTER TABLE order_items
    ADD CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT;

-- ---------- ۲) سفارش ----------
ALTER TABLE orders
    ADD COLUMN subtotal INT NOT NULL DEFAULT 0 AFTER total_amount,
    ADD COLUMN shipping_cost INT NOT NULL DEFAULT 0 AFTER subtotal,
    ADD COLUMN discount_amount INT NOT NULL DEFAULT 0 AFTER shipping_cost,
    ADD COLUMN coupon_code VARCHAR(40) NULL DEFAULT NULL AFTER discount_amount,
    ADD COLUMN shipping_name VARCHAR(100) NULL DEFAULT NULL AFTER coupon_code,
    ADD COLUMN shipping_phone VARCHAR(20) NULL DEFAULT NULL AFTER shipping_name,
    ADD COLUMN shipping_city VARCHAR(80) NULL DEFAULT NULL AFTER shipping_phone,
    ADD COLUMN shipping_postal_code VARCHAR(10) NULL DEFAULT NULL AFTER shipping_city,
    ADD COLUMN shipping_address VARCHAR(500) NULL DEFAULT NULL AFTER shipping_postal_code,
    ADD COLUMN fulfillment ENUM('new', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'new' AFTER status,
    ADD COLUMN tracking_code VARCHAR(60) NULL DEFAULT NULL AFTER fulfillment;

UPDATE orders SET subtotal = total_amount;

CREATE TABLE IF NOT EXISTS coupons (
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

-- ---------- ۳) کلید API هش‌شده ----------
ALTER TABLE api_keys
    ADD COLUMN key_hash CHAR(64) NULL AFTER id,
    ADD COLUMN key_prefix VARCHAR(12) NULL AFTER key_hash,
    ADD COLUMN last_used_at DATETIME NULL DEFAULT NULL;

UPDATE api_keys SET key_hash = SHA2(api_key, 256), key_prefix = LEFT(api_key, 8);

-- این کلیدها داخل ریپوی عمومی نوشته شده بودن، پس معتبر نیستن؛ کلید جدید از پنل مدیریت ساخته می‌شه
DELETE FROM api_keys WHERE api_key IN (
    '181c932d22925cd01d7d0b75ef8b7bf35fb374b5',
    '75b361616727c9fc11de74d07b40d78291c3478f'
);

ALTER TABLE api_keys
    DROP COLUMN api_key,
    MODIFY key_hash CHAR(64) NOT NULL,
    ADD UNIQUE KEY uq_api_keys_hash (key_hash);

-- ---------- ۴) ادمین و بازیابی رمز ----------
ALTER TABLE admins
    ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_token (token_hash),
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- ---------- ۵) خبرنامه ----------
ALTER TABLE newsletter
    ADD COLUMN token CHAR(40) NULL DEFAULT NULL,
    ADD COLUMN confirmed_at DATETIME NULL DEFAULT NULL,
    ADD COLUMN unsubscribed_at DATETIME NULL DEFAULT NULL;

UPDATE newsletter SET token = SHA1(CONCAT(email, RAND(), NOW())), confirmed_at = created_at;

-- ---------- ۶) نرخ‌محدودی ----------
CREATE TABLE IF NOT EXISTS rate_limits (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    scope VARCHAR(40) NOT NULL,
    identifier VARCHAR(190) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_limits_lookup (scope, identifier, created_at)
);

-- دو کد تخفیف نمونه برای تست (مثل install.sql)
INSERT IGNORE INTO coupons (code, type, value, min_order, max_uses, expires_at) VALUES
('GOLARA10', 'percent', 10, 500000, NULL, NULL),
('WELCOME50', 'fixed', 50000, 300000, 100, NULL);
