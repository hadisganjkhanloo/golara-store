<?php
// توابع کمکی پروژه

require_once __DIR__ . '/jalali.php';
require_once __DIR__ . '/images.php';

function clean_input($data)
{
    $data = trim((string) $data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// ارقام فارسی و عربی (۱۲۳ ٤٥٦) رو به لاتین تبدیل می‌کنه تا اعتبارسنجی شماره موبایل، کدپستی و ... با ورودی فارسی هم کار کنه
function normalize_digits(string $text): string
{
    return strtr($text, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function is_valid_email($email)
{
    return preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

function is_valid_phone($phone)
{
    // شماره موبایل ایران، مثلا 09123456789
    return preg_match('/^09[0-9]{9}$/', normalize_digits((string) $phone));
}

// سیاست رمز عبور: حداقل ۸ کاراکتر، حداقل یک حرف و یک عدد، و نه یکی از رمزهای خیلی رایج. null = معتبر
function password_policy_error(string $password): ?string
{
    if (mb_strlen($password) < 8) {
        return 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    }
    if (!preg_match('/\p{L}/u', $password) || !preg_match('/\d/', $password)) {
        return 'رمز عبور باید هم حرف و هم عدد داشته باشد.';
    }
    if (in_array(strtolower($password), ['password1', 'password123', 'qwerty123', 'admin123', 'admin1234', '12345678a', 'a12345678', 'golara123'], true)) {
        return 'این رمز خیلی رایج است؛ یک رمز دیگر انتخاب کنید.';
    }

    return null;
}

function format_price($price)
{
    return number_format((int) $price) . ' تومان';
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function require_admin_login()
{
    if (empty($_SESSION['admin_id'])) {
        redirect(url('admin/login.php'));
    }

    // ادمینی که هنوز رمز پیش‌فرض/موقت داره، تا رمزش رو عوض نکنه به هیچ صفحه‌ی دیگه‌ای دسترسی نداره
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!empty($_SESSION['admin_must_change_password']) && !in_array($current, ['change_password.php', 'logout.php'], true)) {
        redirect(url('admin/change_password.php'));
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
    unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['coupon_code']);
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

    // آیکون‌ها تزئینی‌ان؛ اسم دکمه/لینک از متن یا aria-label خودش میاد، پس screen reader نباید آیکون رو بخونه
    return preg_replace('/<svg /', '<svg class="' . $class . '" aria-hidden="true" focusable="false" ', $svg, 1);
}

// ---------- سئو (عنوان، توضیحات، canonical، Open Graph، JSON-LD) ----------
// هر صفحه قبل از include کردن header.php با set_seo([...]) اطلاعات خودش رو می‌ذاره؛ header.php همه رو چاپ می‌کنه.
// کلیدها: title, description, image (آدرس کامل)، canonical (آدرس کامل)، noindex (bool), type (website|product), jsonld (آرایه‌ی آرایه‌ها)

function set_seo(array $data): void
{
    $GLOBALS['__seo'] = array_merge($GLOBALS['__seo'] ?? [], $data);
}

function get_seo(): array
{
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (BASE_PATH !== '' && str_starts_with($path, BASE_PATH)) {
        $path = substr($path, strlen(BASE_PATH));
    }

    return array_merge([
        'title' => '',
        'description' => 'فروشگاه اینترنتی گل‌آرا؛ لوازم آرایشی، مراقبت پوست و عطر اورجینال با ضمانت اصالت کالا و ارسال سریع به سراسر کشور.',
        'image' => absolute_url('assets/img/og-image.png'),
        'canonical' => absolute_url(ltrim($path, '/')),
        'noindex' => false,
        'type' => 'website',
        'jsonld' => [],
    ], $GLOBALS['__seo'] ?? []);
}

// مسیر ناوبری (breadcrumb): [['خانه', url], ['دسته', url], ['محصول']]؛ آخری لینک نداره
function render_breadcrumbs(array $items): void
{
    $position = 0;
    $list = [];
    foreach ($items as $item) {
        $position++;
        $entry = ['@type' => 'ListItem', 'position' => $position, 'name' => $item[0]];
        if (!empty($item[1])) {
            $entry['item'] = absolute_url(ltrim(substr($item[1], strlen(BASE_PATH)), '/'));
        }
        $list[] = $entry;
    }

    set_seo(['jsonld' => array_merge(get_seo()['jsonld'], [[
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $list,
    ]])]);
    ?>
    <nav class="breadcrumbs" aria-label="مسیر صفحه">
        <ol>
            <?php foreach ($items as $i => $item): ?>
                <li>
                    <?php if (!empty($item[1]) && $i < count($items) - 1): ?>
                        <a href="<?php echo clean_input($item[1]); ?>"><?php echo clean_input($item[0]); ?></a>
                    <?php else: ?>
                        <span aria-current="page"><?php echo clean_input($item[0]); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php
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
    $image = product_image($product['image'] ?? null, 480);
    $name = clean_input($product['name']);
    $link = url('product.php?id=' . $product['id']);

    $is_wishlisted = (new Wishlist())->has((int) $product['id']);
    $stock = (int) ($product['stock'] ?? 0);
    [$stock_text, $stock_class] = stock_badge($stock);

    $rating_count = (int) ($product['rating_count'] ?? 0);
    $rating_avg = round((float) ($product['rating_avg'] ?? 0), 1);
    ?>
    <div class="product-card<?php echo $stock <= 0 ? ' is-out' : ''; ?>">
        <button type="button" class="wish-btn<?php echo $is_wishlisted ? ' is-active' : ''; ?>" data-product-id="<?php echo $product['id']; ?>"
                aria-label="علاقه‌مندی: <?php echo $name; ?>" aria-pressed="<?php echo $is_wishlisted ? 'true' : 'false'; ?>" title="افزودن به علاقه‌مندی‌ها"><?php echo icon('heart'); ?></button>
        <a href="<?php echo $link; ?>" class="card-media" tabindex="-1" aria-hidden="true">
            <img src="<?php echo $image['url']; ?>" width="<?php echo $image['width']; ?>" height="<?php echo $image['height']; ?>" alt="" loading="lazy" decoding="async">
        </a>
        <h3><a href="<?php echo $link; ?>"><?php echo $name; ?></a></h3>
        <?php if ($rating_count > 0): ?>
            <p class="card-rating"><?php echo render_stars($rating_avg, 'stars stars-sm'); ?> <span><?php echo $rating_avg; ?> (<?php echo $rating_count; ?>)</span></p>
        <?php endif; ?>
        <p class="price"><?php echo format_price($product['price']); ?></p>
        <?php if ($stock <= Product::LOW_STOCK_THRESHOLD): ?>
            <p class="stock-badge <?php echo $stock_class; ?>"><?php echo $stock_text; ?></p>
        <?php endif; ?>
        <div class="card-actions">
            <?php if ($stock > 0): ?>
                <button type="button" class="btn js-add-to-cart" data-product-id="<?php echo $product['id']; ?>">افزودن به سبد</button>
            <?php else: ?>
                <span class="btn btn-disabled" aria-disabled="true">ناموجود</span>
            <?php endif; ?>
            <a href="<?php echo $link; ?>" class="btn btn-outline" aria-label="مشاهده <?php echo $name; ?>">مشاهده</a>
        </div>
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

// خلاصه مبلغ: جمع کالاها، تخفیف، هزینه ارسال، مبلغ قابل پرداخت (هم تو سبد، هم تو تسویه حساب)
function render_price_summary(array $summary): void
{
    ?>
    <dl class="price-summary">
        <div><dt>جمع کالاها</dt><dd><?php echo format_price($summary['subtotal']); ?></dd></div>
        <?php if ($summary['discount'] > 0): ?>
            <div class="is-discount"><dt>تخفیف (<?php echo clean_input($summary['coupon']); ?>)</dt><dd>− <?php echo format_price($summary['discount']); ?></dd></div>
        <?php endif; ?>
        <div><dt>هزینه ارسال</dt><dd><?php echo $summary['shipping'] > 0 ? format_price($summary['shipping']) : 'رایگان'; ?></dd></div>
        <div class="is-total"><dt>مبلغ قابل پرداخت</dt><dd class="price"><?php echo format_price($summary['total']); ?></dd></div>
    </dl>
    <?php if ($summary['shipping'] > 0 && $summary['free_shipping_remaining'] > 0): ?>
        <p class="free-shipping-hint">با <?php echo format_price($summary['free_shipping_remaining']); ?> خرید بیشتر، ارسال رایگان می‌شود.</p>
    <?php endif;
}

// جدول سبد خرید؛ هم موقع لود صفحه (cart.php) و هم بعد از هر تغییر Ajax (کلید html در جواب API) از همین استفاده می‌شه
function render_cart_table(array $items, array $summary): void
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
    <div class="table-scroll">
    <table class="cart-table">
        <thead>
            <tr>
                <th scope="col">محصول</th>
                <th scope="col">قیمت واحد</th>
                <th scope="col">تعداد</th>
                <th scope="col">جمع</th>
                <th scope="col"><span class="sr-only">حذف</span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <?php $pid = (int) $item['product']['id']; $pname = clean_input($item['product']['name']); ?>
                <tr>
                    <td>
                        <a href="<?php echo url('product.php?id=' . $pid); ?>"><?php echo $pname; ?></a>
                        <?php if (!$item['in_stock']): ?>
                            <div class="stock-badge stock-out">این کالا ناموجود شده است</div>
                        <?php elseif (!$item['enough']): ?>
                            <div class="stock-badge stock-low">فقط <?php echo $item['stock']; ?> عدد موجود است</div>
                        <?php endif; ?>
                    </td>
                    <td><?php echo format_price($item['product']['price']); ?></td>
                    <td>
                        <div class="qty-control" data-product-id="<?php echo $pid; ?>" role="group" aria-label="تعداد <?php echo $pname; ?>">
                            <button type="button" class="qty-btn" data-action="dec" aria-label="کم کردن تعداد <?php echo $pname; ?>">−</button>
                            <span class="qty-value" aria-live="polite"><?php echo $item['qty']; ?></span>
                            <button type="button" class="qty-btn" data-action="inc" aria-label="زیاد کردن تعداد <?php echo $pname; ?>" <?php echo ($item['qty'] >= $item['stock'] || $item['qty'] >= MAX_QTY_PER_ITEM) ? 'disabled' : ''; ?>>+</button>
                        </div>
                    </td>
                    <td><?php echo format_price($item['line_total']); ?></td>
                    <td><button type="button" class="link-danger" data-action="remove" data-product-id="<?php echo $pid; ?>" aria-label="حذف <?php echo $pname; ?> از سبد">حذف</button></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <div class="cart-footer">
        <div class="coupon-box">
            <?php if (!empty($summary['coupon'])): ?>
                <p class="coupon-applied">کد تخفیف <strong><?php echo clean_input($summary['coupon']); ?></strong> اعمال شد.
                    <button type="button" class="link-danger" data-action="remove-coupon">حذف کد</button></p>
            <?php else: ?>
                <form class="coupon-form" data-coupon-form>
                    <label for="couponCode" class="sr-only">کد تخفیف</label>
                    <input type="text" id="couponCode" name="code" placeholder="کد تخفیف" autocomplete="off" maxlength="40" dir="ltr">
                    <button type="submit" class="btn btn-outline">اعمال</button>
                </form>
            <?php endif; ?>
            <?php if (!empty($summary['coupon_error'])): ?>
                <p class="coupon-error" role="alert"><?php echo clean_input($summary['coupon_error']); ?></p>
            <?php endif; ?>
        </div>

        <div class="summary-box">
            <?php render_price_summary($summary); ?>

            <?php if ($has_problem): ?>
                <span class="btn btn-disabled" aria-disabled="true">ابتدا موجودی کالاها را اصلاح کنید</span>
            <?php else: ?>
                <a href="<?php echo url('checkout.php'); ?>" class="btn">ادامه و ثبت اطلاعات ارسال</a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($_SESSION['customer_id'])): ?>
        <p style="margin-top:16px; font-size:14px;">برای ادامه خرید باید <a href="<?php echo url('login.php'); ?>" style="color:var(--pink-dark);">وارد حساب کاربری</a> شوی. سبد فعلی‌ات بعد از ورود حفظ می‌شه.</p>
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
    $rounded = round($rating);
    $html = '<span class="' . $class . '" role="img" aria-label="امتیاز ' . round((float) $rating, 1) . ' از 5">';
    for ($i = 1; $i <= 5; $i++) {
        $filled = $i <= $rounded ? ' is-filled' : '';
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
        <span class="review-date"><?php echo format_date($review['created_at'], true); ?></span>
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
    <section class="reviews-section" aria-labelledby="reviewsTitle">
        <h2 id="reviewsTitle">نظرات و امتیاز مشتریان</h2>

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

                <span class="field-label" id="ratingLabel">امتیاز شما</span>
                <div class="star-picker" id="starPicker" role="group" aria-labelledby="ratingLabel">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <button type="button" class="star-picker-btn" data-value="<?php echo $i; ?>" aria-label="<?php echo $i; ?> ستاره" aria-pressed="false"><?php echo icon('star', 'icon star-icon'); ?></button>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingInput" value="0">

                <label for="reviewComment">نظر شما (اختیاری)</label>
                <textarea id="reviewComment" name="comment" rows="3"></textarea>

                <div id="reviewFormError" class="alert alert-error" role="alert" style="display:none; margin-top:10px;"></div>

                <button type="submit" class="btn" style="margin-top:14px;">ثبت نظر</button>
            </form>
        <?php endif; ?>
    </section>
    <?php
}
