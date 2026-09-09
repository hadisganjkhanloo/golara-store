USE store_db;

-- جلوگیری از race condition: حتی اگه دو تا درخواست هم‌زمان از چک PHP (hasReviewed) رد بشن،
-- خود دیتابیس اجازه دو ردیف با یه product_id و customer_id یکسان رو نمی‌ده
ALTER TABLE reviews ADD UNIQUE KEY unique_customer_product_review (product_id, customer_id);
