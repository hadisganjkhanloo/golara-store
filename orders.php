<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_customer_login();

require_once __DIR__ . '/includes/header.php';

$orderModel = new Order();
$orders = $orderModel->getByCustomer((int) $_SESSION['customer_id']);

// ۲ کوئری کل (سفارش‌ها + همه آیتم‌ها با IN)، به‌جای ۱+N کوئری تو حلقه
$orderIds = array_column($orders, 'id');
$itemsByOrder = $orderModel->getItemsForOrders($orderIds);

$status_labels = [
    Order::STATUS_PAID => 'پرداخت‌شده',
    Order::STATUS_PENDING => 'در انتظار پرداخت',
    Order::STATUS_FAILED => 'ناموفق',
];
$status_classes = [
    Order::STATUS_PAID => 'alert-success',
    Order::STATUS_PENDING => 'alert-error',
    Order::STATUS_FAILED => 'alert-error',
];
?>

<h1>سفارش‌های من</h1>

<?php if (empty($orders)): ?>
    <p style="margin-top:14px;">هنوز سفارشی ثبت نکردی. <a href="index.php">مشاهده محصولات</a></p>
<?php else: ?>
    <div style="display:flex; flex-direction:column; gap:16px; margin-top:16px;">
        <?php foreach ($orders as $order): ?>
            <div class="box" style="max-width:640px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <strong>سفارش #<?php echo $order['id']; ?></strong>
                    <span class="alert <?php echo $status_classes[$order['status']]; ?>" style="padding:4px 12px; margin:0; display:inline-block;">
                        <?php echo $status_labels[$order['status']]; ?>
                    </span>
                </div>
                <p style="margin-top:10px; font-size:13px; color:var(--plum-soft);"><?php echo $order['created_at']; ?></p>

                <ul style="margin-top:10px; font-size:14px; padding-right:18px;">
                    <?php foreach ($itemsByOrder[$order['id']] ?? [] as $item): ?>
                        <li><?php echo clean_input($item['product_name']); ?> × <?php echo $item['quantity']; ?></li>
                    <?php endforeach; ?>
                </ul>

                <p style="margin-top:10px; font-size:15px;">جمع کل: <strong class="price"><?php echo format_price($order['total_amount']); ?></strong></p>
                <?php if ($order['ref_id']): ?>
                    <p style="font-size:12px; color:var(--plum-soft); margin-top:4px;">کد پیگیری: <?php echo clean_input($order['ref_id']); ?></p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
