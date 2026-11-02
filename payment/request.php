<?php
// قدم ۱ از پرداخت: مشخصات ارسال رو اعتبارسنجی می‌کنیم، سفارش رو از روی سبد خرید می‌سازیم
// (و موجودی رو رزرو می‌کنیم) و به درگاه درخواست می‌فرستیم

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

start_secure_session();

require_customer_login();

// ساخت سفارش موجودی انبار رو رزرو می‌کنه، پس باید POST + توکن CSRF باشه (نه یه لینک ساده که هر سایتی بتونه صداش بزنه)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('checkout.php'));
}
verify_csrf_token();

$customerId = (int) $_SESSION['customer_id'];

// هر مشتری در ۱۰ دقیقه حداکثر ۱۰ بار می‌تونه تسویه بزنه؛ بدون این، یه نفر می‌تونست با تکرار پشت‌سرهم همه موجودی انبار رو رزرو نگه داره
if (RateLimiter::tooMany('checkout', (string) $customerId, 10, 600)) {
    $wait = RateLimiter::humanWait(RateLimiter::retryAfter('checkout', (string) $customerId, 600));
    $_SESSION['cart_notice'] = "تعداد تلاش‌ها برای پرداخت زیاد بود؛ $wait دیگر دوباره امتحان کنید.";
    redirect(url('cart.php'));
}
RateLimiter::hit('checkout', (string) $customerId);

// ---------- مشخصات ارسال ----------
$shipping = [
    'name' => trim($_POST['name'] ?? ''),
    'phone' => normalize_digits(trim($_POST['phone'] ?? '')),
    'city' => trim($_POST['city'] ?? ''),
    'postal_code' => normalize_digits(trim($_POST['postal_code'] ?? '')),
    'address' => trim($_POST['address'] ?? ''),
];

$errors = [];
if (mb_strlen($shipping['name']) < 3 || mb_strlen($shipping['name']) > 100) {
    $errors[] = 'نام و نام خانوادگی گیرنده را کامل وارد کنید.';
}
if (!is_valid_phone($shipping['phone'])) {
    $errors[] = 'شماره موبایل معتبر نیست (فرمت صحیح: 09123456789).';
}
if (mb_strlen($shipping['city']) < 2 || mb_strlen($shipping['city']) > 80) {
    $errors[] = 'شهر را وارد کنید.';
}
if (!preg_match('/^[0-9]{10}$/', $shipping['postal_code'])) {
    $errors[] = 'کدپستی باید ۱۰ رقم باشد.';
}
if (mb_strlen($shipping['address']) < 10 || mb_strlen($shipping['address']) > 500) {
    $errors[] = 'آدرس را کامل‌تر وارد کنید (حداقل ۱۰ حرف).';
}

if (!empty($errors)) {
    $_SESSION['checkout_errors'] = $errors;
    $_SESSION['checkout_old'] = $shipping;
    redirect(url('checkout.php'));
}

$cart = new Cart();

if ($cart->isEmpty()) {
    redirect(url('cart.php'));
}

$orderModel = new Order();

// سفارش‌های رهاشده‌ی قبلی (این کاربر یا بقیه) موجودی انبار رو آزاد می‌کنن، قبل از اینکه موجودی جدید رو چک کنیم
$orderModel->releaseExpired();
$orderModel->cancelPendingForCustomer($customerId);

// موجودی ممکنه از موقع افزودن به سبد تا الان عوض شده باشه؛ سبد رو با انبار هماهنگ می‌کنیم
$changes = $cart->syncWithStock();
if (!empty($changes)) {
    $_SESSION['cart_notice'] = 'موجودی بعضی کالاها تغییر کرده بود و سبد شما به‌روز شد. لطفاً دوباره بررسی و پرداخت کنید.';
    redirect(url('cart.php'));
}

$cartItems = $cart->getItemsWithProducts();

if (empty($cartItems)) {
    redirect(url('cart.php'));
}

// مبلغ نهایی (تخفیف و ارسال) دوباره از روی سبد و کد تخفیف محاسبه می‌شه؛ به عددی که مرورگر فرستاده اعتماد نمی‌کنیم
$pricing = $cart->summary($cartItems);

// سفارش با وضعیت pending ساخته می‌شه و موجودی همون لحظه رزرو می‌شه؛ فقط بعد از تایید درگاه، paid می‌شه
$reservation = $orderModel->createWithReservation($customerId, $cartItems, $shipping, $pricing);

if (!$reservation['ok']) {
    $_SESSION['cart_notice'] = $reservation['error'];
    redirect(url('cart.php'));
}

$order_id = $reservation['order_id'];
$total = (int) $pricing['total'];

// درخواست پرداخت به درگاه - هرچی implements PaymentGatewayInterface باشه اینجا جواب می‌ده
$gateway = new ZarinpalGateway(ZARINPAL_MERCHANT_ID, ZARINPAL_REQUEST_URL, ZARINPAL_VERIFY_URL, ZARINPAL_STARTPAY_URL);

$result = $gateway->request(
    $total,
    'پرداخت سفارش شماره ' . $order_id . ' - فروشگاه ' . SHOP_NAME,
    ZARINPAL_CALLBACK_URL . '?order_id=' . $order_id
);

if (!$result['success']) {
    $orderModel->markAsFailed($order_id);

    set_seo(['title' => 'اتصال به درگاه برقرار نشد', 'noindex' => true]);
    require_once __DIR__ . '/../includes/header.php';
    ?>
    <div class="box" style="max-width:480px;">
        <h1 style="font-size:20px; color:#a13a5f;">اتصال به درگاه برقرار نشد</h1>
        <p style="margin-top:10px; font-size:14px;">لطفاً چند لحظه دیگر دوباره تلاش کنید.</p>
        <a href="<?php echo url('cart.php'); ?>" class="btn" style="margin-top:16px;">بازگشت به سبد خرید</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$orderModel->setAuthority($order_id, $result['authority']);

// کاربر رو به صفحه پرداخت درگاه می‌فرستیم
redirect($gateway->getPaymentUrl($result['authority']));
