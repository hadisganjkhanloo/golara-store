<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

$orderModel = new Order();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$order = $orderModel->findWithCustomer($id);

if (!$order) {
    http_response_code(404);
    require_once __DIR__ . '/_header.php';
    echo '<p>سفارش پیدا نشد. <a href="orders.php">بازگشت به سفارش‌ها</a></p>';
    require_once __DIR__ . '/_footer.php';
    exit;
}

// تغییر وضعیت ارسال و کد رهگیری (فقط سفارش پرداخت‌شده)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $ok = $orderModel->updateFulfillment($id, (string) ($_POST['fulfillment'] ?? ''), (string) ($_POST['tracking_code'] ?? ''));
    $_SESSION['admin_flash'] = $ok
        ? ['type' => 'success', 'text' => 'وضعیت سفارش به‌روز شد.']
        : ['type' => 'error', 'text' => 'وضعیت ارسال فقط برای سفارش پرداخت‌شده قابل تغییر است.'];

    redirect('order.php?id=' . $id);
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$items = $orderModel->getItems($id);

require_once __DIR__ . '/_header.php';
?>

<h1>سفارش #<?php echo $order['id']; ?></h1>
<p><a href="orders.php">‹ بازگشت به سفارش‌ها</a></p>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>" role="status"><?php echo clean_input($flash['text']); ?></div>
<?php endif; ?>

<div class="checkout-grid" style="margin-top:14px;">
    <div class="box" style="max-width:none;">
        <h2 style="font-size:17px; margin-bottom:8px;">مشخصات</h2>
        <p>تاریخ ثبت: <?php echo format_date($order['created_at'], true); ?></p>
        <p>مشتری: <?php echo clean_input($order['customer_name']); ?> (<span dir="ltr"><?php echo clean_input($order['customer_email']); ?></span>)</p>
        <p>وضعیت پرداخت: <strong><?php echo Order::STATUS_LABELS[$order['status']]; ?></strong>
            <?php if ($order['ref_id']): ?> · کد پیگیری درگاه: <span dir="ltr"><?php echo clean_input($order['ref_id']); ?></span><?php endif; ?></p>

        <h2 style="font-size:17px; margin:16px 0 8px;">آدرس تحویل</h2>
        <?php if ($order['shipping_address']): ?>
            <p><?php echo clean_input($order['shipping_name']); ?> — <span dir="ltr"><?php echo clean_input($order['shipping_phone']); ?></span></p>
            <p><?php echo clean_input($order['shipping_city']); ?>، <?php echo nl2br(clean_input($order['shipping_address'])); ?></p>
            <p>کدپستی: <span dir="ltr"><?php echo clean_input($order['shipping_postal_code']); ?></span></p>
        <?php else: ?>
            <p style="color:var(--plum-soft);">این سفارش قبل از اضافه‌شدن آدرس تحویل ثبت شده است.</p>
        <?php endif; ?>
    </div>

    <form method="post" action="order.php" class="box" style="max-width:none;">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="id" value="<?php echo $order['id']; ?>">
        <h2 style="font-size:17px;">وضعیت ارسال</h2>

        <?php if ($order['status'] !== Order::STATUS_PAID): ?>
            <p style="margin-top:8px; font-size:13px; color:var(--plum-soft);">وضعیت ارسال بعد از پرداخت موفق قابل تغییر است.</p>
        <?php else: ?>
            <label for="fulfillment">وضعیت</label>
            <select id="fulfillment" name="fulfillment">
                <?php foreach (Order::FULFILLMENT_LABELS as $value => $label): ?>
                    <option value="<?php echo $value; ?>" <?php echo $order['fulfillment'] === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>

            <label for="tracking_code">کد رهگیری مرسوله (اختیاری)</label>
            <input type="text" id="tracking_code" name="tracking_code" value="<?php echo clean_input($order['tracking_code'] ?? ''); ?>" maxlength="60" dir="ltr">

            <button type="submit" class="btn">ذخیره</button>
        <?php endif; ?>
    </form>
</div>

<div class="table-scroll" style="margin-top:20px;">
<table class="admin-table">
    <thead><tr><th scope="col">کالا</th><th scope="col">قیمت واحد</th><th scope="col">تعداد</th><th scope="col">جمع</th></tr></thead>
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

<div style="max-width:380px; margin-top:16px;">
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
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
