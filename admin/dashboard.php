<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_header.php';

$result = mysqli_query($conn, "
    SELECT products.*, categories.name AS category_name
    FROM products
    LEFT JOIN categories ON products.category_id = categories.id
    ORDER BY products.created_at DESC
");
?>

<h1>مدیریت محصولات</h1>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">محصول با موفقیت حذف شد.</div>
<?php endif; ?>

<table style="margin-top:16px;">
    <thead>
        <tr>
            <th>#</th>
            <th>نام محصول</th>
            <th>دسته‌بندی</th>
            <th>قیمت</th>
            <th>عملیات</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($product = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td><?php echo $product['id']; ?></td>
                <td><?php echo clean_input($product['name']); ?></td>
                <td><?php echo clean_input($product['category_name'] ?? '-'); ?></td>
                <td><?php echo format_price($product['price']); ?></td>
                <td>
                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn">ویرایش</a>
                    <a href="confirm_delete.php?id=<?php echo $product['id']; ?>" class="btn btn-danger">حذف</a>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php require_once __DIR__ . '/_footer.php'; ?>
