<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('admin/dashboard.php'));
}

verify_csrf_token();

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

// حذف نرم: ردیف و عکس محصول می‌مونن (سفارش‌های قبلی و آمار فروش بهشون وابسته‌ان)
if ($id > 0 && (new Product())->softDelete($id)) {
    $_SESSION['admin_flash'] = ['type' => 'success', 'text' => 'محصول از فروشگاه حذف شد. (از فیلتر «حذف‌شده» می‌شود بازگرداند.)'];
} else {
    $_SESSION['admin_flash'] = ['type' => 'error', 'text' => 'محصول پیدا نشد.'];
}

redirect(url('admin/dashboard.php'));
