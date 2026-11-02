<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

require_customer_login();

$customerModel = new Customer();
$customer = $customerModel->find((int) $_SESSION['customer_id']);

// حساب از دیتابیس حذف شده ولی سشن هنوز زنده‌ست؛ باید قبل از چاپ هرچیزی redirect بشه
if (!$customer) {
    logout_customer();
    redirect(url('login.php'));
}

set_seo(['title' => 'حساب کاربری من', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';

$review_count = (new Review())->countForCustomer((int) $_SESSION['customer_id']);
$order_count = (new Order())->countPaidByCustomer((int) $_SESSION['customer_id']);
?>

<h1>حساب کاربری من</h1>

<div class="box" style="max-width:480px;">
    <p><strong>نام:</strong> <?php echo clean_input($customer['name']); ?></p>
    <p style="margin-top:8px;"><strong>ایمیل:</strong> <?php echo clean_input($customer['email']); ?></p>
    <p style="margin-top:8px;"><strong>عضو از:</strong> <?php echo format_date($customer['created_at']); ?></p>
    <p style="margin-top:8px;"><strong>تعداد نظرات ثبت‌شده:</strong> <?php echo $review_count; ?></p>
    <p style="margin-top:8px;"><strong>تعداد سفارش پرداخت‌شده:</strong> <?php echo $order_count; ?></p>
    <p style="margin-top:8px;"><strong>کالاهای داخل سبد خرید:</strong> <?php echo (new Cart())->count(); ?></p>
    <p style="margin-top:8px;"><strong>محصولات مورد علاقه:</strong> <?php echo (new Wishlist())->count(); ?></p>
    <p style="margin-top:10px; font-size:12px; color:var(--plum-soft);">سبد خرید و علاقه‌مندی‌هات تو حساب کاربری‌ات ذخیره می‌شن؛ از هر دستگاهی وارد بشی همین‌ها رو می‌بینی.</p>

    <div style="display:flex; gap:10px; margin-top:20px; flex-wrap:wrap;">
        <a href="<?php echo url('cart.php'); ?>" class="btn">سبد خرید</a>
        <a href="<?php echo url('orders.php'); ?>" class="btn btn-outline">سفارش‌های من</a>
        <a href="<?php echo url('wishlist.php'); ?>" class="btn btn-outline">علاقه‌مندی‌های من</a>
        <a href="<?php echo url('change_password.php'); ?>" class="btn btn-outline">تغییر رمز عبور</a>
        <!-- خروج با POST و توکن CSRF؛ اگه با لینک ساده بود، هر سایتی می‌تونست با یه عکس مخفی کاربر رو از حسابش بیرون کنه -->
        <form method="post" action="<?php echo url('logout.php'); ?>" class="inline-form">
            <?php echo csrf_field(); ?>
            <button type="submit" class="btn btn-danger">خروج از حساب</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
