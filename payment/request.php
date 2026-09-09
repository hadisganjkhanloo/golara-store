<?php
// قدم ۱ از پرداخت: سفارش رو از روی سبد خرید می‌سازیم و به درگاه درخواست می‌فرستیم

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_customer_login();

$cart = new Cart();

if ($cart->isEmpty()) {
    redirect('/danshjoyar/cart.php');
}

$cartItems = $cart->getItemsWithProducts();
$total = $cart->getTotal();

if ($total <= 0) {
    redirect('/danshjoyar/cart.php');
}

// سفارش رو با وضعیت pending ثبت می‌کنیم؛ فقط بعد از تایید درگاه، paid می‌شه
$orderModel = new Order();
$order_id = $orderModel->createFromCartItems((int) $_SESSION['customer_id'], $cartItems, $total);

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
        <a href="/danshjoyar/cart.php" class="btn" style="margin-top:16px;">بازگشت به سبد خرید</a>
    </div>
    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$orderModel->setAuthority($order_id, $result['authority']);

// کاربر رو به صفحه پرداخت درگاه می‌فرستیم
redirect($gateway->getPaymentUrl($result['authority']));
