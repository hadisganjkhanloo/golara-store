<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();
require_customer_login();

$cart = new Cart();
$items = $cart->getItemsWithProducts();

// سبد خالی، یا موجودی یکی از کالاها کم شده؛ برمی‌گردیم به سبد تا مشتری اصلاحش کنه
$problem = empty($items);
foreach ($items as $item) {
    if (!$item['enough']) {
        $problem = true;
    }
}
if ($problem) {
    redirect(url('cart.php'));
}

$summary = $cart->summary($items);

// فرم: اگه قبلاً خطایی بوده مقدارهای قبلی برمی‌گردن؛ وگرنه آخرین آدرسِ مشتری پیشنهاد می‌شه
$errors = $_SESSION['checkout_errors'] ?? [];
$old = $_SESSION['checkout_old'] ?? (new Order())->lastShippingFor((int) $_SESSION['customer_id']);
unset($_SESSION['checkout_errors'], $_SESSION['checkout_old']);

$old += ['name' => $_SESSION['customer_name'] ?? '', 'phone' => '', 'city' => '', 'postal_code' => '', 'address' => ''];

set_seo(['title' => 'اطلاعات ارسال و پرداخت', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';

render_breadcrumbs([['خانه', url('index.php')], ['سبد خرید', url('cart.php')], ['اطلاعات ارسال']]);
?>

<h1>اطلاعات ارسال</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?>
            <div><?php echo clean_input($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="checkout-grid">
    <form method="post" action="<?php echo url('payment/request.php'); ?>" class="box checkout-form">
        <?php echo csrf_field(); ?>

        <label for="shipping_name">نام و نام خانوادگی گیرنده</label>
        <input type="text" id="shipping_name" name="name" value="<?php echo clean_input($old['name']); ?>" maxlength="100" required autocomplete="name">

        <label for="shipping_phone">شماره موبایل گیرنده</label>
        <input type="tel" id="shipping_phone" name="phone" value="<?php echo clean_input($old['phone']); ?>" placeholder="09123456789" maxlength="11" required autocomplete="tel" inputmode="numeric" dir="ltr">

        <div class="form-row">
            <div>
                <label for="shipping_city">شهر</label>
                <input type="text" id="shipping_city" name="city" value="<?php echo clean_input($old['city']); ?>" maxlength="80" required autocomplete="address-level2">
            </div>
            <div>
                <label for="shipping_postal">کدپستی (۱۰ رقم)</label>
                <input type="text" id="shipping_postal" name="postal_code" value="<?php echo clean_input($old['postal_code']); ?>" maxlength="10" required autocomplete="postal-code" inputmode="numeric" dir="ltr">
            </div>
        </div>

        <label for="shipping_address">آدرس کامل (خیابان، پلاک، واحد)</label>
        <textarea id="shipping_address" name="address" rows="3" maxlength="500" required autocomplete="street-address"><?php echo clean_input($old['address']); ?></textarea>

        <button type="submit" class="btn">پرداخت <?php echo format_price($summary['total']); ?></button>
        <p class="form-hint">با زدن این دکمه، موجودی کالاها برای شما ۳۰ دقیقه رزرو می‌شود و به درگاه پرداخت منتقل می‌شوید.</p>
    </form>

    <aside class="box checkout-summary" aria-label="خلاصه سفارش">
        <h2>خلاصه سفارش</h2>
        <ul class="checkout-items">
            <?php foreach ($items as $item): ?>
                <li>
                    <span><?php echo clean_input($item['product']['name']); ?> × <?php echo $item['qty']; ?></span>
                    <span><?php echo format_price($item['line_total']); ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php render_price_summary($summary); ?>
        <p class="form-hint"><a href="<?php echo url('cart.php'); ?>">ویرایش سبد خرید</a></p>
    </aside>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
