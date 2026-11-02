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

// توکن ارسالی (از فیلد فرم یا هدر X-CSRF-Token درخواست‌های Ajax) با توکن سشن مقایسه می‌شه
function csrf_is_valid(string $sent): bool
{
    $real = $_SESSION['csrf_token'] ?? '';

    // hash_equals جلوی Timing Attack رو می‌گیره (مقایسه رشته‌ها به یه زمان ثابت انجام می‌شه)
    return $sent !== '' && $real !== '' && hash_equals($real, $sent);
}

// موقع پردازش POST صداش می‌زنیم؛ اگه توکن غلط یا نبود، درخواست رو رد می‌کنه
function verify_csrf_token()
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    if (!csrf_is_valid((string) $sent)) {
        http_response_code(403);
        die('درخواست نامعتبر است. لطفاً صفحه را رفرش کرده و دوباره تلاش کنید.');
    }
}

// ورود مشتری: شناسه سشن عوض می‌شه (جلوگیری از Session Fixation) و سبد/علاقه‌مندی‌های مهمان تو حسابش ادغام می‌شن
function login_customer(array $customer): void
{
    regenerate_session();

    $_SESSION['customer_id'] = (int) $customer['id'];
    $_SESSION['customer_name'] = $customer['name'];

    Cart::mergeGuestCart((int) $customer['id']);
    Wishlist::mergeGuestList((int) $customer['id']);
}

