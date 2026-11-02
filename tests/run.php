<?php
// تست‌های خودکار پروژه:   php tests/run.php
//
// بدون وابستگی (PHPUnit لازم نیست). هر بار یک دیتابیس موقت از روی database/install.sql ساخته می‌شه،
// تست‌ها روی اون اجرا می‌شن و آخر کار پاک می‌شه؛ پس به دیتابیس اصلی دست نمی‌زنه.
// کد خروجی: ۰ = همه موفق، ۱ = حداقل یک تست شکست خورده.

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('فقط از خط فرمان.');
}

$root = dirname(__DIR__);
chdir($root);
require_once $root . '/vendor/autoload.php';

if (file_exists($root . '/.env')) {
    Dotenv\Dotenv::createImmutable($root)->safeLoad();
}

// ---------- دیتابیس موقت ----------
$scratch = 'golara_test_' . bin2hex(random_bytes(3));
$link = @mysqli_connect($_ENV['DB_HOST'] ?? 'localhost', $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '');
if (!$link) {
    fwrite(STDERR, "اتصال به MySQL ممکن نیست: " . mysqli_connect_error() . PHP_EOL);
    exit(1);
}
mysqli_report(MYSQLI_REPORT_OFF);
mysqli_set_charset($link, 'utf8mb4');

$sql = str_replace('store_db', $scratch, file_get_contents($root . '/database/install.sql'));
if (!mysqli_multi_query($link, $sql)) {
    fwrite(STDERR, 'ساخت دیتابیس تست ناموفق: ' . mysqli_error($link) . PHP_EOL);
    exit(1);
}
do {
    if ($r = mysqli_store_result($link)) {
        mysqli_free_result($r);
    }
} while (mysqli_more_results($link) && mysqli_next_result($link));

register_shutdown_function(function () use ($link, $scratch) {
    mysqli_query($link, "DROP DATABASE IF EXISTS `$scratch`");
});

// برنامه از این به بعد به دیتابیس تست وصل می‌شه
putenv("DB_NAME=$scratch");
$_ENV['DB_NAME'] = $_SERVER['DB_NAME'] = $scratch;
define('JSON_RESPONSE_THROW', true);

require_once $root . '/config/db.php';
require_once $root . '/includes/functions.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ---------- ابزار تست ----------
$passed = 0;
$failed = [];
$section = '';

function section(string $name): void
{
    global $section;
    $section = $name;
    echo PHP_EOL . "▸ $name" . PHP_EOL;
}

function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed, $section;
    if ($condition) {
        $passed++;
        echo "  ✓ $name" . PHP_EOL;
    } else {
        $failed[] = "[$section] $name" . ($detail !== '' ? " — $detail" : '');
        echo "  ✗ $name" . ($detail !== '' ? "  ($detail)" : '') . PHP_EOL;
    }
}

function same($expected, $actual): string
{
    return 'انتظار ' . json_encode($expected, JSON_UNESCAPED_UNICODE) . ' ولی ' . json_encode($actual, JSON_UNESCAPED_UNICODE);
}

// درخواست API رو شبیه‌سازی می‌کنه و جوابِ JsonResponse رو برمی‌گردونه
function api_call(string $method, string $path, array $headers = [], string $body = ''): array
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = BASE_PATH . '/api' . $path;
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with($k, 'HTTP_X_')) {
            unset($_SERVER[$k]);
        }
    }
    foreach ($headers as $k => $v) {
        $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
    }

    // فایل api/index.php مسیرها رو تعریف و dispatch می‌کنه؛ همون رو اجرا می‌کنیم و جواب رو می‌گیریم
    // (تو CLI تابع header() بعد از اولین خروجی هشدار می‌ده؛ برای تست بی‌اهمیته)
    $previous = error_reporting(E_ALL & ~E_WARNING);
    try {
        ob_start();
        (function () {
            include dirname(__DIR__) . '/api/index.php';
        })();
        ob_end_clean();
    } catch (JsonResponseSent $sent) {
        ob_end_clean();
        error_reporting($previous);
        return ['status' => $sent->status, 'body' => $sent->payload];
    }

    error_reporting($previous);

    return ['status' => 0, 'body' => []];
}

