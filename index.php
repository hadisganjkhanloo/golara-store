<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

$category_id = isset($_GET['category']) ? (int) $_GET['category'] : 0;

$productModel = new Product();

// دسته‌بندی‌ها همینجا دوباره کوئری نمی‌شن؛ header.php قبلاً یه‌بار گرفتتشون (فصل ۲۲: بهینه‌سازی)
$categories = $nav_categories;

if ($category_id > 0) {
    $category_products = $productModel->getByCategory($category_id);
}

$current_category_name = '';
foreach ($categories as $cat) {
    if ((int) $cat['id'] === $category_id) {
        $current_category_name = $cat['name'];
    }
}
?>

<?php if ($category_id === 0): ?>

    <section class="hero-slider">
        <div class="hero-slide" style="background:linear-gradient(135deg, var(--pink), var(--lavender));">
            <div class="hero-slide-text">
                <h1>درخشش تو با گل‌آرا آغاز می‌شود</h1>
                <p>لوازم آرایشی و بهداشتی اورجینال، با ضمانت اصالت کالا</p>
                <a href="#products" class="btn">مشاهده محصولات</a>
            </div>
        </div>
        <div class="hero-slide" style="background:linear-gradient(135deg, var(--lavender), var(--rosewater));">
            <div class="hero-slide-text">
                <h1>مراقبت پوست، هدیه‌ای به خودت</h1>
                <p>سرم و کرم‌های روشن‌کننده و آبرسان، مناسب همه پوست‌ها</p>
                <a href="index.php?category=3" class="btn">مشاهده محصولات پوست</a>
            </div>
        </div>
        <div class="hero-slide" style="background:linear-gradient(135deg, #f3a6c9, var(--pink-dark));">
            <div class="hero-slide-text">
                <h1>عطرهای اورجینال دنیا</h1>
                <p>ماندگاری بالا، رایحه‌ای که یادت می‌مونه</p>
                <a href="index.php?category=4" class="btn">مشاهده عطرها</a>
            </div>
        </div>
    </section>

    <section class="trust-bar">
        <div class="trust-item"><span><?php echo icon('truck'); ?></span> ارسال سریع کالا</div>
        <div class="trust-item"><span><?php echo icon('shield'); ?></span> ضمانت اصالت کالا</div>
        <div class="trust-item"><span><?php echo icon('message-circle'); ?></span> پشتیبانی ۲۴ ساعته</div>
        <div class="trust-item"><span><?php echo icon('rotate-ccw'); ?></span> ضمانت بازگشت وجه</div>
    </section>

<?php endif; ?>

<div id="products">

<?php if ($category_id > 0): ?>

    <div class="category-filter">
        <a href="index.php" class="btn">همه محصولات</a>
        <?php foreach ($categories as $cat): ?>
            <a href="index.php?category=<?php echo $cat['id']; ?>" class="btn <?php echo $category_id === (int)$cat['id'] ? 'active' : ''; ?>">
                <?php echo clean_input($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="section-heading">
        <h2><?php echo clean_input($current_category_name); ?></h2>
    </div>

    <div class="product-grid">
        <?php if (empty($category_products)): ?>
            <p>محصولی یافت نشد.</p>
        <?php else: ?>
            <?php foreach ($category_products as $product): ?>
                <?php render_product_card($product); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php else: ?>

    <div class="section-heading">
        <h2>پرفروش‌ترین محصولات</h2>
    </div>
    <div class="product-grid" id="bestsellersGrid">
        <?php foreach ($productModel->all('price DESC', 8) as $product): ?>
            <?php render_product_card($product); ?>
        <?php endforeach; ?>
    </div>
    <div style="text-align:center; margin-top:20px;">
        <button type="button" id="loadMoreBtn" class="btn btn-outline" data-offset="8">نمایش بیشتر</button>
    </div>

    <?php foreach ($categories as $cat): ?>
        <div class="section-heading">
            <h2><?php echo clean_input($cat['name']); ?></h2>
            <a href="index.php?category=<?php echo $cat['id']; ?>" class="see-all">مشاهده همه ‹</a>
        </div>
        <div class="product-grid">
            <?php foreach ($productModel->getByCategory((int) $cat['id'], 4) as $product): ?>
                <?php render_product_card($product); ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <section class="newsletter-box">
        <h2>از تخفیف‌های ویژه باخبر شو</h2>
        <p>ایمیلت رو وارد کن تا جدیدترین محصولات و تخفیف‌های گل‌آرا رو از دست ندی</p>
        <?php if (!empty($_SESSION['newsletter_message'])): ?>
            <div class="alert alert-success" style="max-width:420px; margin:12px auto;"><?php echo $_SESSION['newsletter_message']; ?></div>
            <?php unset($_SESSION['newsletter_message']); ?>
        <?php endif; ?>
        <form method="post" action="newsletter.php" class="newsletter-form">
            <?php echo csrf_field(); ?>
            <input type="email" name="email" placeholder="ایمیل شما" required>
            <button type="submit" class="btn">عضویت</button>
        </form>
    </section>

<?php endif; ?>

</div>

<script>
const loadMoreBtn = document.getElementById('loadMoreBtn');
if (loadMoreBtn) {
    loadMoreBtn.addEventListener('click', function () {
        const offset = parseInt(loadMoreBtn.dataset.offset, 10);

        loadMoreBtn.disabled = true;
        loadMoreBtn.textContent = 'در حال بارگذاری...';

        fetch('/danshjoyar/ajax/load_more_products.php?offset=' + offset)
            .then(function (response) {
                return response.text();
            })
            .then(function (html) {
                document.getElementById('bestsellersGrid').insertAdjacentHTML('beforeend', html);

                if (html.indexOf('data-no-more') !== -1) {
                    // دیگه محصول بیشتری نیست، دکمه رو کلاً مخفی کن
                    loadMoreBtn.style.display = 'none';
                } else {
                    loadMoreBtn.disabled = false;
                    loadMoreBtn.textContent = 'نمایش بیشتر';
                    loadMoreBtn.dataset.offset = offset + 8;
                }
            });
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
