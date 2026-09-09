<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

verify_csrf_token();

if (empty($_SESSION['customer_id'])) {
    echo json_encode(['success' => false, 'message' => 'برای ثبت نظر باید وارد حساب کاربری شوید.']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int) $_POST['product_id'] : 0;
$rating = isset($_POST['rating']) ? (int) $_POST['rating'] : 0;
$comment = clean_input($_POST['comment'] ?? '');
$customer_id = (int) $_SESSION['customer_id'];

if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'لطفاً یک امتیاز بین ۱ تا ۵ ستاره انتخاب کنید.']);
    exit;
}

$reviewModel = new Review();

if ($reviewModel->hasReviewed($product_id, $customer_id)) {
    echo json_encode(['success' => false, 'message' => 'شما قبلاً برای این محصول نظر ثبت کرده‌اید.']);
    exit;
}

$created = $reviewModel->create($product_id, $customer_id, $rating, $comment);

if ($created === false) {
    // قید UNIQUE تو دیتابیس جلوی نظر تکراری رو گرفته (مثلاً دو تب هم‌زمان)
    echo json_encode(['success' => false, 'message' => 'شما قبلاً برای این محصول نظر ثبت کرده‌اید.']);
    exit;
}

// اطلاعات نظر تازه‌ثبت‌شده رو برای نمایش برمی‌گردونیم
$new_review = [
    'customer_name' => $_SESSION['customer_name'],
    'rating' => $rating,
    'comment' => $comment,
    'created_at' => date('Y-m-d H:i'),
];

ob_start();
render_review_item($new_review);
$review_html = ob_get_clean();

$stats = $reviewModel->getAverageForProduct($product_id);

echo json_encode([
    'success' => true,
    'html' => $review_html,
    'average' => $stats['average'],
    'count' => $stats['count'],
]);