$customerModel = new Customer();

// ═════════════════════════════════════════════════════════════
section('تاریخ شمسی و ارقام');
[$a, $b, $c] = gregorian_to_jalali(2026, 3, 21);
check('نوروز ۱۴۰۵ = ۲۱ مارس ۲۰۲۶', [$a, $b, $c] === [1405, 1, 1], json_encode([$a, $b, $c]));
check('۲۰۲۴-۰۳-۲۰ = ۱۴۰۳/۰۱/۰۱', format_date('2024-03-20') === '1403/01/01', format_date('2024-03-20'));
check('تاریخ با ساعت', format_date('2000-01-01 08:05:00', true) === '1378/10/11 08:05', format_date('2000-01-01 08:05:00', true));
check('تاریخ خالی = رشته خالی', format_date(null) === '' && format_date('') === '');
check('ارقام فارسی و عربی به لاتین', normalize_digits('۰۹۱۲۳۴٥٦٧٨٩') === '0912345678' . '9');
check('شماره موبایل با ارقام فارسی معتبر است', (bool) is_valid_phone('۰۹۱۲۳۴۵۶۷۸۹'));
check('شماره نامعتبر رد می‌شه', !is_valid_phone('0812345678'));

// ═════════════════════════════════════════════════════════════
section('سیاست رمز عبور');
check('کوتاه رد می‌شه', password_policy_error('ab1') !== null);
check('فقط عدد رد می‌شه', password_policy_error('12345678') !== null);
check('فقط حرف رد می‌شه', password_policy_error('abcdefgh') !== null);
check('رمز رایج رد می‌شه', password_policy_error('admin123') !== null);
check('رمز خوب پذیرفته می‌شه', password_policy_error('Golara-2026') === null);

// ═════════════════════════════════════════════════════════════
section('کد تخفیف و قیمت‌گذاری');
$coupons = new Coupon();
check('درصدی: ۱۰٪ از ۱٬۰۰۰٬۰۰۰', Coupon::discountFor(['type' => 'percent', 'value' => 10], 1000000) === 100000);
check('ثابت: بیشتر از جمع سبد نمی‌شه', Coupon::discountFor(['type' => 'fixed', 'value' => 50000], 30000) === 30000);
check('کد با حروف کوچک و فاصله پیدا می‌شه', $coupons->findByCode(' golara10 ') !== null);
$v = $coupons->validate('GOLARA10', 400000);
check('کمتر از حداقل خرید رد می‌شه', !$v['ok'] && str_contains($v['error'], 'بالای'), $v['error']);
check('کد معتبر پذیرفته می‌شه', $coupons->validate('GOLARA10', 600000)['ok']);
check('کد ناموجود رد می‌شه', !$coupons->validate('NOPE', 600000)['ok']);
$coupons->create('EXPIRED1', 'fixed', 1000, 0, null, '2020-01-01 00:00:00');
check('کد منقضی رد می‌شه', !$coupons->validate('EXPIRED1', 100000)['ok']);
$coupons->create('ONCE1', 'fixed', 1000, 0, 1, null);
$coupons->incrementUsage('ONCE1');
check('کد پرشده (حداکثر دفعات) رد می‌شه', !$coupons->validate('ONCE1', 100000)['ok']);
$p = Pricing::summarize(1000000, ['code' => 'X', 'type' => 'percent', 'value' => 10]);
check('زیر آستانه: ارسال ۸۰ هزار + تخفیف', $p['discount'] === 100000 && $p['shipping'] === SHIPPING_COST && $p['total'] === 900000 + SHIPPING_COST, json_encode($p));
$p = Pricing::summarize(FREE_SHIPPING_THRESHOLD, null);
check('بالای آستانه: ارسال رایگان', $p['shipping'] === 0 && $p['total'] === FREE_SHIPPING_THRESHOLD);
check('تخفیف می‌تونه ارسال رایگان رو از بین ببره', Pricing::summarize(FREE_SHIPPING_THRESHOLD + 100000, ['code' => 'X', 'type' => 'fixed', 'value' => 200000])['shipping'] === SHIPPING_COST);

