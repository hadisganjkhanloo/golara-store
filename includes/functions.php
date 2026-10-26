<?php
// توابع کمکی پروژه

function clean_input($data)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function is_valid_email($email)
{
    return preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

function is_valid_phone($phone)
{
    // شماره موبایل ایران، مثلا 09123456789
    return preg_match('/^09[0-9]{9}$/', $phone);
}

function format_price($price)
{
    return number_format($price) . ' تومان';
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function require_admin_login()
{
    if (empty($_SESSION['admin_id'])) {
        redirect('login.php');
    }
}

function require_customer_login()
{
    if (empty($_SESSION['customer_id'])) {
        redirect(url('login.php'));
    }
}

// یه توکن تصادفی می‌سازه و تو سشن نگه می‌داره؛ هر فرم باید همینو تو یه فیلد مخفی داشته باشه
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// خروجی آماده برای گذاشتن تو فرم
function csrf_field()
{
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

// موقع پردازش POST صداش می‌زنیم؛ اگه توکن غلط یا نبود، درخواست رو رد می‌کنه
function verify_csrf_token()
{
    $sent = $_POST['csrf_token'] ?? '';
    $real = $_SESSION['csrf_token'] ?? '';

    // hash_equals جلوی Timing Attack رو می‌گیره (مقایسه رشته‌ها به یه زمان ثابت انجام می‌شه)
    if ($sent === '' || $real === '' || !hash_equals($real, $sent)) {
        http_response_code(403);
        die('درخواست نامعتبر است. لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.');
    }
}

function icon($name, $class = 'icon')
{
    static $cache = [];

    if (!isset($cache[$name])) {
        $path = __DIR__ . '/../assets/icons/' . $name . '.svg';
        $cache[$name] = file_exists($path) ? file_get_contents($path) : '';
    }

    $svg = $cache[$name];
    if ($svg === '') {
        return '';
    }

    return preg_replace('/<svg /', '<svg class="' . $class . '" ', $svg, 1);
}

function search_products($conn, $query)
{
    // فقط حروف فارسی/انگلیسی، عدد و فاصله مجاز است
    if (!preg_match('/^[\p{L}\p{N}\s]+$/u', $query)) {
        return ['error' => 'عبارت جستجو فقط می‌تواند شامل حروف، عدد و فاصله باشد.', 'products' => []];
    }

    $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE name LIKE ? ORDER BY created_at DESC");
    $like = '%' . $query . '%';
    mysqli_stmt_bind_param($stmt, 's', $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $products = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }

    return ['error' => null, 'products' => $products];
}

function render_product_card($product)
{
    $image_url = $product['image']
        ? url('uploads/products/' . $product['image'])
        : 'https://via.placeholder.com/220x150/fbe4ee/c2477a?text=Golara';

    $is_wishlisted = (new Wishlist())->has((int) $product['id']);
    ?>
    <div class="product-card">
        <button type="button" class="wish-btn<?php echo $is_wishlisted ? ' is-active' : ''; ?>" data-product-id="<?php echo $product['id']; ?>" title="افزودن به علاقه‌مندی‌ها"><?php echo icon('heart'); ?></button>
        <img src="<?php echo $image_url; ?>" alt="<?php echo clean_input($product['name']); ?>" loading="lazy">
        <h3><?php echo clean_input($product['name']); ?></h3>
        <p class="price"><?php echo format_price($product['price']); ?></p>
        <a href="<?php echo url('product.php?id=' . $product['id']); ?>" class="btn">مشاهده</a>
    </div>
    <?php
}

function render_stars($rating, $class = 'stars')
{
    $rating = round($rating);
    $html = '<span class="' . $class . '">';
    for ($i = 1; $i <= 5; $i++) {
        $filled = $i <= $rating ? ' is-filled' : '';
        $html .= icon('star', 'icon star-icon' . $filled);
    }
    $html .= '</span>';
    return $html;
}

function render_review_item($review)
{
    ?>
    <div class="review-item">
        <div class="review-item-head">
            <strong><?php echo clean_input($review['customer_name']); ?></strong>
            <?php echo render_stars($review['rating']); ?>
        </div>
        <?php if (!empty($review['comment'])): ?>
            <p class="review-comment"><?php echo nl2br(clean_input($review['comment'])); ?></p>
        <?php endif; ?>
        <span class="review-date"><?php echo $review['created_at']; ?></span>
    </div>
    <?php
}

function render_reviews_section($conn, $product_id)
{
    $reviewModel = new Review();
    $reviews = $reviewModel->getForProduct($product_id);
    $stats = $reviewModel->getAverageForProduct($product_id);
    $count = $stats['count'];
    $average = $stats['average'];

    $already_reviewed = !empty($_SESSION['customer_id'])
        && $reviewModel->hasReviewed($product_id, (int) $_SESSION['customer_id']);
    ?>
    <section class="reviews-section">
        <h2>نظرات و امتیاز مشتریان</h2>

        <div class="reviews-summary">
            <?php echo render_stars($average); ?>
            <span id="reviewsAverageText">
                <?php echo $count > 0 ? $average . ' از ۵ (' . $count . ' نظر)' : 'هنوز نظری ثبت نشده'; ?>
            </span>
        </div>

        <div id="reviewsList">
            <?php foreach ($reviews as $review): render_review_item($review); endforeach; ?>
        </div>

        <?php if (empty($_SESSION['customer_id'])): ?>
            <p class="review-login-note">برای ثبت نظر، ابتدا <a href="<?php echo url('login.php'); ?>">وارد حساب کاربری</a> شو.</p>
        <?php elseif ($already_reviewed): ?>
            <p class="review-login-note">شما قبلاً برای این محصول نظر ثبت کرده‌اید.</p>
        <?php else: ?>
            <form id="reviewForm" class="box" data-product-id="<?php echo $product_id; ?>" style="margin-top:16px;">
                <?php echo csrf_field(); ?>

                <label>امتیاز شما</label>
                <div class="star-picker" id="starPicker">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="star-picker-btn" data-value="<?php echo $i; ?>"><?php echo icon('star', 'icon star-icon'); ?></button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="0">

                <label for="reviewComment">نظر شما (اختیاری)</label>
                <textarea id="reviewComment" name="comment" rows="3"></textarea>

                <div id="reviewFormError" class="alert alert-error" style="display:none; margin-top:10px;"></div>

                <button type="submit" class="btn" style="margin-top:14px;">ثبت نظر</button>
            </form>
        <?php endif; ?>
    </section>
    <?php
}

function upload_product_image($file)
{
    if (empty($file['name'])) {
        return null;
    }

    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_ext)) {
        return false;
    }

    $new_name = uniqid('product_') . '.' . $ext;
    $destination = __DIR__ . '/../uploads/products/' . $new_name;

    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return $new_name;
    }

    return false;
}
