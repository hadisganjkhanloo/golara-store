<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

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
?>

<h1>جستجوی محصولات</h1>

<form method="get" action="<?php echo url('search.php'); ?>" class="box" style="margin-bottom:24px;">
    <label for="q">نام محصول را وارد کنید</label>
    <input type="text" id="q" name="q" value="<?php echo clean_input($query); ?>" placeholder="مثلا: رژ لب" autocomplete="off">
    <button type="submit" class="btn">جستجو</button>
</form>

<div class="product-grid" id="searchResults">
    <?php if ($error): ?>
        <p><?php echo $error; ?></p>
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

<script>
const searchInput = document.getElementById('q');
const resultsBox = document.getElementById('searchResults');
const paginationBox = document.getElementById('searchPagination');
let debounceTimer;

searchInput.addEventListener('input', function () {
    // هر بار که کاربر تایپ می‌کنه، تایمر قبلی رو پاک می‌کنیم
    clearTimeout(debounceTimer);
    const query = searchInput.value.trim();

    if (query === '') {
        resultsBox.innerHTML = '';
        paginationBox.innerHTML = '';
        return;
    }

    // ۴۰۰ میلی‌ثانیه صبر می‌کنیم؛ اگه تا اون موقع کاربر دوباره تایپ کرد، این درخواست لغو می‌شه
    debounceTimer = setTimeout(async function () {
        resultsBox.innerHTML = '<p>در حال جستجو...</p>';

        const result = await Golara.api('GET', '/search?q=' + encodeURIComponent(query));

        // اگه تا جواب برسه کاربر چیز دیگه‌ای تایپ کرده، این جواب کهنه رو نادیده می‌گیریم
        if (searchInput.value.trim() !== query) {
            return;
        }

        if (!result.ok) {
            resultsBox.innerHTML = '<p>' + result.message.replace(/</g, '&lt;') + '</p>';
            paginationBox.innerHTML = '';
            return;
        }

        resultsBox.innerHTML = result.data.html;
        paginationBox.innerHTML = result.data.pagination;
    }, 400);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
