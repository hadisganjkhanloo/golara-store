<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_admin_login();

$errors = [];
$name = $description = '';
$price = '';
$category_id = 0;

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

    $image_name = null;
    if (!empty($_FILES['image']['name'])) {
        $image_name = upload_product_image($_FILES['image']);
        if ($image_name === false) {
            $errors[] = 'فرمت تصویر مجاز نیست (فقط jpg، jpeg، png، webp).';
        }
    }

    if (empty($errors)) {
        $stmt = mysqli_prepare($conn, "INSERT INTO products (category_id, name, description, price, image) VALUES (?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'issis', $category_id, $name, $description, $price, $image_name);
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

// ارسال کل فرم (همراه با فایل) با Ajax، به‌جای رفرش کامل صفحه
document.getElementById('addProductForm').addEventListener('submit', function (event) {
    event.preventDefault();

    const form = this;
    const submitBtn = document.getElementById('submitBtn');
    const alertsBox = document.getElementById('formAlerts');

    // FormData خودش فایل‌ها رو هم از تو فرم جمع می‌کنه؛ نیازی نیست دستی اضافه کنیم
    const formData = new FormData(form);

    submitBtn.disabled = true;
    submitBtn.textContent = 'در حال ذخیره...';

    fetch('<?php echo url('ajax/admin_add_product.php'); ?>', {
        method: 'POST',
        body: formData
    })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'ذخیره محصول';

            if (!data.success) {
                alertsBox.innerHTML = '<div class="alert alert-error">' + data.errors.join('<br>') + '</div>';
                return;
            }

            alertsBox.innerHTML = '<div class="alert alert-success">' + data.message + ' می‌تونی محصول بعدی رو هم اضافه کنی.</div>';

            // فرم رو برای افزودن محصول بعدی خالی می‌کنیم (بدون نیاز به رفرش صفحه)
            form.reset();
            document.getElementById('imagePreview').style.display = 'none';
        })
        .catch(function () {
            submitBtn.disabled = false;
            submitBtn.textContent = 'ذخیره محصول';
            alertsBox.innerHTML = '<div class="alert alert-error">مشکلی پیش اومد، دوباره امتحان کن.</div>';
        });
});
</script>

<?php require_once __DIR__ . '/_footer.php'; ?>
