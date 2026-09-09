# گل‌آرا 🌸 — فروشگاه اینترنتی لوازم آرایشی

پروژه عملی دوره جامع PHP (دانشجویار) — یه فروشگاه اینترنتی واقعی با PHP رویه‌ای و شی‌گرا، MySQL، Ajax، درگاه پرداخت زرین‌پال، API، دستیار هوش مصنوعی و ربات تلگرام.

## پیش‌نیازها

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL/MariaDB + PHP 8.0 یا بالاتر)
- [Composer](https://getcomposer.org/) (یا همون `composer.phar` که کنار پروژه هست)
- یه مرورگر

## نصب (قدم‌به‌قدم)

### ۱. کپی پروژه
فایل‌ها رو تو پوشه `htdocs` اکسمپ کپی کن (مثلاً `C:\xampp\htdocs\danshjoyar`).

### ۲. ساخت دیتابیس
Apache و MySQL رو از پنل کنترل XAMPP روشن کن، بعد یکی از این دو راه رو انتخاب کن:

**راه ساده (پیشنهادی):** فایل `database/install.sql` رو تو phpMyAdmin ایمپورت کن — همه جدول‌ها و داده‌های اولیه (دسته‌بندی، محصولات نمونه، اکانت ادمین، یه کلید API تستی) با یه اجرا ساخته می‌شن.

یا از خط فرمان:
```bash
mysql -u root < database/install.sql
```

### ۳. تنظیم متغیرهای محیطی
فایل `.env.example` رو کپی کن و اسمش رو بگذار `.env`، بعد مقادیرش رو پر کن:

```bash
cp .env.example .env
```

| متغیر | توضیح |
|---|---|
| `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME` | اطلاعات اتصال دیتابیس (پیش‌فرض XAMPP: `root` بدون رمز) |
| `GEMINI_API_KEY` | کلید رایگان از [aistudio.google.com/apikey](https://aistudio.google.com/apikey) — برای دستیار هوشمند |
| `ZARINPAL_MERCHANT_ID` | برای تست، همون `00000000-0000-0000-0000-000000000000` کافیه (Sandbox) |
| `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ADMIN_CHAT_ID` | برای اطلاع‌رسانی سفارش جدید تو تلگرام (اختیاری) |

> بدون این کلیدها هم سایت کامل کار می‌کنه؛ فقط چت هوشمند و اعلان تلگرام غیرفعال می‌مونن.

### ۴. نصب پکیج‌های Composer
```bash
php composer.phar install
```
(اگه Composer رو جدا نصب کردی، به‌جاش بزن: `composer install`)

### ۵. اجرا
برو به آدرس:
```
http://localhost/danshjoyar/
```

## دسترسی‌های پیش‌فرض

| نقش | آدرس | یوزر | رمز |
|---|---|---|---|
| پنل مدیریت | `/admin/login.php` | `admin` | `admin123` |
| حساب مشتری | `/register.php` | — بساز خودت — | — |

---

## راهنمای تست (چی رو کجا ببینم)

### فروشگاه عمومی
- **صفحه اصلی** (`index.php`): هیرو اسلایدر، پرفروش‌ترین‌ها، دسته‌بندی‌ها، دکمه «نمایش بیشتر»
- **جستجوی زنده** (`search.php`): تایپ کن، بدون فشردن دکمه نتیجه میاد (Ajax + debounce)
- **صفحه محصول** (`product.php?id=1`): افزودن به سبد/علاقه‌مندی بدون رفرش، بخش نظرات و امتیاز
- **سبد خرید و پرداخت** (`cart.php`): نیاز به ورود مشتری داره، بعد «تسویه حساب» می‌بره به درگاه Sandbox زرین‌پال

### حساب کاربری مشتری
1. تو `register.php` ثبت‌نام کن
2. یه محصول به سبد اضافه کن و پرداخت کن (تو صفحه زرین‌پال فقط کافیه رو دکمه «پرداخت» بزنی، چون Sandboxه)
3. تو `orders.php` سفارش پرداخت‌شده رو ببین
4. تو `product.php` یه نظر و امتیاز ثبت کن

### پنل مدیریت (`/admin/`)
- افزودن محصول با آپلود عکس (بدون رفرش صفحه، با پیش‌نمایش آنی عکس)
- ویرایش و حذف محصول
- بعد از ۳ بار ورود غلط، یه سوال امنیتی (کپچای ریاضی) اضافه می‌شه

### API (برای تست با Postman یا curl)
```bash
# لیست محصولات (بدون نیاز به کلید)
curl http://localhost/danshjoyar/api/v1/products.php

# ساخت محصول جدید (نیاز به کلید API - از جدول api_keys)
curl -X POST http://localhost/danshjoyar/api/v1/products.php \
  -H "Content-Type: application/json" \
  -H "X-API-Key: <کلید_تو_جدول_api_keys>" \
  -d '{"name":"تست","price":100000,"category_id":1}'
```

### دستیار هوش مصنوعی
دکمه صورتی گوشه پایین سایت — نیاز به `GEMINI_API_KEY` تو `.env` داره.

---

## معماری پروژه

```
classes/     کلاس‌های OOP (Database Singleton, Model پایه, Product/Category/Customer/Order/Cart/Wishlist/Review)
ajax/        اندپوینت‌های Ajax (سبد خرید، علاقه‌مندی، نظرات، جستجوی زنده، چت هوشمند)
api/v1/      REST API عمومی محصولات و دسته‌بندی‌ها
payment/     اتصال به درگاه زرین‌پال (Interface + کلاس ZarinpalGateway)
ai/          تنظیمات دستیار هوشمند (Gemini)
telegram/    اطلاع‌رسانی سفارش جدید به ادمین
admin/       پنل مدیریت محصولات
database/    اسکریپت‌های SQL (install.sql = نسخه کامل و یکجا)
```

امنیت: همه‌جا Prepared Statement (ضد SQL Injection)، `htmlspecialchars` روی خروجی‌ها (ضد XSS)، توکن CSRF روی فرم‌های حساس، کپچای ریاضی بعد از چند تلاش ناموفق ورود (ضد Brute Force)، و قید `UNIQUE` دیتابیسی روی نظرات (ضد Race Condition).
