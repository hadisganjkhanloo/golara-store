<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$productModel = new Product();
$product = $productModel->find($id);

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
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
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
    if (!empty($_FILES['image']['name'])) {
        $uploaded = upload_product_image($_FILES['image']);
        if ($uploaded === false) {
            $errors[] = 'فرمت تصویر مجاز نیست (فقط jpg، jpeg، png، webp).';
        } else {
            $image_name = $uploaded;
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

        redirect('dashboard.php');
    }
}

require_once __DIR__ . '/_header.php';

$categories = (new Category())->all('id ASC');
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
        <img src="<?php echo url('uploads/products/' . $product['image']); ?>" style="width:120px; margin-top:10px; border-radius:6px;" alt="">
    <?php endif; ?>

    <label for="image">تصویر جدید (اختیاری)</label>
    <input type="file" id="image" name="image" accept="image/*">

    <button type="submit" class="btn">ذخیره تغییرات</button>
</form>

<?php require_once __DIR__ . '/_footer.php'; ?>
