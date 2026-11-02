<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$productModel = new Product();
$product = $productModel->find($id);

// کد وضعیت باید قبل از چاپ اولین بایت HTML (header.php) تنظیم بشه
if (!$product) {
    http_response_code(404);
}

require_once __DIR__ . '/includes/header.php';

if (!$product) {
    echo '<p>محصول مورد نظر یافت نشد.</p>';
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$stock = (int) $product['stock'];
[$stock_text, $stock_class] = stock_badge($stock);
$is_wishlisted = (new Wishlist())->has((int) $product['id']);
?>

<div style="display:flex; gap:30px; flex-wrap:wrap; background:#fff; padding:20px; border-radius:8px;">
    <img src="<?php echo $product['image'] ? url('uploads/products/' . $product['image']) : 'https://via.placeholder.com/300x220/fbe4ee/c2477a?text=گل‌آرا'; ?>" alt="<?php echo clean_input($product['name']); ?>" style="width:300px; height:220px; object-fit:cover; border-radius:8px;">
    <div>
        <h1><?php echo clean_input($product['name']); ?></h1>
        <p class="price" style="font-size:20px; margin:12px 0;"><?php echo format_price($product['price']); ?></p>
        <p class="stock-badge <?php echo $stock_class; ?>" style="font-size:13px;"><?php echo $stock_text; ?></p>
        <p><?php echo nl2br(clean_input($product['description'])); ?></p>

        <div style="display:flex; align-items:center; gap:10px; margin-top:20px; flex-wrap:wrap;">
            <?php if ($stock > 0): ?>
                <input type="number" id="addToCartQty" class="qty-input" value="1" min="1" max="<?php echo $stock; ?>" aria-label="تعداد">
                <button type="button" id="addToCartBtn" data-product-id="<?php echo $product['id']; ?>" class="btn">افزودن به سبد خرید</button>
            <?php else: ?>
                <button type="button" class="btn" disabled style="opacity:.55; cursor:not-allowed;">ناموجود</button>
            <?php endif; ?>
            <button type="button" class="btn btn-outline wish-btn-text<?php echo $is_wishlisted ? ' is-active' : ''; ?>" data-product-id="<?php echo $product['id']; ?>">
                <?php echo icon('heart'); ?> <span class="wish-btn-label"><?php echo $is_wishlisted ? 'در علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها'; ?></span>
            </button>
            <span id="addToCartMessage" role="status" aria-live="polite" style="font-size:13px; color:#1e7a3c;"></span>
        </div>
    </div>
</div>

<?php render_reviews_section($conn, $product['id']); ?>

<script>
const addToCartBtn = document.getElementById('addToCartBtn');
if (addToCartBtn) {
    addToCartBtn.addEventListener('click', async function () {
        const btn = this;
        const message = document.getElementById('addToCartMessage');
        const qtyInput = document.getElementById('addToCartQty');
        const originalText = btn.textContent;

        // Loading state: تا جواب نیومده، دکمه غیرفعال بشه و متنش عوض بشه
        btn.disabled = true;
        btn.textContent = 'در حال افزودن...';

        const result = await Golara.addToCart(btn.dataset.productId, parseInt(qtyInput.value, 10) || 1);

        // چه موفق چه ناموفق، دکمه رو به حالت اول برگردون
        btn.disabled = false;
        btn.textContent = originalText;

        message.style.color = result.ok ? '#1e7a3c' : '#dc2626';
        message.textContent = result.ok ? 'به سبد خرید اضافه شد ✓ (در سبد: ' + result.data.quantity + ' عدد)' : result.message;
        setTimeout(function () { message.textContent = ''; }, 3500);
    });
}

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
    reviewForm.addEventListener('submit', async function (event) {
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

        const result = await Golara.api('POST', '/products/' + encodeURIComponent(productId) + '/reviews', {
            rating: parseInt(rating, 10),
            comment: comment
        });

        submitBtn.disabled = false;
        submitBtn.textContent = 'ثبت نظر';

        if (!result.ok) {
            errorBox.textContent = result.message;
            errorBox.style.display = 'block';
            return;
        }

        errorBox.style.display = 'none';

        // نظر جدید رو بالای لیست اضافه می‌کنیم
        document.getElementById('reviewsList').insertAdjacentHTML('afterbegin', result.data.html);

        // میانگین امتیاز رو آپدیت می‌کنیم
        document.getElementById('reviewsAverageText').textContent = result.data.average + ' از ۵ (' + result.data.count + ' نظر)';

        // فرم رو با یه پیام تشکر جایگزین می‌کنیم
        reviewForm.outerHTML = '<p class="review-login-note">ممنون از ثبت نظرت 🌸</p>';
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
