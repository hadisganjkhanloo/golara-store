<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$productModel = new Product();
// مدیر محصولِ حذف‌شده رو هم می‌تونه ویرایش کنه (برای بازگردانی)
$product = $productModel->find($id, true);

if (!$product) {
    require_once __DIR__ . '/_header.php';
    echo '<p>محصول یافت نشد.</p>';
    require_once __DIR__ . '/_footer.php';
    exit;
}

$errors = [];
$name = $product['name'];
$description = $product['description'];
$price = (string) $product['price'];
$stock = (string) $product['stock'];
$category_id = (int) $product['category_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    // مقدارها خام ذخیره می‌شن؛ موقع نمایش با clean_input امن می‌شن
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

    $image_name = $product['image'];
    $replaced_image = null;
    if (!empty($_FILES['image']['name'])) {
        $upload = store_product_image($_FILES['image']);
        if (!$upload['ok']) {
            $errors[] = $upload['error'];
        } else {
            $replaced_image = $product['image'];
            $image_name = $upload['name'];
        }
    }

    if (empty($errors)) {
        $productModel->update($id, [
            'category_id' => $category_id > 0 ? $category_id : null,
            'name' => $name,
            'description' => $description,
            'price' => (int) $price,
            'stock' => (int) $stock,
            'image' => $image_name,
        ]);

        // عکس قبلی دیگه استفاده نمی‌شه؛ فایلش (و thumbnailهاش) پاک می‌شه
        delete_product_image($replaced_image);

        redirect('dashboard.php');
    }
}

require_once __DIR__ . '/_header.php';

$categories = (new Category())->all('id ASC');
?>

<h1>ویرایش محصول <?php if ($product['deleted_at']): ?><span class="badge badge-off">حذف‌شده</span><?php endif; ?></h1>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?>
            <div><?php echo clean_input($error); ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="edit_product.php?id=<?php echo $id; ?>" enctype="multipart/form-data" class="box">
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

    <?php if ($product['image']): ?>
        <img src="<?php echo product_image($product['image'], 240)['url']; ?>" class="image-preview" alt="عکس فعلی محصول" width="240" height="160">
    <?php endif; ?>

    <label for="image">تصویر جدید (اختیاری؛ JPEG، PNG یا WebP، حداکثر ۲ مگابایت)</label>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp">

    <button type="submit" class="btn">ذخیره تغییرات</button>
</form>

<?php require_once __DIR__ . '/_footer.php'; ?>
