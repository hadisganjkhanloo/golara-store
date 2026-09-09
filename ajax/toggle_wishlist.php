<?php
// اندپوینت Ajax: علاقه‌مندی رو toggle می‌کنه (اگه بود حذف، نبود اضافه) و JSON برمی‌گردونه

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

verify_csrf_token();

$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$wishlist = new Wishlist();
$is_active = $product_id > 0 ? $wishlist->toggle($product_id) : false;

header('Content-Type: application/json');
echo json_encode([
    'count' => $wishlist->count(),
    'active' => $is_active,
]);
