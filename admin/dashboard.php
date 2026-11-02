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

$q = trim($_GET['q'] ?? '');
$category_id = (int) ($_GET['category'] ?? 0);
$stock_state = isset($stock_options[$_GET['stock'] ?? '']) ? ($_GET['stock'] ?? '') : '';
$sort = isset($sort_options[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'newest';
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = (new Product())->paginate([
    'q' => $q,
    'category_id' => $category_id,
    'stock_state' => $stock_state,
    'sort' => $sort,
], $page, ADMIN_PER_PAGE);

$categories = (new Category())->all('id ASC');

$active_query = [
    'q' => $q,
    'category' => $category_id,
    'stock' => $stock_state,
    'sort' => $sort === 'newest' ? '' : $sort,
];
$has_filter = $q !== '' || $category_id || $stock_state !== '' || $sort !== 'newest';
?>

<h1>مدیریت محصولات</h1>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert alert-success">محصول با موفقیت حذف شد.</div>
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

<table class="admin-table" style="margin-top:10px;">
    <thead>
        <tr>
            <th>#</th>
            <th>نام محصول</th>
            <th>دسته‌بندی</th>
            <th>قیمت</th>
            <th>موجودی</th>
            <th>عملیات</th>
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
                <td><?php echo clean_input($product['name']); ?></td>
                <td><?php echo clean_input($product['category_name'] ?? '-'); ?></td>
                <td><?php echo format_price($product['price']); ?></td>
                <td class="num"><span class="stock-pill <?php echo $stock_class; ?>" title="<?php echo $stock_text; ?>"><?php echo (int) $product['stock']; ?></span></td>
                <td>
                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn">ویرایش</a>
                    <a href="confirm_delete.php?id=<?php echo $product['id']; ?>" class="btn btn-danger">حذف</a>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php render_pagination($result['page'], $result['pages'], url('admin/dashboard.php'), $active_query); ?>

<?php require_once __DIR__ . '/_footer.php'; ?>
