USE store_db;

DELETE FROM products WHERE id IN (11, 12, 13);

INSERT INTO products (category_id, name, description, price, image) VALUES
(1, 'رژگونه نیکس Blush', 'رژگونه پودری نیکس با رنگدانه بالا و پخش‌شوندگی روان', 380000, 'nyx_blush.jpg'),
(3, 'سرم نیاسینامید اردینری', 'سرم کنترل‌کننده چربی و منافذ The Ordinary Niacinamide 10% + Zinc 1%', 590000, 'ordinary_niacinamide.jpg'),
(4, 'عطر گوچی Bloom', 'عطر زنانه گوچی با رایحه گلی سفید و یاسمن، ماندگاری بالا', 2190000, 'gucci_bloom.jpg');
