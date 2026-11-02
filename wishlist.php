<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

set_seo(['title' => 'علاقه‌مندی‌های من', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';

$wishlist = new Wishlist();

render_breadcrumbs([['خانه', url('index.php')], ['علاقه‌مندی‌ها']]);
?>

<h1>علاقه‌مندی‌های من</h1>

<?php if (empty($_SESSION['customer_id'])): ?>
    <p style="margin-top:6px; font-size:13px; color:var(--plum-soft);">
        برای اینکه علاقه‌مندی‌هات تو حساب کاربری‌ات ذخیره بمونه و از دستگاه‌های دیگه هم ببینیشون، <a href="<?php echo url('login.php'); ?>" style="color:var(--pink-dark);">وارد شو</a>.
    </p>
<?php endif; ?>

<!-- محتوای این بخش با Ajax هم به‌روز می‌شه (app.js)؛ مثلاً وقتی تب دیگه‌ای محصولی رو اضافه یا حذف کنه -->
<div id="wishlistContent">
    <?php render_wishlist_grid($wishlist->products()); ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
