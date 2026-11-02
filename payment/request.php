<?php
// قدم ۱ از پرداخت: سفارش رو از روی سبد خرید می‌سازیم و به درگاه درخواست می‌فرستیم

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

start_secure_session();

require_customer_login();

// ساخت سفارش موجودی انبار رو رزرو می‌کنه، پس باید POST + توکن CSRF باشه (نه یه لینک ساده که هر سایتی بتونه صداش بزنه)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('cart.php'));
}
verify_csrf_token();

$cart = new Cart();

if ($cart->isEmpty()) {
    redirect(url('cart.php'));
}

$orderModel = new Order();

// سفارش‌های رهاشده‌ی قبلی (این کاربر یا بقیه) موجودی انبار رو آزاد می‌کنن، قبل از اینکه موجودی جدید رو چک کنیم
$orderModel->releaseExpired();
$orderModel->cancelPendingForCustomer((int) $_SESSION['customer_id']);

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

// سفارش با وضعیت pending ساخته می‌شه و موجودی همون لحظه رزرو می‌شه؛ فقط بعد از تایید درگاه، paid می‌شه
$reservation = $orderModel->createWithReservation((int) $_SESSION['customer_id'], $cartItems);

if (!$reservation['ok']) {
    $_SESSION['cart_notice'] = $reservation['error'];
    redirect(url('cart.php'));
}

$order_id = $reservation['order_id'];
$total = (int) $orderModel->find($order_id)['total_amount'];

// درخواست پرداخت به درگاه - هرچی implements PaymentGatewayInterface باشه اینجا جواب می‌ده
$gateway = new ZarinpalGateway(ZARINPAL_MERCHANT_ID, ZARINPAL_REQUEST_URL, ZARINPAL_VERIFY_URL, ZARINPAL_STARTPAY_URL);

$result = $gateway->request(
    $total,
    'پرداخت سفارش شماره ' . $order_id . ' - فروشگاه گل‌آرا',
    ZARINPAL_CALLBACK_URL . '?order_id=' . $order_id
);

if (!$result['success']) {
    $orderModel->markAsFailed($order_id);

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
