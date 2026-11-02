<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

$keyModel = new ApiKey();
$new_key = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $label = trim($_POST['label'] ?? '');
        if ($label === '' || mb_strlen($label) > 100) {
            $errors[] = 'یک نام برای کلید وارد کنید (مثلاً «اپلیکیشن موبایل»)، حداکثر ۱۰۰ حرف.';
        } else {
            // خود کلید فقط همین یک بار نشون داده می‌شه؛ تو دیتابیس فقط هشش ذخیره می‌شه
            $new_key = $keyModel->generate($label)['key'];
        }
    } elseif ($action === 'revoke') {
        $keyModel->delete((int) ($_POST['id'] ?? 0));
        $_SESSION['admin_flash'] = ['type' => 'success', 'text' => 'کلید باطل شد؛ از این لحظه هیچ درخواستی با آن پذیرفته نمی‌شود.'];
        redirect('api_keys.php');
    }
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

require_once __DIR__ . '/_header.php';
?>

<h1>کلیدهای API</h1>
<p style="font-size:13px; color:var(--plum-soft); margin-bottom:12px;">
    برای ساخت، ویرایش و حذف محصول و دسته‌بندی از طریق API (هدر <span dir="ltr">X-API-Key</span>). برای هر کاربرد یک کلید جدا بساز تا اگر لو رفت، فقط همان را باطل کنی.
</p>

<?php if ($new_key): ?>
    <div class="key-reveal" role="alert">
        <strong>کلید جدید ساخته شد. همین حالا ذخیره‌اش کن؛ دوباره نشان داده نمی‌شود.</strong>
        <code><?php echo clean_input($new_key); ?></code>
    </div>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>" role="status"><?php echo clean_input($flash['text']); ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?><div><?php echo clean_input($error); ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="api_keys.php" class="admin-toolbar">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="create">
    <div class="filter-field filter-wide">
        <label for="k_label">نام کلید</label>
        <input type="text" id="k_label" name="label" maxlength="100" placeholder="مثلاً اپلیکیشن موبایل" required>
    </div>
    <div class="filter-actions"><button type="submit" class="btn">ساخت کلید جدید</button></div>
</form>

<div class="table-scroll">
<table class="admin-table" style="margin-top:14px;">
    <thead>
        <tr>
            <th scope="col">نام</th>
            <th scope="col">شروع کلید</th>
            <th scope="col">ساخته‌شده</th>
            <th scope="col">آخرین استفاده</th>
            <th scope="col"><span class="sr-only">عملیات</span></th>
        </tr>
    </thead>
    <tbody>
        <?php $keys = $keyModel->list(); ?>
        <?php if (empty($keys)): ?>
            <tr><td colspan="5" style="text-align:center;">هنوز کلیدی ساخته نشده.</td></tr>
        <?php endif; ?>
        <?php foreach ($keys as $key): ?>
            <tr>
                <td><?php echo clean_input($key['label']); ?></td>
                <td dir="ltr"><?php echo clean_input($key['key_prefix'] ?? ''); ?>…</td>
                <td><?php echo format_date($key['created_at'], true); ?></td>
                <td><?php echo $key['last_used_at'] ? format_date($key['last_used_at'], true) : 'هرگز'; ?></td>
                <td>
                    <form method="post" action="api_keys.php" class="inline-form" data-confirm="این کلید باطل شود؟ برنامه‌هایی که از آن استفاده می‌کنند از کار می‌افتند.">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="id" value="<?php echo $key['id']; ?>">
                        <button type="submit" class="btn btn-danger">باطل کردن</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
