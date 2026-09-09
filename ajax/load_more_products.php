<?php
// اندپوینت Ajax برای «نمایش بیشتر»: صفحه‌بندی با LIMIT و OFFSET

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
$limit = 8;

$stmt = mysqli_prepare($conn, "SELECT * FROM products ORDER BY price DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt, 'ii', $limit, $offset);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$count = 0;
while ($product = mysqli_fetch_assoc($result)) {
    render_product_card($product);
    $count++;
}

// اگه کمتر از حد معمول محصول برگشت، یعنی دیگه چیزی برای نمایش نمونده
if ($count < $limit) {
    echo '<div style="display:none" data-no-more></div>';
}
