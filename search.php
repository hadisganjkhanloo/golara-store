<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$per_page = Api\SearchController::PER_PAGE;
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$error = '';
$result = ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1];

if ($query !== '') {
    if (!preg_match('/^[\p{L}\p{N}\s]+$/u', $query)) {
        $error = 'عبارت جستجو فقط می‌تواند شامل حروف، عدد و فاصله باشد.';
    } else {
        $result = (new Product())->paginate(['q' => $query], $page, $per_page);
    }
}

// نتایج جستجو محتوای مستقل نیستن؛ تو موتور جستجو ایندکس نمی‌شن
set_seo(['title' => 'جستجوی محصولات', 'noindex' => true]);
$page_scripts = ['assets/js/search.js'];
require_once __DIR__ . '/includes/header.php';

render_breadcrumbs([['خانه', url('index.php')], ['جستجو']]);
?>

<h1>جستجوی محصولات</h1>

<form method="get" action="<?php echo url('search.php'); ?>" class="box" style="margin-bottom:24px;" role="search">
    <label for="q">نام محصول را وارد کنید</label>
    <input type="search" id="q" name="q" value="<?php echo clean_input($query); ?>" placeholder="مثلا: رژ لب" autocomplete="off" aria-controls="searchResults">
    <button type="submit" class="btn">جستجو</button>
</form>

<div class="product-grid" id="searchResults" aria-live="polite">
    <?php if ($error): ?>
        <p><?php echo clean_input($error); ?></p>
    <?php elseif ($query !== ''): ?>
        <?php if (empty($result['items'])): ?>
            <p>نتیجه‌ای برای «<?php echo clean_input($query); ?>» یافت نشد.</p>
        <?php else: ?>
            <?php foreach ($result['items'] as $product): render_product_card($product); endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div id="searchPagination">
    <?php render_pagination($result['page'], $result['pages'], url('search.php'), ['q' => $query]); ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
