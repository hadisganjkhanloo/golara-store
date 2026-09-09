<?php
// API دسته‌بندی‌ها (فقط خواندن)
// GET /api/v1/categories.php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    api_error('این اندپوینت فقط GET را پشتیبانی می‌کند.', 405);
}

$result = mysqli_query($conn, "SELECT * FROM categories");
$categories = [];
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

api_response($categories);
