<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// پیام یک‌بارمصرف (مثلاً «موجودی عوض شد») که صفحه پرداخت اینجا می‌ذاره
$notice = $_SESSION['cart_notice'] ?? '';
unset($_SESSION['cart_notice']);

require_once __DIR__ . '/includes/header.php';

$cart = new Cart();
$cart_items = $cart->getItemsWithProducts();
$total = $cart->getTotal();
?>

<h1>سبد خرید</h1>

<?php if ($notice !== ''): ?>
    <div class="alert alert-error"><?php echo clean_input($notice); ?></div>
<?php endif; ?>

<?php if (empty($_SESSION['customer_id']) && !empty($cart_items)): ?>
    <p style="margin-bottom:12px; font-size:13px; color:var(--plum-soft);">
        تا وارد نشدی، سبد فقط تو همین مرورگر نگه داشته می‌شه. بعد از ورود، به حساب کاربری‌ات منتقل می‌شه.
    </p>
<?php endif; ?>

<!-- افزودن/کم/زیاد/حذف با Ajax انجام می‌شه و همین بخش دوباره ساخته می‌شه (app.js) -->
<div id="cartContent">
    <?php render_cart_table($cart_items, $total); ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
