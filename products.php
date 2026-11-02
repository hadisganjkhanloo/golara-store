<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

// با ۱۲ محصول نمونه، ۸ تا در هر صفحه یعنی دو صفحه و صفحه‌بندی واقعاً دیده می‌شه
const PRODUCTS_PER_PAGE = 8;

$sort_options = [
    'newest' => 'جدیدترین',
    'bestseller' => 'پرفروش‌ترین',
    'price_asc' => 'ارزان‌ترین',
    'price_desc' => 'گران‌ترین',
    'name' => 'بر اساس نام',
];

// ورودی‌ها اعتبارسنجی می‌شن؛ مقدار نامعتبر مثل «بدون فیلتر» رفتار می‌کنه
$category_id = isset($_GET['category']) ? (int) $_GET['category'] : 0;
$sort = $_GET['sort'] ?? 'newest';
if (!isset($sort_options[$sort])) {
    $sort = 'newest';
}
$q = trim($_GET['q'] ?? '');
$in_stock = !empty($_GET['in_stock']);
$min_price = preg_match('/^[0-9]+$/', $_GET['min_price'] ?? '') ? $_GET['min_price'] : '';
$max_price = preg_match('/^[0-9]+$/', $_GET['max_price'] ?? '') ? $_GET['max_price'] : '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = (new Product())->paginate([
    'q' => $q,
    'category_id' => $category_id,
    'in_stock' => $in_stock,
    'min_price' => $min_price,
    'max_price' => $max_price,
    'sort' => $sort,
], $page, PRODUCTS_PER_PAGE);

$current_category = '';
foreach ($nav_categories as $cat) {
    if ((int) $cat['id'] === $category_id) {
        $current_category = $cat['name'];
    }
}

// پارامترهای فعال؛ لینک‌های صفحه‌بندی همه رو نگه می‌دارن
$active_query = [
    'category' => $category_id,
    'q' => $q,
    'sort' => $sort === 'newest' ? '' : $sort,
    'in_stock' => $in_stock ? 1 : '',
    'min_price' => $min_price,
    'max_price' => $max_price,
];
$has_filter = $category_id || $q !== '' || $in_stock || $min_price !== '' || $max_price !== '' || $sort !== 'newest';
?>

<div class="section-heading" style="margin-top:0;">
    <h1><?php echo $current_category !== '' ? clean_input($current_category) : 'همه محصولات'; ?></h1>
    <span style="font-size:13px; color:var(--plum-soft);"><?php echo number_format($result['total']); ?> محصول</span>
</div>

<form method="get" action="<?php echo url('products.php'); ?>" class="filter-bar">
    <div class="filter-field filter-wide">
        <label for="f_q">جستجو در نام</label>
        <input type="text" id="f_q" name="q" value="<?php echo clean_input($q); ?>" placeholder="مثلا: رژ لب">
    </div>
    <div class="filter-field">
        <label for="f_category">دسته‌بندی</label>
        <select id="f_category" name="category">
            <option value="0">همه</option>
            <?php foreach ($nav_categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo $category_id === (int) $cat['id'] ? 'selected' : ''; ?>><?php echo clean_input($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="f_sort">مرتب‌سازی</label>
        <select id="f_sort" name="sort">
            <?php foreach ($sort_options as $value => $label): ?>
                <option value="<?php echo $value; ?>" <?php echo $sort === $value ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-field">
        <label for="f_min">از قیمت (تومان)</label>
        <input type="text" inputmode="numeric" id="f_min" name="min_price" value="<?php echo $min_price; ?>" placeholder="0">
    </div>
    <div class="filter-field">
        <label for="f_max">تا قیمت (تومان)</label>
        <input type="text" inputmode="numeric" id="f_max" name="max_price" value="<?php echo $max_price; ?>" placeholder="بدون سقف">
    </div>
    <label class="filter-check"><input type="checkbox" name="in_stock" value="1" <?php echo $in_stock ? 'checked' : ''; ?>> فقط کالاهای موجود</label>
    <div class="filter-actions">
        <button type="submit" class="btn">اعمال فیلتر</button>
        <?php if ($has_filter): ?>
            <a href="<?php echo url('products.php'); ?>" class="btn btn-outline">حذف فیلترها</a>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($result['items'])): ?>
    <p style="margin-top:20px;">محصولی با این شرایط پیدا نشد.</p>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($result['items'] as $product): render_product_card($product); endforeach; ?>
    </div>

    <?php render_pagination($result['page'], $result['pages'], url('products.php'), $active_query); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
