<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

$categoryModel = new Category();

// افزودن / تغییر نام / حذف؛ بعد از هر عملیات redirect می‌کنیم تا رفرش صفحه دوباره همون POST رو نفرسته
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $flash = ['type' => 'error', 'text' => 'عملیات نامعتبر است.'];

    if ($action === 'create' || $action === 'rename') {
        if ($name === '' || mb_strlen($name) > 100) {
            $flash['text'] = 'نام دسته‌بندی را وارد کنید (حداکثر ۱۰۰ حرف).';
        } elseif ($categoryModel->nameExists($name, $action === 'rename' ? $id : 0)) {
            $flash['text'] = 'دسته‌بندی دیگری با همین نام وجود دارد.';
        } elseif ($action === 'create') {
            $categoryModel->create($name);
            $flash = ['type' => 'success', 'text' => 'دسته‌بندی «' . $name . '» اضافه شد.'];
        } elseif ($categoryModel->find($id)) {
            $categoryModel->rename($id, $name);
            $flash = ['type' => 'success', 'text' => 'نام دسته‌بندی تغییر کرد.'];
        }
    } elseif ($action === 'delete' && ($category = $categoryModel->find($id))) {
        $categoryModel->delete($id);
        $flash = ['type' => 'success', 'text' => 'دسته‌بندی «' . $category['name'] . '» حذف شد. محصولاتش بدون دسته‌بندی ماندند.'];
    }

    $_SESSION['admin_flash'] = $flash;
    redirect('categories.php');
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

require_once __DIR__ . '/_header.php';

$categories = $categoryModel->allWithProductCount();
?>

<h1>مدیریت دسته‌بندی‌ها</h1>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>"><?php echo clean_input($flash['text']); ?></div>
<?php endif; ?>

<form method="post" action="categories.php" class="admin-toolbar">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="create">
    <div class="filter-field filter-wide">
        <label for="new_name">دسته‌بندی جدید</label>
        <input type="text" id="new_name" name="name" maxlength="100" placeholder="مثلا: مراقبت مو" required>
    </div>
    <div class="filter-actions"><button type="submit" class="btn">افزودن</button></div>
</form>

<div class="table-scroll">
<table class="admin-table" style="margin-top:14px;">
    <thead>
        <tr>
            <th>#</th>
            <th>نام دسته‌بندی</th>
            <th>تعداد محصول</th>
            <th>عملیات</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($categories)): ?>
            <tr><td colspan="4" style="text-align:center;">هنوز دسته‌بندی‌ای ساخته نشده.</td></tr>
        <?php endif; ?>
        <?php foreach ($categories as $cat): ?>
            <tr>
                <td><?php echo $cat['id']; ?></td>
                <td>
                    <form method="post" action="categories.php" class="inline-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="rename">
                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                        <input type="text" name="name" value="<?php echo clean_input($cat['name']); ?>" maxlength="100" aria-label="نام دسته‌بندی" required>
                        <button type="submit" class="btn btn-outline">ذخیره نام</button>
                    </form>
                </td>
                <td class="num">
                    <?php if ((int) $cat['product_count'] > 0): ?>
                        <a href="dashboard.php?category=<?php echo $cat['id']; ?>"><?php echo $cat['product_count']; ?></a>
                    <?php else: ?>
                        ۰
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" action="categories.php" class="inline-form" data-confirm="<?php echo (int) $cat['product_count'] > 0
                              ? 'این دسته‌بندی ' . (int) $cat['product_count'] . ' محصول دارد که بدون دسته‌بندی می‌مانند. حذف شود؟'
                              : 'این دسته‌بندی حذف شود؟'; ?>">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                        <button type="submit" class="btn btn-danger">حذف</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
