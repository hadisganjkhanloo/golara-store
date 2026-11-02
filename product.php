<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$productModel = new Product();
$product = $productModel->find($id);

// کد وضعیت باید قبل از چاپ اولین بایت HTML (header.php) تنظیم بشه
if (!$product) {
    http_response_code(404);
    set_seo(['title' => 'محصول پیدا نشد', 'noindex' => true]);
}

if ($product) {
    $reviewModel = new Review();
    $rating = $reviewModel->getAverageForProduct((int) $product['id']);
    $stock = (int) $product['stock'];
    $category = $product['category_id'] ? (new Category())->find((int) $product['category_id']) : null;

    $main_image = product_image($product['image'], 720);
    $description = trim(preg_replace('/\s+/u', ' ', (string) $product['description']));
    $meta_description = mb_strimwidth($description, 0, 155, '…', 'UTF-8');

    // داده ساختاریافته‌ی محصول (JSON-LD) برای نمایش قیمت، موجودی و ستاره تو نتایج گوگل
    // قیمت‌ها تو دیتابیس تومانه؛ کد ارز استاندارد IRR (ریال) است، پس ×۱۰
    $jsonld = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['name'],
        'description' => $description,
        'sku' => (string) $product['id'],
        'image' => $product['image'] ? [absolute_url('uploads/products/' . $product['image'])] : [],
        'offers' => [
            '@type' => 'Offer',
            'url' => absolute_url('product.php?id=' . $product['id']),
            'priceCurrency' => 'IRR',
            'price' => (int) $product['price'] * 10,
            'availability' => $stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        ],
    ];
    if ($rating['count'] > 0) {
        $jsonld['aggregateRating'] = [
            '@type' => 'AggregateRating',
            'ratingValue' => $rating['average'],
            'reviewCount' => $rating['count'],
        ];
    }

    set_seo([
        'title' => $product['name'],
        'description' => $meta_description !== '' ? $meta_description : $product['name'] . ' با ضمانت اصالت کالا در ' . SHOP_NAME,
        'image' => $product['image'] ? absolute_url('uploads/products/' . $product['image']) : absolute_url('assets/img/og-image.png'),
        'canonical' => absolute_url('product.php?id=' . $product['id']),
        'type' => 'product',
        'jsonld' => [$jsonld],
    ]);
}

$page_scripts = ['assets/js/product.js'];
require_once __DIR__ . '/includes/header.php';

if (!$product) {
    echo '<p>محصول مورد نظر یافت نشد. <a href="' . url('products.php') . '">مشاهده همه محصولات</a></p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

[$stock_text, $stock_class] = stock_badge($stock);
$is_wishlisted = (new Wishlist())->has((int) $product['id']);
$max_qty = max(1, min($stock, MAX_QTY_PER_ITEM));
$related = $productModel->related($product, 4);

$crumbs = [['خانه', url('index.php')], ['محصولات', url('products.php')]];
if ($category) {
    $crumbs[] = [$category['name'], url('products.php?category=' . $category['id'])];
}
$crumbs[] = [$product['name']];
render_breadcrumbs($crumbs);
?>

<div class="product-detail">
    <?php if ($product['image']): ?>
        <a href="<?php echo url('uploads/products/' . $product['image']); ?>" class="product-zoom" data-zoom aria-label="بزرگ‌نمایی عکس <?php echo clean_input($product['name']); ?>">
            <img src="<?php echo $main_image['url']; ?>" width="<?php echo $main_image['width']; ?>" height="<?php echo $main_image['height']; ?>" alt="<?php echo clean_input($product['name']); ?>" fetchpriority="high">
            <span class="zoom-hint" aria-hidden="true"><?php echo icon('search'); ?> بزرگ‌نمایی</span>
        </a>
    <?php else: ?>
        <img class="product-zoom" src="<?php echo $main_image['url']; ?>" width="<?php echo $main_image['width']; ?>" height="<?php echo $main_image['height']; ?>" alt="<?php echo clean_input($product['name']); ?>">
    <?php endif; ?>

    <div class="product-info">
        <h1><?php echo clean_input($product['name']); ?></h1>
        <?php if ($rating['count'] > 0): ?>
            <p class="card-rating"><?php echo render_stars($rating['average']); ?> <span><?php echo $rating['average']; ?> (<?php echo $rating['count']; ?> نظر)</span></p>
        <?php endif; ?>
        <p class="price" style="font-size:20px; margin:12px 0;"><?php echo format_price($product['price']); ?></p>
        <p class="stock-badge <?php echo $stock_class; ?>" style="font-size:13px;"><?php echo $stock_text; ?></p>
        <p><?php echo nl2br(clean_input($product['description'])); ?></p>

        <div class="product-actions">
            <?php if ($stock > 0): ?>
                <label for="addToCartQty" class="sr-only">تعداد</label>
                <input type="number" id="addToCartQty" class="qty-input" value="1" min="1" max="<?php echo $max_qty; ?>">
                <button type="button" id="addToCartBtn" data-product-id="<?php echo $product['id']; ?>" class="btn">افزودن به سبد خرید</button>
            <?php else: ?>
                <button type="button" class="btn btn-disabled" disabled aria-disabled="true">ناموجود</button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline wish-btn-text<?php echo $is_wishlisted ? ' is-active' : ''; ?>" data-product-id="<?php echo $product['id']; ?>" aria-pressed="<?php echo $is_wishlisted ? 'true' : 'false'; ?>">
                <?php echo icon('heart'); ?> <span class="wish-btn-label"><?php echo $is_wishlisted ? 'در علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'; ?></span>
            </button>
            <span id="addToCartMessage" role="status" aria-live="polite" class="action-message"></span>
        </div>
    </div>
</div>

<?php render_reviews_section($conn, $product['id']); ?>

<?php if (!empty($related)): ?>
    <section class="related-section" aria-labelledby="relatedTitle">
        <h2 id="relatedTitle">محصولات مشابه</h2>
        <div class="product-grid">
            <?php foreach ($related as $item): render_product_card($item); endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
