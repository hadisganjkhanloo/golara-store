<?php
// API محصولات
// GET    /api/v1/products.php              -> لیست محصولات (با limit/offset/category_id)
// GET    /api/v1/products.php?id=5         -> جزئیات یک محصول
// POST   /api/v1/products.php              -> ساخت محصول جدید (نیاز به API Key)
// PUT    /api/v1/products.php?id=5         -> ویرایش محصول (نیاز به API Key)
// DELETE /api/v1/products.php?id=5         -> حذف محصول (نیاز به API Key)

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../functions.php';

$method = $_SERVER['REQUEST_METHOD'];
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;

// ---------- GET ----------
if ($method === 'GET') {
    if ($id) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if (!$product) {
            api_error('محصولی با این id پیدا نشد.', 404);
        }

        api_response($product);
    }

    $limit = isset($_GET['limit']) ? min((int) $_GET['limit'], 50) : 10;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
    $category_id = isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0;

    if ($category_id > 0) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE category_id = ? ORDER BY id DESC LIMIT ? OFFSET ?");
        mysqli_stmt_bind_param($stmt, 'iii', $category_id, $limit, $offset);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM products ORDER BY id DESC LIMIT ? OFFSET ?");
        mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }

    api_response($products);
}

// ---------- POST (ساخت محصول جدید) ----------
if ($method === 'POST') {
    require_api_key($conn);

    $input = get_json_input();

    $name = clean_input($input['name'] ?? '');
    $price = $input['price'] ?? '';
    $category_id = (int) ($input['category_id'] ?? 0);
    $description = clean_input($input['description'] ?? '');

    if ($name === '') {
        api_error('فیلد name الزامی است.', 400);
    }
    if (!preg_match('/^[0-9]+$/', (string) $price)) {
        api_error('فیلد price باید عدد صحیح باشد.', 400);
    }

    $stmt = mysqli_prepare($conn, "INSERT INTO products (category_id, name, description, price) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, 'issi', $category_id, $name, $description, $price);
    mysqli_stmt_execute($stmt);

    api_response(['id' => mysqli_insert_id($conn), 'message' => 'محصول ایجاد شد.'], 201);
}

// ---------- PUT (ویرایش محصول) ----------
if ($method === 'PUT') {
    require_api_key($conn);

    if (!$id) {
        api_error('پارامتر id در آدرس الزامی است.', 400);
    }

    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$product) {
        api_error('محصولی با این id پیدا نشد.', 404);
    }

    $input = get_json_input();

    $name = isset($input['name']) ? clean_input($input['name']) : $product['name'];
    $price = $input['price'] ?? $product['price'];
    $category_id = isset($input['category_id']) ? (int) $input['category_id'] : $product['category_id'];
    $description = isset($input['description']) ? clean_input($input['description']) : $product['description'];

    $stmt = mysqli_prepare($conn, "UPDATE products SET name = ?, price = ?, category_id = ?, description = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'siisi', $name, $price, $category_id, $description, $id);
    mysqli_stmt_execute($stmt);

    api_response(['message' => 'محصول به‌روزرسانی شد.']);
}

// ---------- DELETE ----------
if ($method === 'DELETE') {
    require_api_key($conn);

    if (!$id) {
        api_error('پارامتر id در آدرس الزامی است.', 400);
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);

    if (mysqli_stmt_affected_rows($stmt) === 0) {
        api_error('محصولی با این id پیدا نشد.', 404);
    }

    api_response(['message' => 'محصول حذف شد.']);
}

api_error('این متد پشتیبانی نمی‌شود.', 405);
