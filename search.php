<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$error = '';
$results = [];

if ($query !== '') {
    $search = search_products($conn, $query);
    $error = $search['error'];
    $results = $search['products'];
}
?>

<h1>جستجوی محصولات</h1>

<form method="get" action="search.php" class="box" style="margin-bottom:24px;">
    <label for="q">نام محصول را وارد کنید</label>
    <input type="text" id="q" name="q" value="<?php echo clean_input($query); ?>" placeholder="مثلا: رژ لب" autocomplete="off">
    <button type="submit" class="btn">جستجو</button>
</form>

<div class="product-grid" id="searchResults">
    <?php if ($error): ?>
        <p><?php echo $error; ?></p>
    <?php elseif ($query !== ''): ?>
        <?php if (empty($results)): ?>
            <p>نتیجه‌ای برای «<?php echo clean_input($query); ?>» یافت نشد.</p>
        <?php else: ?>
            <?php foreach ($results as $product): render_product_card($product); endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const searchInput = document.getElementById('q');
const resultsBox = document.getElementById('searchResults');
let debounceTimer;

searchInput.addEventListener('input', function () {
    // هر بار که کاربر تایپ می‌کنه، تایمر قبلی رو پاک می‌کنیم
    clearTimeout(debounceTimer);
    const query = searchInput.value.trim();

    if (query === '') {
        resultsBox.innerHTML = '';
        return;
    }

    // ۴۰۰ میلی‌ثانیه صبر می‌کنیم؛ اگه تا اون موقع کاربر دوباره تایپ کرد، این درخواست لغو می‌شه
    debounceTimer = setTimeout(function () {
        resultsBox.innerHTML = '<p>در حال جستجو...</p>';

        fetch('/danshjoyar/ajax/live_search.php?q=' + encodeURIComponent(query))
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                resultsBox.innerHTML = html;
            });
    }, 400);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
