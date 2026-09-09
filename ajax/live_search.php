<?php
// اندپوینت Ajax: به‌جای JSON، این‌بار مستقیم یه تکه HTML آماده برمی‌گردونیم
// (چون جواب چیزیه که باید مستقیم تو صفحه نشون داده بشه، نه یه عدد یا داده خام)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($query === '') {
    exit;
}

$search = search_products($conn, $query);

if ($search['error']) {
    echo '<p>' . $search['error'] . '</p>';
    exit;
}

if (empty($search['products'])) {
    echo '<p>نتیجه‌ای برای «' . clean_input($query) . '» یافت نشد.</p>';
    exit;
}

foreach ($search['products'] as $product) {
    render_product_card($product);
}
