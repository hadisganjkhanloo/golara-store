<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/_header.php';

const ADMIN_PER_PAGE = 8;

$sort_options = [
    'newest' => 'جدیدترین',
    'oldest' => 'قدیمی‌ترین',
    'name' => 'نام (الفبا)',
    'price_asc' => 'قیمت: کم به زیاد',
    'price_desc' => 'قیمت: زیاد به کم',
    'stock_asc' => 'موجودی: کم به زیاد',
    'stock_desc' => 'موجودی: زیاد به کم',
];
$stock_options = [
    '' => 'همه',
    'in' => 'موجود',
    'low' => 'کم‌موجود (۵ عدد یا کمتر)',
    'out' => 'ناموجود',
];
$deleted_options = [
    '' => 'فعال (نمایش‌داده‌شده در فروشگاه)',
    'only' => 'حذف‌شده',
    'all' => 'همه',
];

$q = trim($_GET['q'] ?? '');
$category_id = (int) ($_GET['category'] ?? 0);
$stock_state = isset($stock_options[$_GET['stock'] ?? '']) ? ($_GET['stock'] ?? '') : '';
$deleted = isset($deleted_options[$_GET['deleted_filter'] ?? '']) ? ($_GET['deleted_filter'] ?? '') : '';
$sort = isset($sort_options[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'newest';
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = (new Product())->paginate([
    'q' => $q,
    'category_id' => $category_id,
    'stock_state' => $stock_state,
    'deleted' => $deleted,
    'sort' => $sort,
], $page, ADMIN_PER_PAGE);

$categories = (new Category())->all('id ASC');

$active_query = [
    'q' => $q,
    'category' => $category_id,
    'stock' => $stock_state,
    'deleted_filter' => $deleted,
    'sort' => $sort === 'newest' ? '' : $sort,
];
$has_filter = $q !== '' || $category_id || $stock_state !== '' || $deleted !== '' || $sort !== 'newest';

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);
?>

<h1>مدیریت محصولات</h1>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>" role="status"><?php echo clean_input($flash['text']); ?></div>
<?php endif; ?>

<form method="get" action="dashboard.php" class="admin-toolbar">
    <div class="filter-field filter-wide">
        <label for="a_q">جستجو در نام محصول</label>
        <input type="text" id="a_q" name="q" value="<?php echo clean_input($q); ?>" placeholder="نام محصول...">
    </div>
    <div class="filter-field">
        <label for="a_cat">دسته‌بندی</label>
        <select id="a_cat" name="category">
            <option value="0">همه</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category_id === (int) $cat['id'] ? 'selected' : ''; ?>><?php echo clean_input($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="a_stock">وضعیت موجودی</label>
        <select id="a_stock" name="stock">
            <?php foreach ($stock_options as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $stock_state === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="a_deleted">نمایش</label>
        <select id="a_deleted" name="deleted_filter">
            <?php foreach ($deleted_options as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $deleted === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="a_sort">مرتب‌سازی</label>
        <select id="a_sort" name="sort">
            <?php foreach ($sort_options as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $sort === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-actions">
        <button type="submit" class="btn">اعمال</button>
        <?php if ($has_filter): ?>
            <a href="dashboard.php" class="btn btn-outline">حذف فیلترها</a>
        <?php endif; ?>
    </div>
</form>

<p style="font-size:13px; color:var(--plum-soft);"><?php echo number_format($result['total']); ?> محصول پیدا شد</p>

<div class="table-scroll">
<table class="admin-table" style="margin-top:10px;">
    <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col">نام محصول</th>
            <th scope="col">دسته‌بندی</th>
            <th scope="col">قیمت</th>
            <th scope="col">موجودی</th>
            <th scope="col">عملیات</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($result['items'])): ?>
            <tr><td colspan="6" style="text-align:center;">محصولی با این شرایط پیدا نشد.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['items'] as $product): ?>
            <?php [$stock_text, $stock_class] = stock_badge((int) $product['stock']); ?>
            <tr>
                <td><?php echo $product['id']; ?></td>
                <td>
                    <?php echo clean_input($product['name']); ?>
                    <?php if ($product['deleted_at']): ?><span class="badge badge-off">حذف‌شده</span><?php endif; ?>
                </td>
                <td><?php echo clean_input($product['category_name'] ?? '-'); ?></td>
                <td><?php echo format_price($product['price']); ?></td>
                <td class="num"><span class="stock-pill <?php echo $stock_class; ?>" title="<?php echo $stock_text; ?>"><?php echo (int) $product['stock']; ?></span></td>
                <td>
                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn">ویرایش</a>
                    <?php if ($product['deleted_at']): ?>
                        <form method="post" action="restore_product.php" class="inline-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                            <button type="submit" class="btn btn-outline">بازگردانی</button>
                        </form>
                    <?php else: ?>
                        <a href="confirm_delete.php?id=<?php echo $product['id']; ?>" class="btn btn-danger">حذف</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php render_pagination($result['page'], $result['pages'], url('admin/dashboard.php'), $active_query); ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
