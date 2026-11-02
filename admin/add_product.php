<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

$errors = [];
$name = $description = '';
$price = '';
$stock = '0';
$category_id = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    // مقدارها خام ذخیره می‌شن؛ موقع نمایش با clean_input امن می‌شن (نه موقع ذخیره، تا دوبار escape نشن)
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = normalize_digits(trim($_POST['price'] ?? ''));
    $stock = normalize_digits(trim($_POST['stock'] ?? ''));
    $category_id = (int) ($_POST['category_id'] ?? 0);

    if ($name === '') {
        $errors[] = 'نام محصول را وارد کنید.';
    }
    if (!preg_match('/^[0-9]+$/', $price)) {
        $errors[] = 'قیمت باید یک عدد صحیح باشد.';
    }
    if (!preg_match('/^[0-9]+$/', $stock)) {
        $errors[] = 'موجودی باید یک عدد صحیح (۰ یا بیشتر) باشد.';
    }

    $image_name = null;
    if (!empty($_FILES['image']['name'])) {
        $upload = store_product_image($_FILES['image']);
        if (!$upload['ok']) {
            $errors[] = $upload['error'];
        } else {
            $image_name = $upload['name'];
        }
    }

    if (empty($errors)) {
        (new Product())->create([
            'category_id' => $category_id > 0 ? $category_id : null,
            'name' => $name,
            'description' => $description,
            'price' => (int) $price,
            'stock' => (int) $stock,
            'image' => $image_name,
        ]);

        redirect('dashboard.php');
    }
}

$page_scripts = ['assets/js/admin-product.js'];
require_once __DIR__ . '/_header.php';

$categories = (new Category())->all('id ASC');
?>

<h1>افزودن محصول جدید</h1>

<div id="formAlerts" role="status">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
            <?php foreach ($errors as $error): ?>
                <div><?php echo clean_input($error); ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<form method="post" action="add_product.php" enctype="multipart/form-data" class="box" id="addProductForm">
    <?php echo csrf_field(); ?>

    <label for="name">نام محصول</label>
    <input type="text" id="name" name="name" value="<?php echo clean_input($name); ?>">

    <label for="category_id">دسته‌بندی</label>
    <select id="category_id" name="category_id">
        <option value="0">بدون دسته‌بندی</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?php echo $cat['id']; ?>" <?php echo $category_id === (int)$cat['id'] ? 'selected' : ''; ?>>
                <?php echo clean_input($cat['name']); ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label for="price">قیمت (تومان)</label>
    <input type="text" inputmode="numeric" id="price" name="price" value="<?php echo clean_input($price); ?>">

    <label for="stock">موجودی انبار (تعداد)</label>
    <input type="number" id="stock" name="stock" min="0" step="1" value="<?php echo clean_input($stock); ?>">

    <label for="description">توضیحات</label>
    <textarea id="description" name="description" rows="4"><?php echo clean_input($description); ?></textarea>

    <label for="image">تصویر محصول (JPEG، PNG یا WebP؛ حداکثر ۲ مگابایت)</label>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">
    <img id="imagePreview" src="" alt="پیش‌نمایش" class="image-preview" hidden>

    <button type="submit" class="btn" id="submitBtn">ذخیره محصول</button>
</form>

<?php require_once __DIR__ . '/_footer.php'; ?>
