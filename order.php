<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();
require_customer_login();

$orderModel = new Order();
// فقط سفارش‌های خود همین مشتری؛ با عوض‌کردن id تو آدرس نمی‌شه سفارش کس دیگه‌ای رو دید
$order = $orderModel->findForCustomer((int) ($_GET['id'] ?? 0), (int) $_SESSION['customer_id']);

if (!$order) {
    http_response_code(404);
}

set_seo(['title' => $order ? 'فاکتور سفارش #' . $order['id'] : 'سفارش پیدا نشد', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';

if (!$order) {
    echo '<p>سفارش مورد نظر پیدا نشد. <a href="' . url('orders.php') . '">بازگشت به سفارش‌ها</a></p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$items = $orderModel->getItems((int) $order['id']);
$steps = [Order::FULFILLMENT_NEW, Order::FULFILLMENT_PROCESSING, Order::FULFILLMENT_SHIPPED, Order::FULFILLMENT_DELIVERED];
$current_step = array_search($order['fulfillment'], $steps, true);

render_breadcrumbs([['خانه', url('index.php')], ['سفارش‌های من', url('orders.php')], ['سفارش #' . $order['id']]]);
?>

<div class="invoice">
    <div class="invoice-head">
        <div>
            <h1>فاکتور سفارش #<?php echo $order['id']; ?></h1>
            <p class="invoice-meta">تاریخ ثبت: <?php echo format_date($order['created_at'], true); ?></p>
            <p class="invoice-meta">وضعیت پرداخت: <strong><?php echo Order::STATUS_LABELS[$order['status']]; ?></strong>
                <?php if ($order['ref_id']): ?> · کد پیگیری پرداخت: <span dir="ltr"><?php echo clean_input($order['ref_id']); ?></span><?php endif; ?></p>
        </div>
        <div class="invoice-brand no-print-hide"><?php echo clean_input(SHOP_NAME); ?></div>
    </div>

    <?php if ($order['status'] === Order::STATUS_PAID): ?>
        <?php if ($order['fulfillment'] === Order::FULFILLMENT_CANCELLED): ?>
            <p class="alert alert-error">این سفارش لغو شده است.</p>
        <?php else: ?>
            <ol class="order-steps" aria-label="وضعیت ارسال">
                <?php foreach ($steps as $i => $step): ?>
                    <li class="<?php echo $i <= $current_step ? 'is-done' : ''; ?>" <?php echo $i === $current_step ? 'aria-current="step"' : ''; ?>>
                        <?php echo Order::FULFILLMENT_LABELS[$step]; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($order['tracking_code']): ?>
                <p class="invoice-meta">کد رهگیری مرسوله: <strong dir="ltr"><?php echo clean_input($order['tracking_code']); ?></strong></p>
            <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($order['shipping_address']): ?>
        <section class="invoice-section">
            <h2>مشخصات تحویل</h2>
            <p><?php echo clean_input($order['shipping_name']); ?> — <span dir="ltr"><?php echo clean_input($order['shipping_phone']); ?></span></p>
            <p><?php echo clean_input($order['shipping_city']); ?>، <?php echo nl2br(clean_input($order['shipping_address'])); ?></p>
            <p>کدپستی: <span dir="ltr"><?php echo clean_input($order['shipping_postal_code']); ?></span></p>
        </section>
    <?php endif; ?>

    <div class="table-scroll">
    <table class="invoice-table">
        <thead>
            <tr><th scope="col">کالا</th><th scope="col">قیمت واحد</th><th scope="col">تعداد</th><th scope="col">جمع</th></tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo clean_input($item['product_name']); ?></td>
                    <td><?php echo format_price($item['price']); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td><?php echo format_price($item['price'] * $item['quantity']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <?php
    render_price_summary([
        'subtotal' => (int) $order['subtotal'] ?: (int) $order['total_amount'],
        'discount' => (int) $order['discount_amount'],
        'coupon' => $order['coupon_code'],
        'shipping' => (int) $order['shipping_cost'],
        'total' => (int) $order['total_amount'],
        'free_shipping_remaining' => 0,
    ]);
    ?>

    <div class="invoice-actions no-print">
        <button type="button" class="btn" data-print>چاپ فاکتور</button>
        <a href="<?php echo url('orders.php'); ?>" class="btn btn-outline">بازگشت به سفارش‌ها</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
