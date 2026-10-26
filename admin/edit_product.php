<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_admin_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($result);

if (!$product) {
    require_once __DIR__ . '/_header.php';
    echo '<p>محصول یافت نشد.</p>';
    require_once __DIR__ . '/_footer.php';
    exit;
}

$errors = [];
$name = $product['name'];
$description = $product['description'];
$price = $product['price'];
$category_id = (int) $product['category_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $name = clean_input($_POST['name'] ?? '');
    $description = clean_input($_POST['description'] ?? '');
    $price = $_POST['price'] ?? '';
    $category_id = (int) ($_POST['category_id'] ?? 0);

    if ($name === '') {
        $errors[] = 'نام محصول را وارد کنید.';
    }
    if (!preg_match('/^[0-9]+$/', $price)) {
        $errors[] = 'قیمت باید یک عدد صحیح باشد.';
    }

    $image_name = $product['image'];
    if (!empty($_FILES['image']['name'])) {
        $uploaded = upload_product_image($_FILES['image']);
        if ($uploaded === false) {
            $errors[] = 'فرمت تصویر مجاز نیست (فقط jpg، jpeg، png، webp).';
        } else {
            $image_name = $uploaded;
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, image = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'issisi', $category_id, $name, $description, $price, $image_name, $id);
        mysqli_stmt_execute($stmt);

        redirect('dashboard.php');
    }
}

require_once __DIR__ . '/_header.php';

$categories_result = mysqli_query($conn, "SELECT * FROM categories");
$categories = [];
while ($cat = mysqli_fetch_assoc($categories_result)) {
    $categories[] = $cat;
}
?>

<h1>ویرایش محصول</h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $error): ?>
            <div><?php echo $error; ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="edit_product.php?id=<?php echo $id; ?>" enctype="multipart/form-data" class="box">
    <?php echo csrf_field(); ?>

    <label for="name">نام محصول</label>
    <input type="text" id="name" name="name" value="<?php echo $name; ?>">

    <label for="category_id">دسته‌بندی</label>
    <select id="category_id" name="category_id">
        <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo $category_id === (int)$cat['id'] ? 'selected' : ''; ?>>
                <?php echo clean_input($cat['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="price">قیمت (تومان)</label>
    <input type="text" id="price" name="price" value="<?php echo $price; ?>">

    <label for="description">توضیحات</label>
    <textarea id="description" name="description" rows="4"><?php echo $description; ?></textarea>

    <?php if ($product['image']): ?>
        <img src="<?php echo url('uploads/products/' . $product['image']); ?>" style="width:120px; margin-top:10px; border-radius:6px;">
    <?php endif; ?>

    <label for="image">تصویر جدید (اختیاری)</label>
    <input type="file" id="image" name="image" accept="image/*">

    <button type="submit" class="btn">ذخیره تغییرات</button>
</form>

<?php require_once __DIR__ . '/_footer.php'; ?>
