# گل‌آرا 🌸 — فروشگاه اینترنتی لوازم آرایشی

پروژه عملی دوره جامع PHP (دانشجویار) — یه فروشگاه اینترنتی واقعی با PHP رویه‌ای و شی‌گرا، MySQL، Ajax، درگاه پرداخت زرین‌پال، API، دستیار هوش مصنوعی و ربات تلگرام.

## پیش‌نیازها

- [XAMPP](https://www.apachefriends.org/) (Apache + MySQL/MariaDB + PHP 8.0 یا بالاتر)
- [Composer](https://getcomposer.org/) (یا همون `composer.phar` که کنار پروژه هست)
- یه مرورگر

## نصب (قدم‌به‌قدم)

### ۱. کپی پروژه
فایل‌ها رو هرجا که می‌خوای قرار بده؛ اسم پوشه مهم نیست و هیچ آدرسی تو کد ثابت نشده. دو حالت رایجه:

- **زیرپوشه** تو `htdocs` اکسمپ (مثلاً `C:\xampp\htdocs\golara`) و باز کردن با `http://localhost/golara/`
- **دامنه اختصاصی** (Virtual Host / Laragon، مثلاً `http://golara-store.test`) با Document Root روی **همین پوشه پروژه**

آدرس پایه سایت (`config/app.php`) خودکار از روی دامنه و مسیر تشخیص داده می‌شه و همه لینک‌ها، CSS، عکس‌ها، درخواست‌های Ajax و آدرس بازگشت درگاه از همون ساخته می‌شن. اگه خواستی دستی ثابتش کنی، تو `.env` مقدار `APP_URL` رو بذار (مرحله ۳).

### ۲. ساخت دیتابیس
Apache و MySQL رو از پنل کنترل XAMPP روشن کن، بعد یکی از این دو راه رو انتخاب کن:

**راه ساده (پیشنهادی):** فایل `database/install.sql` رو تو phpMyAdmin ایمپورت کن — همه جدول‌ها و داده‌های اولیه (دسته‌بندی، محصولات نمونه، اکانت ادمین، یه کلید API تستی) با یه اجرا ساخته می‌شن.

یا از خط فرمان:
```bash
mysql -u root < database/install.sql
```

> اگه دیتابیس رو قبلاً با نسخه قدیمی `install.sql` ساخته بودی، به‌جای ساخت دوباره فقط یک بار `database/migrate_v2.sql` رو اجرا کن؛ ستون موجودی انبار (`stock`) و جدول‌های سبد خرید و علاقه‌مندی کاربر (`cart_items`, `wishlist_items`) رو اضافه می‌کنه.

### ۳. تنظیم متغیرهای محیطی
فایل `.env.example` رو کپی کن و اسمش رو بگذار `.env`، بعد مقادیرش رو پر کن:

```bash
cp .env.example .env
```

| متغیر | توضیح |
|---|---|
| `APP_URL` | (اختیاری) آدرس پایه سایت، مثل `https://golara-store.test` یا `http://localhost/golara`. خالی = تشخیص خودکار |
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
برو به آدرس پروژه، بسته به محل نصب، مثلاً:
```
http://localhost/golara/        (زیرپوشه htdocs)
http://golara-store.test/       (دامنه اختصاصی)
```

> فایل `.htaccess` کنار پروژه جلوی دانلود `.env`، `vendor`، `config`، `classes` و `database` رو می‌گیره؛ پس Document Root روی ریشه پروژه هم امنه (نیاز به فعال بودن `AllowOverride All` تو Apache).

## دسترسی‌های پیش‌فرض

| نقش | آدرس | یوزر | رمز |
|---|---|---|---|
| پنل مدیریت | `/admin/login.php` | `admin` | `admin123` |
| حساب مشتری | `/register.php` | — بساز خودت — | — |

---

## راهنمای تست (چی رو کجا ببینم)

### فروشگاه عمومی
- **صفحه اصلی** (`index.php`): هیرو اسلایدر، پرفروش‌ترین‌ها (بر اساس تعداد فروش واقعی سفارش‌های پرداخت‌شده)، دسته‌بندی‌ها، خبرنامه (Ajax، صفحه بالا نمی‌پره)
- **همه محصولات** (`products.php`): فیلتر دسته‌بندی/قیمت/موجودی، مرتب‌سازی و **صفحه‌بندی** (۸ محصول در هر صفحه)
- **جستجوی زنده** (`search.php`): تایپ کن، بدون فشردن دکمه نتیجه میاد (Ajax + debounce)؛ نتایج هم صفحه‌بندی دارن
- **صفحه محصول** (`product.php?id=1`): وضعیت موجودی («موجود»، «تنها N عدد باقی مانده»، «ناموجود»)، انتخاب تعداد، افزودن به سبد/علاقه‌مندی بدون رفرش، نظرات و امتیاز
- **سبد خرید** (`cart.php`): دکمه‌های + و − و حذف با Ajax؛ بیشتر از موجودی انبار نمی‌شه گذاشت
- **علاقه‌مندی‌ها** (`wishlist.php`)

**سبد خرید و علاقه‌مندی‌ها:** برای مهمان تو سشن و برای کاربر واردشده تو دیتابیس (`cart_items`, `wishlist_items`) نگه داشته می‌شن؛ پس با خروج و ورود دوباره یا از دستگاه دیگه هم سر جاشونن. موقع ورود، سبد/علاقه‌مندی مهمان با حساب ادغام می‌شه.

**همگام‌سازی تب‌ها:** سایت رو تو دو تب باز کن؛ اضافه/حذف یه محصول (سبد یا علاقه‌مندی) تو یه تب، بدون رفرش تو تب دیگه (شمارنده‌ها، قلب‌ها، محتوای صفحه سبد و علاقه‌مندی) به‌روز می‌شه (`BroadcastChannel` + تازه‌سازی موقع فوکوس).

### موجودی انبار و پرداخت
1. هر محصول `stock` داره. اضافه‌کردن بیشتر از موجودی به سبد رد می‌شه (خطای ۴۰۹ با پیام مشخص)، محصول ناموجود دکمه خرید نداره.
2. «تسویه حساب» (فقط با `POST` و توکن CSRF) اول سبد رو با انبار هماهنگ می‌کنه، بعد سفارش `pending` می‌سازه و **موجودی رو همون لحظه رزرو (کم) می‌کنه**. کم‌کردن اتمیکه (`UPDATE ... WHERE stock >= تعداد`)، پس دو نفر نمی‌تونن آخرین عدد انبار رو با هم بخرن.
3. پرداخت موفق: موجودی همون‌طور کم می‌مونه. لغو یا ناموفق بودن پرداخت، و سفارش‌های رهاشده بعد از ۳۰ دقیقه: موجودی رزروشده به انبار برمی‌گرده (فقط یک بار).

### حساب کاربری مشتری
1. تو `register.php` ثبت‌نام کن (یا وارد شو؛ سبد مهمان حفظ می‌شه)
2. یه محصول به سبد اضافه کن و پرداخت کن (تو صفحه زرین‌پال فقط کافیه رو دکمه «پرداخت» بزنی، چون Sandboxه)
3. تو `orders.php` سفارش پرداخت‌شده رو ببین
4. تو `product.php` یه نظر و امتیاز ثبت کن

### پنل مدیریت (`/admin/`)
- **محصولات:** جستجو در نام، فیلتر دسته‌بندی و وضعیت موجودی (موجود/کم‌موجود/ناموجود)، مرتب‌سازی (قیمت، موجودی، نام، تاریخ) و صفحه‌بندی
- افزودن (Ajax + آپلود عکس + پیش‌نمایش آنی) و ویرایش و حذف محصول، همراه با فیلد موجودی انبار
- **دسته‌بندی‌ها** (`admin/categories.php`): افزودن، تغییر نام و حذف (محصولات دسته حذف‌شده بدون دسته‌بندی می‌مونن)
- بعد از ۳ بار ورود غلط، یه سوال امنیتی (کپچای ریاضی) اضافه می‌شه

### API (برای تست با Postman یا curl)
همه درخواست‌های `/api/...` وارد `api/index.php` می‌شن و **روتر** (`classes/Router.php`) با (متد HTTP + آدرس) کنترلر مناسب (`classes/Api/*Controller.php`) رو پیدا می‌کنه. خروجی همه‌جا یک قالبه:

```json
{ "success": true,  "message": "...", "data": { } }
{ "success": false, "message": "...", "errors": { "price": "..." } }
```
کد وضعیت HTTP هم درست برمی‌گرده (۲۰۰/۲۰۱ موفق، ۴۰۱ نیاز به ورود/کلید، ۴۰۳ CSRF، ۴۰۴، ۴۰۹ کمبود موجودی، ۴۲۲ ورودی نامعتبر).

| متد و آدرس | توضیح |
|---|---|
| `GET /api/v1/products?page=&per_page=&q=&category_id=&in_stock=1&min_price=&max_price=&sort=` | لیست با فیلتر و صفحه‌بندی (`sort`: newest, oldest, price_asc, price_desc, name, bestseller) |
| `GET /api/v1/products/{id}` | جزئیات یک محصول |
| `POST` / `PUT` / `DELETE` ‏`/api/v1/products[/{id}]` | ساخت / ویرایش / حذف (هدر `X-API-Key`) |
| `GET /api/v1/categories`، `GET /api/v1/categories/{id}` | دسته‌بندی‌ها (با تعداد محصول) |
| `POST` / `PUT` / `DELETE` ‏`/api/v1/categories[/{id}]` | مدیریت دسته‌بندی (هدر `X-API-Key`) |

مسیرهای زیر برای خود صفحه‌های سایت‌ان (سشن + هدر `X-CSRF-Token`): `GET /state`، `GET /cart`، `POST /cart/items`، `PATCH|DELETE /cart/items/{productId}`، `GET /wishlist`، `PUT|DELETE /wishlist/{id}`، `GET /search`، `POST /newsletter`، `POST /products/{id}/reviews`، `POST|DELETE /assistant/messages`، `POST /admin/products`.

```bash
# لیست محصولات (بدون نیاز به کلید)
curl "http://golara-store.test/api/v1/products?per_page=5&sort=price_desc"

# ساخت محصول جدید (نیاز به کلید API - از جدول api_keys)
curl -X POST http://golara-store.test/api/v1/products \
  -H "Content-Type: application/json" \
  -H "X-API-Key: <کلید_تو_جدول_api_keys>" \
  -d '{"name":"تست","price":100000,"stock":10,"category_id":1}'
```
بدون `mod_rewrite` هم می‌شه با `http://.../api/index.php/v1/products` صدا زد.

### دستیار هوش مصنوعی
دکمه صورتی گوشه پایین سایت — نیاز به `GEMINI_API_KEY` تو `.env` داره.

---

## معماری پروژه

```
classes/            کلاس‌های OOP (Database Singleton, Model پایه, Product/Category/Customer/Order/Cart/Wishlist/Review/Newsletter)
classes/Router.php  روتر API  |  Request.php  |  JsonResponse.php (قالب یکدست خروجی)  |  Middleware.php (کلید API، CSRF، ورود)
classes/Api/        کنترلرهای API (Product, Category, Cart, Wishlist, Search, Newsletter, Review, Assistant, State, AdminProduct)
api/index.php       نقطه ورود API؛ تعریف همه مسیرها اینجاست   |   api/.htaccess  (rewrite)
assets/js/app.js    کلاینت API، همگام‌سازی تب‌ها، سبد، علاقه‌مندی، خبرنامه، چت
payment/            اتصال به درگاه زرین‌پال (Interface + کلاس ZarinpalGateway)
ai/                 تنظیمات دستیار هوشمند (Gemini)
telegram/           اطلاع‌رسانی سفارش جدید به ادمین
admin/              پنل مدیریت (محصولات + فیلتر/جستجو/صفحه‌بندی، دسته‌بندی‌ها)
config/             app.php (آدرس پایه)، session.php (سشن امن)، db.php
database/           اسکریپت‌های SQL (install.sql = نسخه کامل، migrate_v2.sql = ارتقا از نسخه قبلی)
```

امنیت: همه‌جا Prepared Statement (ضد SQL Injection)، `htmlspecialchars` روی خروجی‌ها (ضد XSS)، توکن CSRF روی فرم‌ها و همه درخواست‌های Ajax تغییردهنده (هدر `X-CSRF-Token`)، کپچای ریاضی بعد از چند تلاش ناموفق ورود (ضد Brute Force)، و قید `UNIQUE` دیتابیسی روی نظرات (ضد Race Condition).

**سشن (`config/session.php`):** کوکی با `HttpOnly` (جاوااسکریپت بهش دسترسی نداره)، `SameSite=Lax`، و `Secure` (فقط وقتی سایت روی HTTPS هست یا `APP_URL` با `https` شروع می‌شه؛ روی `http://` مرورگر کوکی `Secure` رو قبول نمی‌کنه و ورود کار نمی‌کنه). حالت strict برای شناسه سشن فعاله و بعد از ورود (مشتری و مدیر) شناسه سشن عوض می‌شه (ضد Session Fixation).
