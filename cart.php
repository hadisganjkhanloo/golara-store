<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cart = new Cart();

// افزودن محصول به سبد
if (isset($_POST['add_to_cart'])) {
    $cart->add((int) $_POST['product_id']);
    redirect('cart.php');
}

// حذف محصول از سبد
if (isset($_GET['remove'])) {
    $cart->remove((int) $_GET['remove']);
    redirect('cart.php');
}

require_once __DIR__ . '/includes/header.php';

$cart_items = $cart->getItemsWithProducts();
$total = $cart->getTotal();
?>

<h1>سبد خرید</h1>

<?php if (empty($cart_items)): ?>
    <p>سبد خرید شما خالی است. <a href="index.php">مشاهده محصولات</a></p>
<?php else: ?>
    <table>
        <thead>
            <tr>
                <th>محصول</th>
                <th>قیمت واحد</th>
                <th>تعداد</th>
                <th>جمع</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($cart_items as $item): ?>
                <tr>
                    <td><?php echo clean_input($item['product']['name']); ?></td>
                    <td><?php echo format_price($item['product']['price']); ?></td>
                    <td><?php echo $item['qty']; ?></td>
                    <td><?php echo format_price($item['line_total']); ?></td>
                    <td><a href="cart.php?remove=<?php echo $item['product']['id']; ?>" style="color:#dc2626;">حذف</a></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:16px; font-size:18px;">جمع کل: <strong class="price"><?php echo format_price($total); ?></strong></p>

    <?php if (!empty($_SESSION['customer_id'])): ?>
        <a href="payment/request.php" class="btn" style="margin-top:16px;">تسویه حساب و پرداخت</a>
    <?php else: ?>
        <p style="margin-top:16px; font-size:14px;">برای تسویه حساب، ابتدا <a href="login.php" style="color:var(--pink-dark);">وارد حساب کاربری</a> شو.</p>
    <?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
