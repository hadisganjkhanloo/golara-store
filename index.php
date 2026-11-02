<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

// لینک‌های قدیمی index.php?category=3 به صفحه محصولات (که فیلتر و صفحه‌بندی داره) منتقل می‌شن
// این redirect باید قبل از header.php باشه، چون بعد از چاپ HTML دیگه نمی‌شه هدر فرستاد
if (isset($_GET['category'])) {
    redirect(url('products.php?category=' . (int) $_GET['category']));
}

require_once __DIR__ . '/includes/header.php';

$productModel = new Product();

// دسته‌بندی‌ها همینجا دوباره کوئری نمی‌شن؛ header.php قبلاً یه‌بار گرفتتشون (فصل ۲۲: بهینه‌سازی)
$categories = $nav_categories;
?>

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
            <a href="<?php echo url('products.php?category=3'); ?>" class="btn">مشاهده محصولات پوست</a>
        </div>
    </div>
    <div class="hero-slide" style="background:linear-gradient(135deg, #f3a6c9, var(--pink-dark));">
        <div class="hero-slide-text">
            <h1>عطرهای اورجینال دنیا</h1>
            <p>ماندگاری بالا، رایحه‌ای که یادت می‌مونه</p>
            <a href="<?php echo url('products.php?category=4'); ?>" class="btn">مشاهده عطرها</a>
        </div>
    </div>
</section>

<section class="trust-bar">
    <div class="trust-item"><span><?php echo icon('truck'); ?></span> ارسال سریع کالا</div>
    <div class="trust-item"><span><?php echo icon('shield'); ?></span> ضمانت اصالت کالا</div>
    <div class="trust-item"><span><?php echo icon('message-circle'); ?></span> پشتیبانی ۲۴ ساعته</div>
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

    <section class="newsletter-box" id="newsletter">
        <h2>از تخفیف‌های ویژه باخبر شو</h2>
        <p>ایمیلت رو وارد کن تا جدیدترین محصولات و تخفیف‌های گل‌آرا رو از دست ندی</p>
        <?php if (!empty($_SESSION['newsletter_message'])): ?>
            <div class="alert alert-success" style="max-width:420px; margin:12px auto;"><?php echo clean_input($_SESSION['newsletter_message']); ?></div>
            <?php unset($_SESSION['newsletter_message']); ?>
        <?php endif; ?>
        <!-- با Ajax ارسال می‌شه (app.js)، پس صفحه بالا نمی‌پره؛ اگه جاوااسکریپت خاموش بود، همین فرم عادی کار می‌کنه -->
        <form method="post" action="<?php echo url('newsletter.php'); ?>" class="newsletter-form" id="newsletterForm">
            <?php echo csrf_field(); ?>
            <input type="email" name="email" placeholder="ایمیل شما" required>
            <button type="submit" class="btn">عضویت</button>
        </form>
        <p class="newsletter-message" id="newsletterMessage" role="status" aria-live="polite"></p>
    </section>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
