<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_customer_login();

require_once __DIR__ . '/includes/header.php';

$customerModel = new Customer();
$customer = $customerModel->find((int) $_SESSION['customer_id']);

$review_count = (new Review())->countForCustomer((int) $_SESSION['customer_id']);
$order_count = (new Order())->countPaidByCustomer((int) $_SESSION['customer_id']);
?>

<h1>حساب کاربری من</h1>

<div class="box" style="max-width:480px;">
    <p><strong>نام:</strong> <?php echo clean_input($customer['name']); ?></p>
    <p style="margin-top:8px;"><strong>ایمیل:</strong> <?php echo clean_input($customer['email']); ?></p>
    <p style="margin-top:8px;"><strong>عضو از:</strong> <?php echo $customer['created_at']; ?></p>
    <p style="margin-top:8px;"><strong>تعداد نظرات ثبت‌شده:</strong> <?php echo $review_count; ?></p>
    <p style="margin-top:8px;"><strong>تعداد سفارش پرداخت‌شده:</strong> <?php echo $order_count; ?></p>

    <div style="display:flex; gap:10px; margin-top:20px; flex-wrap:wrap;">
        <a href="orders.php" class="btn">سفارش‌های من</a>
        <a href="wishlist.php" class="btn btn-outline">علاقه‌مندی‌های من</a>
        <a href="logout.php" class="btn btn-danger">خروج از حساب</a>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
