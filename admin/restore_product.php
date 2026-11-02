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

$_SESSION['admin_flash'] = ($id > 0 && (new Product())->restore($id))
    ? ['type' => 'success', 'text' => 'محصول دوباره در فروشگاه نمایش داده می‌شود.']
    : ['type' => 'error', 'text' => 'محصول پیدا نشد یا حذف‌شده نبود.'];

redirect(url('admin/dashboard.php'));