// ═════════════════════════════════════════════════════════════
section('سبد خرید: موجودی و سقف تعداد');
$_SESSION = [];
$cart = new Cart();
$r = $cart->add(3, 2);
check('افزودن عادی', $r['status'] === Cart::OK && $r['quantity'] === 2);
check('ناموجود (id=16) رد می‌شه', $cart->add(16)['status'] === Cart::OUT_OF_STOCK);
check('محصول ناموجود در دیتابیس', $cart->add(9999)['status'] === Cart::NOT_FOUND);
check('بیشتر از موجودی (id=8، موجودی ۳)', $cart->add(8, 5)['status'] === Cart::LIMIT_REACHED && $cart->add(8, 5)['max'] === 3);
check('سقف هر کالا (۱۰) حتی با موجودی بیشتر', $cart->setQuantity(3, 11)['status'] === Cart::LIMIT_REACHED && $cart->setQuantity(3, 11)['max'] === MAX_QTY_PER_ITEM);
check('تنظیم تعداد معتبر', $cart->setQuantity(3, 10)['status'] === Cart::OK && $cart->count() === 10);
check('صفر = حذف', $cart->setQuantity(3, 0)['status'] === Cart::OK && $cart->count() === 0);

$_SESSION = [];
$cart = new Cart();
$cart->add(3, 1);
$cart->add(4, 1);
$_SESSION['coupon_code'] = 'GOLARA10';
$s = $cart->summary();
check('خلاصه سبد با کد تخفیف', $s['coupon'] === 'GOLARA10' && $s['discount'] === (int) ((890000 + 420000) * 0.1), json_encode($s));
$cart->remove(3);
$s = $cart->summary();
check('با پایین‌آمدن از حداقل خرید، کد خودکار حذف می‌شه', $s['coupon'] === null && $s['coupon_error'] !== '' && empty($_SESSION['coupon_code']), json_encode($s));

// ═════════════════════════════════════════════════════════════
section('سبد و علاقه‌مندی داخل حساب + ادغام مهمان');
$custId = $customerModel->register('Test One', 'one@example.com', 'Secret-pass-1');
$_SESSION = ['cart' => [3 => 2, 8 => 9], 'wishlist' => [5, 9999]];
login_customer(['id' => $custId, 'name' => 'Test One']);
$dbCart = (new Cart($custId))->getRawItems();
check('سبد مهمان وارد دیتابیس شد', ($dbCart[3] ?? 0) === 2);
check('بیشتر از موجودی موقع ادغام کم می‌شه (id=8 → ۳)', ($dbCart[8] ?? 0) === 3, json_encode($dbCart));
check('سشن سبد بعد از ادغام خالیه', empty($_SESSION['cart']));
$wl = new Wishlist($custId);
check('علاقه‌مندی مهمان ادغام شد؛ محصول ناموجود نادیده گرفته شد', $wl->ids() === [5], json_encode($wl->ids()));
check('add ایدمپوتنت', $wl->add(5) && $wl->add(5) && count($wl->ids()) === 1);
check('remove ایدمپوتنت', ($wl->remove(5) ?? true) && ($wl->remove(5) ?? true) && $wl->ids() === []);
check('افزودن محصول ناموجود به علاقه‌مندی false می‌ده', $wl->add(9999) === false);
$_SESSION = ['customer_id' => $custId, 'customer_name' => 'Test One'];
logout_customer();
check('بعد از خروج، سشن پاک ولی دیتابیس سالمه', empty($_SESSION['customer_id']) && (new Cart($custId))->count() === 5);

