<?php
// اندپوینت Ajax: نه هدر داره نه فوتر، فقط منطق سبد خرید + یه عدد ساده در جواب

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

verify_csrf_token();

$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$cart = new Cart();

if ($product_id > 0) {
    $cart->add($product_id);
}

// همون کاری که counter.php می‌کرد: فقط یه عدد ساده برمی‌گردونیم
echo $cart->count();
