<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_header.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$product = (new Product())->find($id);

if (!$product) {
    echo '<p>محصول یافت نشد (یا قبلاً حذف شده). <a href="dashboard.php">بازگشت</a></p>';
    require_once __DIR__ . '/_footer.php';
    exit;
}
?>

<h1>حذف محصول</h1>

<div class="box" style="max-width:520px;">
    <p>محصول «<strong><?php echo clean_input($product['name']); ?></strong>» از فروشگاه حذف شود؟</p>
    <p style="margin-top:10px; font-size:13px; color:var(--plum-soft);">
        محصول فقط از فروشگاه، سبد خرید و علاقه‌مندی‌ها پنهان می‌شود. سفارش‌های قبلی، آمار فروش و نظرات مشتریان دست‌نخورده می‌مانند،
        و هر وقت خواستی از فیلتر «حذف‌شده» در لیست محصولات می‌توانی بازگردانیش.
    </p>
    <div style="display:flex; gap:10px; margin-top:18px;">
        <form method="post" action="delete_product.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
            <button type="submit" class="btn btn-danger">بله، حذف کن</button>
        </form>
        <a href="dashboard.php" class="btn btn-outline">انصراف</a>
    </div>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
