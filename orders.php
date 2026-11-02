<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();
require_customer_login();

set_seo(['title' => 'سفارش‌های من', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';

$orderModel = new Order();
$orders = $orderModel->getByCustomer((int) $_SESSION['customer_id']);

// ۲ کوئری کل (سفارش‌ها + همه آیتم‌ها با IN)، به‌جای ۱+N کوئری تو حلقه
$orderIds = array_column($orders, 'id');
$itemsByOrder = $orderModel->getItemsForOrders($orderIds);

$status_classes = [
    Order::STATUS_PAID => 'alert-success',
    Order::STATUS_PENDING => 'alert-error',
    Order::STATUS_FAILED => 'alert-error',
];

render_breadcrumbs([['خانه', url('index.php')], ['حساب کاربری', url('account.php')], ['سفارش‌های من']]);
?>

<h1>سفارش‌های من</h1>

<?php if (empty($orders)): ?>
    <p style="margin-top:14px;">هنوز سفارشی ثبت نکردی. <a href="<?php echo url('products.php'); ?>">مشاهده محصولات</a></p>
<?php else: ?>
    <div style="display:flex; flex-direction:column; gap:16px; margin-top:16px;">
        <?php foreach ($orders as $order): ?>
            <div class="box" style="max-width:640px;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                    <strong>سفارش #<?php echo $order['id']; ?></strong>
                    <span class="alert <?php echo $status_classes[$order['status']]; ?>" style="padding:4px 12px; margin:0; display:inline-block;">
                        <?php echo Order::STATUS_LABELS[$order['status']]; ?>
                    </span>
                </div>
                <p style="margin-top:10px; font-size:13px; color:var(--plum-soft);"><?php echo format_date($order['created_at'], true); ?></p>

                <?php if ($order['status'] === Order::STATUS_PAID): ?>
                    <p style="margin-top:6px; font-size:13px;">وضعیت ارسال: <strong><?php echo Order::FULFILLMENT_LABELS[$order['fulfillment']]; ?></strong>
                        <?php if ($order['tracking_code']): ?>
                            · کد رهگیری: <span dir="ltr"><?php echo clean_input($order['tracking_code']); ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <ul style="margin-top:10px; font-size:14px; padding-right:18px;">
                    <?php foreach ($itemsByOrder[$order['id']] ?? [] as $item): ?>
                        <li><?php echo clean_input($item['product_name']); ?> × <?php echo $item['quantity']; ?></li>
                    <?php endforeach; ?>
                </ul>

                <p style="margin-top:10px; font-size:15px;">مبلغ پرداختی: <strong class="price"><?php echo format_price($order['total_amount']); ?></strong></p>
                <?php if ($order['ref_id']): ?>
                    <p style="font-size:12px; color:var(--plum-soft); margin-top:4px;">کد پیگیری پرداخت: <?php echo clean_input($order['ref_id']); ?></p>
                <?php endif; ?>
                <a href="<?php echo url('order.php?id=' . $order['id']); ?>" class="btn btn-outline" style="margin-top:12px;">جزئیات و فاکتور</a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
