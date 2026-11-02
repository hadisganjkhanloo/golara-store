<?php
// قدم ۲ از پرداخت: درگاه کاربر رو با همین آدرس برمی‌گردونه
// وظیفه ما اینجا اینه: با درگاه تایید کنیم که پول واقعاً پرداخت شده (verify)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../telegram/config.php';

start_secure_session();

$order_id = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;
$authority = $_GET['Authority'] ?? '';
$status = $_GET['Status'] ?? '';

$orderModel = new Order();
$order = $orderModel->find($order_id);

$payment_ok = false;
$message = '';

if (!$order) {
    $message = 'سفارش مورد نظر پیدا نشد.';
} elseif ($order['status'] === Order::STATUS_PAID) {
    // کاربر شاید صفحه رو رفرش کرده؛ دوباره verify نمی‌کنیم، فقط نتیجه قبلی رو نشون می‌دیم
    $payment_ok = true;
} elseif ($status !== 'OK') {
    $message = 'پرداخت توسط شما لغو شد.';
    $orderModel->markAsFailed($order_id);
} else {
    $gateway = new ZarinpalGateway(ZARINPAL_MERCHANT_ID, ZARINPAL_REQUEST_URL, ZARINPAL_VERIFY_URL, ZARINPAL_STARTPAY_URL);
    $result = $gateway->verify((int) $order['total_amount'], $authority);

    if ($result['success']) {
        $orderModel->markAsPaid($order_id, $result['ref_id']);
        $order['ref_id'] = $result['ref_id'];

        (new Cart())->clear();
        $payment_ok = true;

        // به ادمین تو تلگرام خبر می‌دیم - اگه این یه لحظه کند بشه یا شکست بخوره،
        // نباید جلوی نمایش رسید به مشتری رو بگیره؛ برای همین نتیجه‌ش رو چک نمی‌کنیم
        $telegram = new TelegramNotifier(TELEGRAM_BOT_TOKEN, TELEGRAM_ADMIN_CHAT_ID);
        $telegram->sendNewOrderNotification($order, $orderModel->getItems($order_id));
    } else {
        $message = 'پرداخت تایید نشد. اگر مبلغی کسر شده، ظرف ۷۲ ساعت برمی‌گردد.';
        $orderModel->markAsFailed($order_id);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($payment_ok): ?>
    <div class="box" style="max-width:480px; text-align:center;">
        <h1 style="font-size:22px; color:#1e7a3c;">پرداخت با موفقیت انجام شد ✓</h1>
        <p style="margin-top:14px; font-size:14px;">شماره سفارش: <strong>#<?php echo $order_id; ?></strong></p>
        <?php if (!empty($order['ref_id'])): ?>
            <p style="margin-top:6px; font-size:14px;">کد پیگیری: <strong><?php echo clean_input($order['ref_id']); ?></strong></p>
        <?php endif; ?>
        <p style="margin-top:6px; font-size:14px;">مبلغ: <strong class="price"><?php echo format_price($order['total_amount']); ?></strong></p>
        <a href="<?php echo url('orders.php'); ?>" class="btn" style="margin-top:18px;">مشاهده سفارش‌های من</a>
    </div>
<?php else: ?>
    <div class="box" style="max-width:480px; text-align:center;">
        <h1 style="font-size:22px; color:#a13a5f;">پرداخت ناموفق</h1>
        <p style="margin-top:14px; font-size:14px;"><?php echo clean_input($message); ?></p>
        <a href="<?php echo url('cart.php'); ?>" class="btn" style="margin-top:18px;">بازگشت به سبد خرید</a>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