// ═════════════════════════════════════════════════════════════
section('محصول: فیلتر، صفحه‌بندی، پرفروش، حذف نرم');
$products = new Product();
$res = $products->paginate([], 1, 5);
check('صفحه‌بندی: ۱۲ محصول، ۵ تا در صفحه → ۳ صفحه', $res['total'] === 12 && $res['pages'] === 3 && count($res['items']) === 5);
check('صفحه خارج از محدوده به آخرین صفحه می‌چسبه', $products->paginate([], 99, 5)['page'] === 3);
check('فیلتر دسته‌بندی', $products->paginate(['category_id' => 4], 1, 50)['total'] === 3);
check('فیلتر موجود', $products->paginate(['in_stock' => true], 1, 50)['total'] === 11);
check('فیلتر کم‌موجود', $products->paginate(['stock_state' => 'low'], 1, 50)['total'] === 1);
check('فیلتر بازه قیمت', $products->paginate(['min_price' => 500000, 'max_price' => 700000], 1, 50)['total'] === 3);
check('جستجوی نام', $products->paginate(['q' => 'سرم'], 1, 50)['total'] === 2);
check('کاراکتر % در جستجو wildcard نیست', $products->paginate(['q' => '%'], 1, 50)['total'] === 0);
check('مرتب‌سازی قیمت نزولی', $products->paginate(['sort' => 'price_desc'], 1, 3)['items'][0]['price'] === 2450000);
check('rating روی هر محصول هست', array_key_exists('rating_count', $products->paginate([], 1, 1)['items'][0]));

check('بدون فروش، پرفروش‌ها خالی از خطا و ۴ تا برمی‌گردن', count($products->bestsellers(4)) === 4);

$soft = $products->softDelete(2);
check('حذف نرم', $soft === true && $products->find(2) === null && $products->find(2, true) !== null);
check('محصول حذف‌شده تو لیست نیست', $products->paginate([], 1, 50)['total'] === 11);
check('تو حالت admin «حذف‌شده‌ها» دیده می‌شه', $products->paginate(['deleted' => 'only'], 1, 50)['total'] === 1);
check('حذف دوباره false', $products->softDelete(2) === false);
$_SESSION = [];
$guest = new Cart();
check('محصول حذف‌شده به سبد اضافه نمی‌شه', $guest->add(2)['status'] === Cart::NOT_FOUND);
check('بازگردانی', $products->restore(2) === true && $products->find(2) !== null);
$related = $products->related($products->find(3), 4);
check('محصولات مشابه: هم‌دسته و بدون خود محصول', count($related) === 2 && !in_array(3, array_column($related, 'id')));

// ═════════════════════════════════════════════════════════════
section('سفارش: رزرو موجودی، لغو، انقضا، فروش');
$orders = new Order();
$cust2 = $customerModel->register('Test Two', 'two@example.com', 'Secret-pass-2');
$ship = ['name' => 'Test Two', 'phone' => '09123456789', 'city' => 'Tehran', 'postal_code' => '1234567890', 'address' => 'Some street, No. 1, Unit 2'];
$cart2 = new Cart($cust2);
$cart2->add(3, 2);
$cart2->add(8, 1);
$items = $cart2->getItemsWithProducts();
$pricing = $cart2->summary($items);
$stock3 = (int) $products->find(3)['stock'];
$res = $orders->createWithReservation($cust2, $items, $ship, $pricing);
check('ساخت سفارش موفق', $res['ok'] && $res['order_id'] > 0);
check('موجودی رزرو شد', (int) $products->find(3)['stock'] === $stock3 - 2 && (int) $products->find(8)['stock'] === 2);
$order = $orders->find($res['order_id']);
check('مبلغ‌ها و آدرس تو سفارش ذخیره شد', (int) $order['total_amount'] === $pricing['total'] && $order['shipping_postal_code'] === '1234567890' && $order['status'] === 'pending');

