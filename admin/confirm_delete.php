<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_header.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    echo '<p>محصول یافت نشد.</p>';
    require_once __DIR__ . '/_footer.php';
    exit;
}
?>

<h1>حذف محصول</h1>

<div class="box" style="max-width:480px;">
    <p>آیا از حذف محصول «<strong><?php echo clean_input($product['name']); ?></strong>» مطمئن هستید؟ این عملیات قابل بازگشت نیست.</p>
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
