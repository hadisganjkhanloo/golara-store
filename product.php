<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$productModel = new Product();
$product = $productModel->find($id);

if (!$product) {
    echo '<p>محصول مورد نظر یافت نشد.</p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}
?>

<div style="display:flex; gap:30px; flex-wrap:wrap; background:#fff; padding:20px; border-radius:8px;">
    <img src="<?php echo $product['image'] ? '/danshjoyar/uploads/products/' . $product['image'] : 'https://via.placeholder.com/300x220/fbe4ee/c2477a?text=گل‌آرا'; ?>" alt="<?php echo clean_input($product['name']); ?>" style="width:300px; height:220px; object-fit:cover; border-radius:8px;">
    <div>
        <h1><?php echo clean_input($product['name']); ?></h1>
        <p class="price" style="font-size:20px; margin:12px 0;"><?php echo format_price($product['price']); ?></p>
        <p><?php echo nl2br(clean_input($product['description'])); ?></p>

        <?php $is_wishlisted = isset($_SESSION['wishlist']) && in_array($product['id'], $_SESSION['wishlist']); ?>
        <div style="display:flex; align-items:center; gap:10px; margin-top:20px;">
            <button type="button" id="addToCartBtn" data-product-id="<?php echo $product['id']; ?>" class="btn">افزودن به سبد خرید</button>
            <button type="button" class="btn btn-outline wish-btn-text<?php echo $is_wishlisted ? ' is-active' : ''; ?>" data-product-id="<?php echo $product['id']; ?>">
                <?php echo icon('heart'); ?> <span class="wish-btn-label"><?php echo $is_wishlisted ? 'در علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'; ?></span>
            </button>
            <span id="addToCartMessage" style="font-size:13px; color:#1e7a3c;"></span>
        </div>
    </div>
</div>

<?php render_reviews_section($conn, $product['id']); ?>

<script>
document.getElementById('addToCartBtn').addEventListener('click', function () {
    const btn = this;
    const productId = btn.dataset.productId;
    const message = document.getElementById('addToCartMessage');
    const originalText = btn.textContent;

    // Loading state: تا جواب نیومده، دکمه غیرفعال بشه و متنش عوض بشه
    btn.disabled = true;
    btn.textContent = 'در حال افزودن...';

    fetch('/danshjoyar/ajax/add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId + '&csrf_token=' + encodeURIComponent(getCsrfToken())
    })
        .then(function (response) {
            return response.text();
        })
        .then(function (newCartCount) {
            document.getElementById('cartBadge').textContent = newCartCount;
            message.textContent = 'به سبد خرید اضافه شد ✓';
            setTimeout(function () { message.textContent = ''; }, 2000);
        })
        .catch(function () {
            message.style.color = '#dc2626';
            message.textContent = 'مشکلی پیش اومد، دوباره امتحان کن.';
        })
        .finally(function () {
            // چه موفق چه ناموفق، دکمه رو به حالت اول برگردون
            btn.disabled = false;
            btn.textContent = originalText;
        });
});

// ستاره‌های انتخاب امتیاز
const starPicker = document.getElementById('starPicker');
if (starPicker) {
    const starButtons = starPicker.querySelectorAll('.star-picker-btn');
    const ratingInput = document.getElementById('ratingInput');

    starButtons.forEach(function (starBtn) {
        starBtn.addEventListener('click', function () {
            const value = parseInt(starBtn.dataset.value, 10);
            ratingInput.value = value;

            starButtons.forEach(function (b) {
                const isFilled = parseInt(b.dataset.value, 10) <= value;
                b.querySelector('svg').classList.toggle('is-filled', isFilled);
            });
        });
    });
}

// ارسال فرم نظر با Ajax
const reviewForm = document.getElementById('reviewForm');
if (reviewForm) {
    reviewForm.addEventListener('submit', function (event) {
        event.preventDefault();

        const productId = reviewForm.dataset.productId;
        const rating = document.getElementById('ratingInput').value;
        const comment = document.getElementById('reviewComment').value;
        const errorBox = document.getElementById('reviewFormError');

        if (rating === '0') {
            errorBox.textContent = 'لطفاً یک امتیاز انتخاب کنید.';
            errorBox.style.display = 'block';
            return;
        }

        const submitBtn = reviewForm.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.textContent = 'در حال ارسال...';

        fetch('/danshjoyar/ajax/submit_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'product_id=' + encodeURIComponent(productId)
                + '&rating=' + encodeURIComponent(rating)
                + '&comment=' + encodeURIComponent(comment)
                + '&csrf_token=' + encodeURIComponent(getCsrfToken())
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'ثبت نظر';

                if (!data.success) {
                    errorBox.textContent = data.message;
                    errorBox.style.display = 'block';
                    return;
                }

                errorBox.style.display = 'none';

                // نظر جدید رو بالای لیست اضافه می‌کنیم
                document.getElementById('reviewsList').insertAdjacentHTML('afterbegin', data.html);

                // میانگین امتیاز رو آپدیت می‌کنیم
                document.getElementById('reviewsAverageText').textContent = data.average + ' از ۵ (' + data.count + ' نظر)';

                // فرم رو با یه پیام تشکر جایگزین می‌کنیم
                reviewForm.outerHTML = '<p class="review-login-note">ممنون از ثبت نظرت 🌸</p>';
            });
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