$orders->markAsFailed($res['order_id']);
check('لغو: موجودی برگشت', (int) $products->find(3)['stock'] === $stock3 && (int) $products->find(8)['stock'] === 3);
$orders->markAsFailed($res['order_id']);
check('لغو دوباره موجودی رو دوبار برنمی‌گردونه', (int) $products->find(3)['stock'] === $stock3);

mysqli_query(Database::getConnection(), "UPDATE products SET stock = 1 WHERE id = 4");
$cartBig = [['product' => $products->find(3), 'qty' => 1, 'line_total' => 890000], ['product' => $products->find(4), 'qty' => 2, 'line_total' => 840000]];
$bad = $orders->createWithReservation($cust2, $cartBig, $ship, Pricing::summarize(1730000, null));
check('موجودی ناکافی: سفارش ساخته نمی‌شه', $bad['ok'] === false && str_contains($bad['error'], 'موجودی'));
check('و موجودی کالای اول هم کم نشده (rollback)', (int) $products->find(3)['stock'] === $stock3);
check('رقابت روی آخرین عدد: فقط یکی موفق', $products->decrementStock(4, 1) === true && $products->decrementStock(4, 1) === false && (int) $products->find(4)['stock'] === 0);
mysqli_query(Database::getConnection(), "UPDATE products SET stock = 25 WHERE id = 4");

$res2 = $orders->createWithReservation($cust2, $items, $ship, $pricing);
$orders->markAsPaid($res2['order_id'], 'REF1');
check('پرداخت موفق: وضعیت paid و موجودی کم می‌مونه', $orders->find($res2['order_id'])['status'] === 'paid' && (int) $products->find(3)['stock'] === $stock3 - 2);
check('لغو سفارش پرداخت‌شده موجودی رو برنمی‌گردونه', $orders->markAsFailed($res2['order_id']) === false && (int) $products->find(3)['stock'] === $stock3 - 2);
$best = $products->bestsellers(2);
check('پرفروش‌ها: بر اساس فروش واقعی (محصول ۳ با ۲ عدد اول)', (int) $best[0]['id'] === 3 && (int) $best[0]['sold'] === 2, json_encode(array_column($best, 'id')));
$products->softDelete(3);
check('حذف نرم محصول فروخته‌شده: ردیف‌های سفارش می‌مونن', count($orders->getItems($res2['order_id'])) === 2);
check('با حذف نرم، شمارنده سبد/لیست محصول حذف‌شده رو نمی‌شمره', (new Cart($custId))->count() === 3);
$products->restore(3);
$hard = false;
try {
    mysqli_query(Database::getConnection(), 'DELETE FROM products WHERE id = 3');
} catch (mysqli_sql_exception $e) {
    $hard = true;
}
check('حذف مستقیم محصولِ فروخته‌شده از دیتابیس ممکن نیست (RESTRICT)', $hard);

$old = $orders->createWithReservation($cust2, [['product' => $products->find(5), 'qty' => 2, 'line_total' => 1300000]], $ship, Pricing::summarize(1300000, null));
mysqli_query(Database::getConnection(), 'UPDATE orders SET created_at = NOW() - INTERVAL 45 MINUTE WHERE id = ' . (int) $old['order_id']);
$stock5 = (int) $products->find(5)['stock'];
$orders->releaseExpired();
check('سفارش رهاشده بعد از ۳۰ دقیقه آزاد می‌شه', $orders->find($old['order_id'])['status'] === 'failed' && (int) $products->find(5)['stock'] === $stock5 + 2);
$orders->markAsPaid($old['order_id'], 'LATE');
check('پرداخت دیرهنگام بعد از انقضا: موجودی دوباره کم می‌شه', (int) $products->find(5)['stock'] === $stock5);
check('سفارش مشتری دیگه برای این مشتری دیده نمی‌شه', $orders->findForCustomer($res2['order_id'], $custId) === null && $orders->findForCustomer($res2['order_id'], $cust2) !== null);
check('وضعیت ارسال فقط برای پرداخت‌شده', $orders->updateFulfillment($res2['order_id'], 'shipped', 'RR12-3') === true && $orders->find($res2['order_id'])['tracking_code'] === 'RR12-3' && $orders->updateFulfillment($res['order_id'], 'shipped', null) === false);
check('وضعیت نامعتبر رد می‌شه', $orders->updateFulfillment($res2['order_id'], 'hacked', null) === false);

