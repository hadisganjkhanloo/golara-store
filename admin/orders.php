<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_header.php';

const ADMIN_ORDERS_PER_PAGE = 10;

$status = isset(Order::STATUS_LABELS[$_GET['status'] ?? '']) ? $_GET['status'] : '';
$fulfillment = isset(Order::FULFILLMENT_LABELS[$_GET['fulfillment'] ?? '']) ? $_GET['fulfillment'] : '';
$q = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

$orderModel = new Order();
$stats = $orderModel->stats();
$result = $orderModel->paginateForAdmin(['status' => $status, 'fulfillment' => $fulfillment, 'q' => $q], $page, ADMIN_ORDERS_PER_PAGE);

$active_query = ['status' => $status, 'fulfillment' => $fulfillment, 'q' => $q];
$has_filter = $status !== '' || $fulfillment !== '' || $q !== '';
$low_stock = (new Product())->countLowStock();
?>

<h1>سفارش‌ها</h1>

<div class="stats-row">
    <div class="stat-card"><strong><?php echo number_format($stats['paid_orders']); ?></strong><span>سفارش پرداخت‌شده</span></div>
    <div class="stat-card"><strong><?php echo number_format($stats['revenue']); ?></strong><span>جمع فروش (تومان)</span></div>
    <div class="stat-card"><strong><?php echo number_format($stats['to_ship']); ?></strong><span>در انتظار ارسال</span></div>
    <div class="stat-card"><strong><?php echo number_format($stats['pending_orders']); ?></strong><span>در انتظار پرداخت</span></div>
    <a class="stat-card" href="dashboard.php?stock=low" style="text-decoration:none;"><strong><?php echo number_format($low_stock); ?></strong><span>محصول کم‌موجود یا ناموجود</span></a>
</div>

<form method="get" action="orders.php" class="admin-toolbar">
    <div class="filter-field filter-wide">
        <label for="o_q">جستجو (شماره سفارش، نام یا ایمیل)</label>
        <input type="text" id="o_q" name="q" value="<?php echo clean_input($q); ?>">
    </div>
    <div class="filter-field">
        <label for="o_status">وضعیت پرداخت</label>
        <select id="o_status" name="status">
            <option value="">همه</option>
            <?php foreach (Order::STATUS_LABELS as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $status === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="o_ful">وضعیت ارسال</label>
        <select id="o_ful" name="fulfillment">
            <option value="">همه</option>
            <?php foreach (Order::FULFILLMENT_LABELS as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $fulfillment === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn">اعمال</button>
        <?php if ($has_filter): ?><a href="orders.php" class="btn btn-outline">حذف فیلترها</a><?php endif; ?>
    </div>
</form>

<p style="font-size:13px; color:var(--plum-soft);"><?php echo number_format($result['total']); ?> سفارش</p>

<div class="table-scroll">
<table class="admin-table" style="margin-top:10px;">
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">تاریخ</th>
            <th scope="col">مشتری</th>
            <th scope="col">مبلغ</th>
            <th scope="col">پرداخت</th>
            <th scope="col">ارسال</th>
            <th scope="col"><span class="sr-only">عملیات</span></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($result['items'])): ?>
            <tr><td colspan="7" style="text-align:center;">سفارشی با این شرایط پیدا نشد.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['items'] as $order): ?>
            <tr>
                <td><?php echo $order['id']; ?></td>
                <td><?php echo format_date($order['created_at'], true); ?></td>
                <td><?php echo clean_input($order['shipping_name'] ?: $order['customer_name']); ?><br><small dir="ltr"><?php echo clean_input($order['customer_email']); ?></small></td>
                <td><?php echo format_price($order['total_amount']); ?></td>
                <td><span class="badge <?php echo $order['status'] === Order::STATUS_PAID ? 'badge-ok' : ($order['status'] === Order::STATUS_PENDING ? 'badge-warn' : 'badge-off'); ?>"><?php echo Order::STATUS_LABELS[$order['status']]; ?></span></td>
                <td><?php echo $order['status'] === Order::STATUS_PAID ? Order::FULFILLMENT_LABELS[$order['fulfillment']] : '—'; ?></td>
                <td><a href="order.php?id=<?php echo $order['id']; ?>" class="btn">جزئیات</a></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php render_pagination($result['page'], $result['pages'], url('admin/orders.php'), $active_query); ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
