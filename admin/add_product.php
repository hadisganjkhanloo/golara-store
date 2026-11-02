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

    $image_name = null;
    if (!empty($_FILES['image']['name'])) {
        $image_name = upload_product_image($_FILES['image']);
        if ($image_name === false) {
            $errors[] = 'فرمت تصویر مجاز نیست (فقط jpg، jpeg، png، webp).';
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

require_once __DIR__ . '/_header.php';

$categories = (new Category())->all('id ASC');
?>

<h1>افزودن محصول جدید</h1>

<div id="formAlerts">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <div><?php echo $error; ?></div>
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

    <label for="image">تصویر محصول</label>
    <input type="file" id="image" name="image" accept="image/*">
    <img id="imagePreview" src="" alt="پیش‌نمایش" style="display:none; width:120px; height:120px; object-fit:cover; border-radius:10px; margin-top:10px;">

    <button type="submit" class="btn" id="submitBtn">ذخیره محصول</button>
</form>

<script>
// پیش‌نمایش آنی عکس، قبل از هر آپلودی - فقط تو خود مرورگر
document.getElementById('image').addEventListener('change', function () {
    const file = this.files[0];
    const preview = document.getElementById('imagePreview');

    if (!file) {
        preview.style.display = 'none';
        return;
    }

    const reader = new FileReader();
    reader.onload = function (event) {
        preview.src = event.target.result;
        preview.style.display = 'block';
    };
    reader.readAsDataURL(file);
});

// ارسال کل فرم (همراه با فایل) با Ajax به API، به‌جای رفرش کامل صفحه
document.getElementById('addProductForm').addEventListener('submit', async function (event) {
    event.preventDefault();

    const form = this;
    const submitBtn = document.getElementById('submitBtn');
    const alertsBox = document.getElementById('formAlerts');

    submitBtn.disabled = true;
    submitBtn.textContent = 'در حال ذخیره...';

    // FormData خودش فایل‌ها رو هم از تو فرم جمع می‌کنه؛ نیازی نیست دستی اضافه کنیم
    const result = await Golara.api('POST', '/admin/products', new FormData(form));

    submitBtn.disabled = false;
    submitBtn.textContent = 'ذخیره محصول';

    alertsBox.textContent = '';
    const box = document.createElement('div');

    if (!result.ok) {
        box.className = 'alert alert-error';
        const messages = Object.values(result.errors);
        (messages.length ? messages : [result.message]).forEach(function (text) {
            const line = document.createElement('div');
            line.textContent = text;
            box.appendChild(line);
        });
        alertsBox.appendChild(box);
        return;
    }

    box.className = 'alert alert-success';
    box.textContent = result.message + ' می‌تونی محصول بعدی رو هم اضافه کنی.';
    alertsBox.appendChild(box);

    // فرم رو برای افزودن محصول بعدی خالی می‌کنیم (بدون نیاز به رفرش صفحه)
    form.reset();
    document.getElementById('imagePreview').style.display = 'none';
});
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