// ═════════════════════════════════════════════════════════════
section('نرخ‌محدودی (RateLimiter)');
check('اول آزاد', !RateLimiter::tooMany('t', 'k', 3, 60));
RateLimiter::hit('t', 'k');
RateLimiter::hit('t', 'K'); // بزرگ/کوچک‌بودن حروف فرقی نداره
RateLimiter::hit('t', 'k');
check('بعد از ۳ تلاش قفل می‌شه', RateLimiter::tooMany('t', 'k', 3, 60));
check('هویت دیگه قفل نیست', !RateLimiter::tooMany('t', 'other', 3, 60));
check('زمان انتظار مثبت و حداکثر پنجره', ($w = RateLimiter::retryAfter('t', 'k', 60)) >= 1 && $w <= 60, (string) $w);
RateLimiter::clear('t', 'k');
check('بعد از clear آزاد', !RateLimiter::tooMany('t', 'k', 3, 60));

// ═════════════════════════════════════════════════════════════
section('کلید API، مدیر و رمز');
$keys = new ApiKey();
$k = $keys->generate('test');
check('کلید با پیشوند gk_ و ۴۳ حرف', str_starts_with($k['key'], 'gk_') && strlen($k['key']) === 43);
check('فقط هش ذخیره می‌شه', mysqli_fetch_assoc(mysqli_query(Database::getConnection(), "SELECT COUNT(*) c FROM api_keys WHERE key_hash = '" . $k['key'] . "'"))['c'] === '0');
check('تأیید کلید درست', $keys->verify($k['key']) !== null);
check('کلید غلط', $keys->verify($k['key'] . 'x') === null && $keys->verify('') === null);
$keys->delete($k['id']);
check('کلید باطل‌شده کار نمی‌کنه', $keys->verify($k['key']) === null);

$admins = new Admin();
check('در نصب تازه مدیری وجود ندارد (رمز پیش‌فرض نیست)', $admins->total() === 0);
$admins->create('boss', 'Strong-pass-9');
check('ورود مدیر با رمز درست', $admins->verifyLogin('boss', 'Strong-pass-9') !== null);
check('رمز غلط و کاربر ناموجود', $admins->verifyLogin('boss', 'bad') === null && $admins->verifyLogin('ghost', 'x') === null);
check('رمز پیش‌فرض قدیمی شناسایی می‌شه', Admin::isKnownDefaultPassword('admin123') && !Admin::isKnownDefaultPassword('Strong-pass-9'));

$tok = $customerModel->createResetToken($custId);
check('توکن بازیابی ۶۴ حرف و معتبر', strlen($tok) === 64 && $customerModel->findByResetToken($tok) !== null);
check('فقط هش توکن تو دیتابیسه', mysqli_fetch_assoc(mysqli_query(Database::getConnection(), "SELECT COUNT(*) c FROM password_resets WHERE token_hash = '$tok'"))['c'] === '0');
check('تغییر رمز با توکن', $customerModel->resetPassword($tok, 'Brand-new-pass-5') === true && $customerModel->verifyLogin('one@example.com', 'Brand-new-pass-5') !== null);
check('توکن یک‌بارمصرف است', $customerModel->resetPassword($tok, 'Another-pass-6') === false && $customerModel->findByResetToken($tok) === null);
check('توکن بی‌معنی', $customerModel->findByResetToken('zzz') === null);

