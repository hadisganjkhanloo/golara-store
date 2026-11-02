<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

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
$min_price = preg_match('/^[0-9]+$/', normalize_digits($_GET['min_price'] ?? '')) ? normalize_digits($_GET['min_price']) : '';
$max_price = preg_match('/^[0-9]+$/', normalize_digits($_GET['max_price'] ?? '')) ? normalize_digits($_GET['max_price']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));

$result = (new Product())->paginate([
    'q' => $q,
    'category_id' => $category_id,
    'in_stock' => $in_stock,
    'min_price' => $min_price,
    'max_price' => $max_price,
    'sort' => $sort,
], $page, PRODUCTS_PER_PAGE);

$nav_cats = (new Category())->all('id ASC');
$current_category = '';
foreach ($nav_cats as $cat) {
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
$filter_count = (int) ($q !== '') + (int) ($category_id > 0) + (int) $in_stock + (int) ($min_price !== '') + (int) ($max_price !== '') + (int) ($sort !== 'newest');

// سئو: هر ترکیب فیلتر/مرتب‌سازی یه صفحه‌ی تکراریه؛ canonical به صفحه‌ی اصلی دسته اشاره می‌کنه
// و صفحه‌های فیلترشده noindex هستن تا موتور جستجو هزاران آدرس تکراری نبینه
$title = $current_category !== '' ? $current_category : 'همه محصولات';
$canonical_query = array_filter(['category' => $category_id, 'page' => $result['page'] > 1 ? $result['page'] : 0]);
set_seo([
    'title' => $title . ($result['page'] > 1 ? ' - صفحه ' . $result['page'] : ''),
    'description' => $current_category !== ''
        ? 'خرید اینترنتی ' . $current_category . ' اورجینال از ' . SHOP_NAME . '؛ ضمانت اصالت کالا و ارسال سریع.'
        : 'همه لوازم آرایشی، مراقبت پوست و عطر ' . SHOP_NAME . ' را ببینید و با فیلتر قیمت و موجودی، محصول مناسب خود را پیدا کنید.',
    'canonical' => absolute_url('products.php' . ($canonical_query ? '?' . http_build_query($canonical_query) : '')),
    'noindex' => $q !== '' || $in_stock || $min_price !== '' || $max_price !== '' || $sort !== 'newest',
]);

$page_scripts = [];
require_once __DIR__ . '/includes/header.php';

render_breadcrumbs($current_category !== '' ? [['خانه', url('index.php')], ['محصولات', url('products.php')], [$current_category]] : [['خانه', url('index.php')], ['محصولات']]);
?>

<div class="section-heading" style="margin-top:0;">
    <h1><?php echo clean_input($title); ?></h1>
    <span style="font-size:13px; color:var(--plum-soft);"><?php echo number_format($result['total']); ?> محصول</span>
</div>

<!-- روی موبایل جمع‌شده است تا محصولات زیر خط دید نیفتن؛ روی دسکتاپ خودکار باز می‌شه (app.js) -->
<details class="filter-panel" id="filterPanel" <?php echo $filter_count > 0 ? 'open' : ''; ?>>
    <summary>فیلتر و مرتب‌سازی<?php echo $filter_count > 0 ? ' <span class="filter-count">' . $filter_count . '</span>' : ''; ?></summary>
    <form method="get" action="<?php echo url('products.php'); ?>" class="filter-bar">
        <div class="filter-field filter-wide">
            <label for="f_q">جستجو در نام</label>
            <input type="text" id="f_q" name="q" value="<?php echo clean_input($q); ?>" placeholder="مثلا: رژ لب">
        </div>
        <div class="filter-field">
            <label for="f_category">دسته‌بندی</label>
            <select id="f_category" name="category">
                <option value="0">همه</option>
                <?php foreach ($nav_cats as $cat): ?>
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
            <?php if ($filter_count > 0): ?>
                <a href="<?php echo url('products.php'); ?>" class="btn btn-outline">حذف فیلترها</a>
            <?php endif; ?>
        </div>
    </form>
</details>

<?php if (empty($result['items'])): ?>
    <p style="margin-top:20px;">محصولی با این شرایط پیدا نشد.</p>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($result['items'] as $product): render_product_card($product); endforeach; ?>
    </div>

    <?php render_pagination($result['page'], $result['pages'], url('products.php'), $active_query); ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
