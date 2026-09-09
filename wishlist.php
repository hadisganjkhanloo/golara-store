<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$wishlist = new Wishlist();

if (isset($_GET['remove'])) {
    $wishlist->remove((int) $_GET['remove']);
    redirect('wishlist.php');
}

require_once __DIR__ . '/includes/header.php';

$productModel = new Product();
$products = $productModel->getByIds($wishlist->getIds());
?>

<h1>علاقه‌مندی‌های من</h1>

<?php if (empty($products)): ?>
    <p style="margin-top:14px;">هنوز محصولی به علاقه‌مندی‌ها اضافه نکردی. <a href="index.php">مشاهده محصولات</a></p>
<?php else: ?>
    <p style="margin-top:8px; color:var(--plum-soft); font-size:13px;">با کلیک روی قلب هر محصول می‌تونی از این لیست حذفش کنی.</p>
    <div class="product-grid" style="margin-top:16px;">
        <?php foreach ($products as $product): render_product_card($product); endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