// ═════════════════════════════════════════════════════════════
section('خبرنامه: تأیید دومرحله‌ای و لغو عضویت');
$news = new Newsletter();
$sub = $news->subscribe('n@example.com');
check('عضویت جدید منتظر تأیید است', $sub['status'] === Newsletter::PENDING && $news->findByEmail('n@example.com')['confirmed_at'] === null);
check('لینک تأیید عضویت را فعال می‌کند', $news->confirm($sub['token']) !== null && $news->findByEmail('n@example.com')['confirmed_at'] !== null);
check('درخواست دوباره برای عضو فعال = already', $news->subscribe('n@example.com')['status'] === Newsletter::ALREADY);
$news->unsubscribe($sub['token']);
check('لغو عضویت', $news->findByEmail('n@example.com')['unsubscribed_at'] !== null);
check('بعد از لغو، عضویت دوباره نیاز به تأیید جدید دارد', ($again = $news->subscribe('n@example.com'))['status'] === Newsletter::PENDING && $again['token'] !== $sub['token']);
check('توکن نامعتبر', $news->confirm('nope') === null);

// ═════════════════════════════════════════════════════════════
section('روتر و قالب JSON');
$r = api_call('GET', '/v1/products?per_page=3');
check('GET /products → ۲۰۰ و meta', $r['status'] === 200 && $r['body']['success'] === true && $r['body']['data']['meta']['total'] === 12);
$_GET = ['per_page' => '3'];
$r = api_call('GET', '/v1/products/3');
check('GET /products/{id}', $r['status'] === 200 && $r['body']['data']['id'] === 3 && $r['body']['data']['category_name'] !== null);
check('محصول ناموجود → ۴۰۴', api_call('GET', '/v1/products/9999')['status'] === 404);
check('مسیر ناشناخته → ۴۰۴ با قالب خطا', ($x = api_call('GET', '/v1/nothing'))['status'] === 404 && $x['body']['success'] === false);
check('متد غیرمجاز → ۴۰۵', api_call('PATCH', '/v1/products')['status'] === 405);
check('بدون کلید API → ۴۰۱', api_call('POST', '/v1/products')['status'] === 401);
check('کلید اشتباه → ۴۰۱', api_call('POST', '/v1/products', ['X-API-Key' => 'wrong'])['status'] === 401);
$k2 = $keys->generate('router');
$_POST = [];
check('کلید درست ولی بدنه خالی → ۴۲۲ با errors', ($x = api_call('POST', '/v1/products', ['X-API-Key' => $k2['key']]))['status'] === 422 && isset($x['body']['errors']['name']), json_encode($x));
$id = $products->create(['category_id' => 1, 'name' => 'T', 'description' => '', 'price' => 1000, 'stock' => 1, 'image' => null]);
check('DELETE با کلید → حذف نرم', api_call('DELETE', '/v1/products/' . $id, ['X-API-Key' => $k2['key']])['status'] === 200 && $products->find($id) === null && $products->find($id, true) !== null);
$_GET = ['sort' => 'evil'];
check('sort نامعتبر → ۴۲۲', api_call('GET', '/v1/products')['status'] === 422);
$_GET = [];
check('GET /categories شامل product_count', ($x = api_call('GET', '/v1/categories'))['status'] === 200 && isset($x['body']['data'][0]['product_count']));

// ═════════════════════════════════════════════════════════════
section('نتیجه');
echo PHP_EOL . "$passed تست موفق، " . count($failed) . ' ناموفق' . PHP_EOL;
foreach ($failed as $f) {
    echo "  ✗ $f" . PHP_EOL;
}
exit(count($failed) === 0 ? 0 : 1);
