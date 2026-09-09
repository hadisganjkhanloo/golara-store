<?php
// اندپوینت Ajax برای افزودن محصول از پنل مدیریت - با آپلود عکس از طریق FormData

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

verify_csrf_token();

if (empty($_SESSION['admin_id'])) {
    echo json_encode(['success' => false, 'errors' => ['دسترسی غیرمجاز. لطفاً دوباره وارد شوید.']]);
    exit;
}

$errors = [];
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

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$stmt = mysqli_prepare($conn, "INSERT INTO products (category_id, name, description, price, image) VALUES (?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'issis', $category_id, $name, $description, $price, $image_name);
mysqli_stmt_execute($stmt);

echo json_encode([
    'success' => true,
    'message' => 'محصول «' . $name . '» با موفقیت اضافه شد.',
]);