// خروج مشتری: سبد و علاقه‌مندی‌ها تو دیتابیس می‌مونن، فقط نسخه‌ی داخل سشن پاک می‌شه
function logout_customer(): void
{
    unset($_SESSION['customer_id'], $_SESSION['customer_name']);
    $_SESSION['cart'] = [];
    $_SESSION['wishlist'] = [];

    regenerate_session();
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

// برچسب وضعیت موجودی: [متن، کلاس CSS]
function stock_badge(int $stock): array
{
    if ($stock <= 0) {
        return ['ناموجود', 'stock-out'];
    }
    if ($stock <= Product::LOW_STOCK_THRESHOLD) {
        return ['تنها ' . $stock . ' عدد باقی مانده', 'stock-low'];
    }

    return ['موجود در انبار', 'stock-in'];
}

function render_product_card($product)
{
    $image_url = $product['image']
        ? url('uploads/products/' . $product['image'])
        : 'https://via.placeholder.com/220x150/fbe4ee/c2477a?text=Golara';

    $is_wishlisted = (new Wishlist())->has((int) $product['id']);
    $stock = (int) ($product['stock'] ?? 0);
    [$stock_text, $stock_class] = stock_badge($stock);
    ?>
    <div class="product-card<?php echo $stock <= 0 ? ' is-out' : ''; ?>">
        <button type="button" class="wish-btn<?php echo $is_wishlisted ? ' is-active' : ''; ?>" data-product-id="<?php echo $product['id']; ?>" title="افزودن به علاقه‌مندی‌ها"><?php echo icon('heart'); ?></button>
        <img src="<?php echo $image_url; ?>" alt="<?php echo clean_input($product['name']); ?>" loading="lazy">
        <h3><?php echo clean_input($product['name']); ?></h3>
        <p class="price"><?php echo format_price($product['price']); ?></p>
        <?php if ($stock <= Product::LOW_STOCK_THRESHOLD): ?>
            <p class="stock-badge <?php echo $stock_class; ?>"><?php echo $stock_text; ?></p>
        <?php endif; ?>
        <a href="<?php echo url('product.php?id=' . $product['id']); ?>" class="btn">مشاهده</a>
    </div>
    <?php
}

// لینک‌های صفحه‌بندی: «قبلی ۱ ۲ [۳] ۴ ۵ بعدی»؛ بقیه پارامترهای کوئری (فیلتر، جستجو، ...) حفظ می‌شن
function render_pagination(int $page, int $pages, string $baseUrl, array $query = []): void
{
    if ($pages <= 1) {
        return;
    }

    $link = function (int $target) use ($baseUrl, $query) {
        $q = $query;
        unset($q['page']);
        if ($target > 1) {
            $q['page'] = $target;
        }
        $q = array_filter($q, fn($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0' && $v !== false);

        return $baseUrl . ($q ? '?' . http_build_query($q) : '');
    };

    $window = 2;
    $from = max(1, $page - $window);
    $to = min($pages, $page + $window);
    ?>
    <nav class="pagination" aria-label="صفحه‌بندی">
        <?php if ($page > 1): ?>
            <a href="<?php echo clean_input($link($page - 1)); ?>" rel="prev">قبلی</a>
        <?php endif; ?>

        <?php if ($from > 1): ?>
            <a href="<?php echo clean_input($link(1)); ?>">1</a>
            <?php if ($from > 2): ?><span class="gap">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $from; $i <= $to; $i++): ?>
            <?php if ($i === $page): ?>
                <span class="current" aria-current="page"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="<?php echo clean_input($link($i)); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($to < $pages): ?>
            <?php if ($to < $pages - 1): ?><span class="gap">…</span><?php endif; ?>
            <a href="<?php echo clean_input($link($pages)); ?>"><?php echo $pages; ?></a>
        <?php endif; ?>

        <?php if ($page < $pages): ?>
            <a href="<?php echo clean_input($link($page + 1)); ?>" rel="next">بعدی</a>
        <?php endif; ?>
    </nav>
    <?php
}

// جدول سبد خرید؛ هم موقع لود صفحه (cart.php) و هم بعد از هر تغییر Ajax (کلید html در جواب API) از همین استفاده می‌شه
function render_cart_table(array $items, int $total): void
{
    if (empty($items)) {
        ?>
        <p>سبد خرید شما خالی است. <a href="<?php echo url('products.php'); ?>">مشاهده محصولات</a></p>
        <?php
        return;
    }

    $has_problem = false;
    foreach ($items as $item) {
        if (!$item['enough']) {
            $has_problem = true;
        }
    }
    ?>
    <table class="cart-table">
        <thead>
            <tr>
                <th>محصول</th>
                <th>قیمت واحد</th>
                <th>تعداد</th>
                <th>جمع</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php $pid = (int) $item['product']['id']; ?>
                <tr>
                    <td>
                        <a href="<?php echo url('product.php?id=' . $pid); ?>"><?php echo clean_input($item['product']['name']); ?></a>
                        <?php if (!$item['in_stock']): ?>
                            <div class="stock-badge stock-out">این کالا ناموجود شده است</div>
                        <?php elseif (!$item['enough']): ?>
                            <div class="stock-badge stock-low">فقط <?php echo $item['stock']; ?> عدد موجود است</div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo format_price($item['product']['price']); ?></td>
                    <td>
                        <div class="qty-control" data-product-id="<?php echo $pid; ?>">
                            <button type="button" class="qty-btn" data-action="dec" aria-label="کم کردن">−</button>
                            <span class="qty-value"><?php echo $item['qty']; ?></span>
                            <button type="button" class="qty-btn" data-action="inc" aria-label="زیاد کردن" <?php echo $item['qty'] >= $item['stock'] ? 'disabled' : ''; ?>>+</button>
                        </div>
                    </td>
                    <td><?php echo format_price($item['line_total']); ?></td>
                    <td><button type="button" class="link-danger" data-action="remove" data-product-id="<?php echo $pid; ?>">حذف</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <p style="margin-top:16px; font-size:18px;">جمع کل: <strong class="price"><?php echo format_price($total); ?></strong></p>

    <?php if (!empty($_SESSION['customer_id'])): ?>
        <form method="post" action="<?php echo url('payment/request.php'); ?>" style="margin-top:16px;">
            <?php echo csrf_field(); ?>
            <button type="submit" class="btn" <?php echo $has_problem ? 'disabled' : ''; ?>>تسویه حساب و پرداخت</button>
        </form>
    <?php else: ?>
        <p style="margin-top:16px; font-size:14px;">برای تسویه حساب، ابتدا <a href="<?php echo url('login.php'); ?>" style="color:var(--pink-dark);">وارد حساب کاربری</a> شو. سبد فعلی‌ات بعد از ورود حفظ می‌شه.</p>
    <?php endif;
}

// شبکه کارت‌های علاقه‌مندی؛ مثل جدول سبد هم برای لود اولیه و هم برای به‌روزرسانی Ajax
function render_wishlist_grid(array $products): void
{
    if (empty($products)) {
        ?>
        <p style="margin-top:14px;">هنوز محصولی به علاقه‌مندی‌ها اضافه نکردی. <a href="<?php echo url('products.php'); ?>">مشاهده محصولات</a></p>
        <?php
        return;
    }
    ?>
    <p style="margin-top:8px; color:var(--plum-soft); font-size:13px;">با کلیک روی قلب هر محصول می‌تونی از این لیست حذفش کنی.</p>
    <div class="product-grid" style="margin-top:16px;">
        <?php foreach ($products as $product): render_product_card($product); endforeach; ?>
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
