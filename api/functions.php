<?php
// توابع کمکی مشترک برای همه اندپوینت‌های API

function api_response($data, $status_code = 200)
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function api_error($message, $status_code = 400)
{
    http_response_code($status_code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

// برای متدهایی که تغییر ایجاد می‌کنن (POST/PUT/DELETE) به یه کلید معتبر نیاز داریم
function require_api_key($conn)
{
    $key = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');

    if ($key === '') {
        api_error('کلید API ارسال نشده. هدر X-API-Key رو اضافه کن.', 401);
    }

    $stmt = mysqli_prepare($conn, "SELECT id FROM api_keys WHERE api_key = ?");
    mysqli_stmt_bind_param($stmt, 's', $key);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);

    if (mysqli_stmt_num_rows($stmt) === 0) {
        api_error('کلید API نامعتبره.', 401);
    }
}

// بدنه خام JSON درخواست رو به آرایه PHP تبدیل می‌کنه (برای POST/PUT در API‌ها معمولاً JSON می‌فرستن، نه form-data)
function get_json_input()
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
