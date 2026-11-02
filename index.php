<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// لینک‌های قدیمی index.php?category=3 به صفحه محصولات (که فیلتر و صفحه‌بندی داره) منتقل می‌شن
// این redirect باید قبل از header.php باشه، چون بعد از چاپ HTML دیگه نمی‌شه هدر فرستاد
if (isset($_GET['category'])) {
    redirect(url('products.php?category=' . (int) $_GET['category']));
}

// سئو: معرفی فروشگاه (Organization) و کادر جستجوی سایت (WebSite + SearchAction) برای گوگل
set_seo([
    'canonical' => absolute_url('index.php'),
    'jsonld' => [
        [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => SHOP_NAME,
            'url' => absolute_url('index.php'),
            'logo' => absolute_url('assets/img/apple-touch-icon.png'),
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => SHOP_NAME,
            'url' => absolute_url('index.php'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => absolute_url('products.php') . '?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ],
]);

require_once __DIR__ . '/includes/header.php';

$productModel = new Product();

// دسته‌بندی‌ها همینجا دوباره کوئری نمی‌شن؛ header.php قبلاً یه‌بار گرفتتشون (فصل ۲۲: بهینه‌سازی)
$categories = $nav_categories;
?>

<!-- یک h1 برای کل صفحه (برای موتور جستجو و screen reader)؛ تیترهای اسلایدها h2 هستن -->
<h1 class="sr-only"><?php echo clean_input(SHOP_NAME); ?> — فروشگاه اینترنتی لوازم آرایشی، مراقبت پوست و عطر اورجینال</h1>

<section class="hero-slider" id="heroSlider" aria-roledescription="اسلایدر" aria-label="پیشنهادهای ویژه">
    <button type="button" class="hero-pause" id="heroPause" aria-pressed="false">توقف اسلایدر</button>
    <div class="hero-slide" style="background:linear-gradient(135deg, var(--pink-dark), #8f5a99);">
        <div class="hero-slide-text">
            <h2>درخشش تو با <?php echo clean_input(SHOP_NAME); ?> آغاز می‌شود</h2>
            <p>لوازم آرایشی و بهداشتی اورجینال، با ضمانت اصالت کالا</p>
            <a href="#products" class="btn">مشاهده محصولات</a>
        </div>
    </div>
    <div class="hero-slide" style="background:linear-gradient(135deg, #7f5aa8, #b2557a);">
        <div class="hero-slide-text">
            <h2>مراقبت پوست، هدیه‌ای به خودت</h2>
            <p>سرم و کرم‌های روشن‌کننده و آبرسان، مناسب همه پوست‌ها</p>
            <a href="<?php echo url('products.php?category=3'); ?>" class="btn">مشاهده محصولات پوست</a>
        </div>
    </div>
    <div class="hero-slide" style="background:linear-gradient(135deg, #b2557a, #8a3d63);">
        <div class="hero-slide-text">
            <h2>عطرهای اورجینال دنیا</h2>
            <p>ماندگاری بالا، رایحه‌ای که یادت می‌مونه</p>
            <a href="<?php echo url('products.php?category=4'); ?>" class="btn">مشاهده عطرها</a>
        </div>
    </div>
</section>

<section class="trust-bar" aria-label="مزایای خرید">
    <div class="trust-item"><span><?php echo icon('truck'); ?></span> ارسال سریع کالا</div>
    <div class="trust-item"><span><?php echo icon('shield'); ?></span> ضمانت اصالت کالا</div>
    <div class="trust-item"><span><?php echo icon('message-circle'); ?></span> پشتیبانی آنلاین</div>
    <div class="trust-item"><span><?php echo icon('rotate-ccw'); ?></span> ضمانت بازگشت وجه</div>
</section>

<div id="products">

    <div class="section-heading">
        <h2>پرفروش‌ترین محصولات</h2>
        <a href="<?php echo url('products.php?sort=bestseller'); ?>" class="see-all">مشاهده همه ‹</a>
    </div>
    <div class="product-grid">
        <?php foreach ($productModel->bestsellers(8) as $product): ?>
            <?php render_product_card($product); ?>
        <?php endforeach; ?>
    </div>

    <?php foreach ($categories as $cat): ?>
        <div class="section-heading">
            <h2><?php echo clean_input($cat['name']); ?></h2>
            <a href="<?php echo url('products.php?category=' . $cat['id']); ?>" class="see-all">مشاهده همه ‹</a>
        </div>
        <div class="product-grid">
            <?php foreach ($productModel->getByCategory((int) $cat['id'], 4) as $product): ?>
                <?php render_product_card($product); ?>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div style="text-align:center; margin-top:28px;">
        <a href="<?php echo url('products.php'); ?>" class="btn btn-outline">مشاهده همه محصولات</a>
    </div>

    <section class="newsletter-box" id="newsletter" aria-labelledby="newsletterTitle">
        <h2 id="newsletterTitle">از تخفیف‌های ویژه باخبر شو</h2>
        <p>ایمیلت رو وارد کن تا جدیدترین محصولات و تخفیف‌های <?php echo clean_input(SHOP_NAME); ?> رو از دست ندی</p>
        <?php if (!empty($_SESSION['newsletter_message'])): ?>
            <div class="alert alert-success" role="status" style="max-width:480px; margin:12px auto;"><?php echo clean_input($_SESSION['newsletter_message']); ?></div>
            <?php unset($_SESSION['newsletter_message']); ?>
        <?php endif; ?>
        <!-- با Ajax ارسال می‌شه (app.js)، پس صفحه بالا نمی‌پره؛ اگه جاوااسکریپت خاموش بود، همین فرم عادی کار می‌کنه -->
        <form method="post" action="<?php echo url('newsletter.php'); ?>" class="newsletter-form" id="newsletterForm">
            <?php echo csrf_field(); ?>
            <label for="newsletterEmail" class="sr-only">ایمیل شما</label>
            <input type="email" id="newsletterEmail" name="email" placeholder="ایمیل شما" required dir="ltr" autocomplete="email">
            <button type="submit" class="btn">عضویت</button>
        </form>
        <p class="newsletter-message" id="newsletterMessage" role="status" aria-live="polite"></p>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
