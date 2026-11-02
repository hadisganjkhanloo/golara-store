<?php
// آپلود امن عکس محصول و ساخت نسخه‌های کوچک (thumbnail)

const PRODUCT_IMAGE_MAX_BYTES = 2 * 1024 * 1024;   // ۲ مگابایت
const PRODUCT_IMAGE_MIN_SIDE = 200;
const PRODUCT_IMAGE_MAX_SIDE = 6000;
// نسبت ثابت ۳ به ۲ برای همه نسخه‌ها؛ با width/height روی <img> جلوی پریدن صفحه موقع لود (CLS) رو می‌گیره
const PRODUCT_IMAGE_RATIO = 2 / 3;

function product_image_dir(): string
{
    return dirname(__DIR__) . '/uploads/products';
}

/**
 * عکس آپلودشده رو چک و ذخیره می‌کنه. به پسوند اسم فایل اعتماد نمی‌کنه:
 *  - محتوای واقعی با finfo و getimagesize بررسی می‌شه (فقط JPEG/PNG/WebP)
 *  - حجم و ابعاد محدوده
 *  - فایل با GD دوباره ساخته و ذخیره می‌شه؛ هر چیزی غیر از پیکسل‌ها (متادیتا، کد اضافه) حذف می‌شه
 *  - اسم فایل تصادفیه و پسوندش از نوع واقعی فایل میاد، نه از اسمی که کاربر فرستاده
 *
 * @return array{ok:bool, name:?string, error:?string}
 */
function store_product_image(array $file): array
{
    if (empty($file['name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'name' => null, 'error' => null];
    }

    $fail = fn(string $message) => ['ok' => false, 'name' => null, 'error' => $message];

    if (($file['error'] ?? 0) !== UPLOAD_ERR_OK) {
        return $fail($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'حجم عکس بیشتر از حد مجاز است.'
            : 'آپلود عکس ناموفق بود، دوباره تلاش کنید.');
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return $fail('فایل آپلودشده معتبر نیست.');
    }

    if ($file['size'] > PRODUCT_IMAGE_MAX_BYTES) {
        return $fail('حجم عکس باید کمتر از ۲ مگابایت باشد.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $loaders = [
        'image/jpeg' => ['imagecreatefromjpeg', 'jpg'],
        'image/png' => ['imagecreatefrompng', 'png'],
        'image/webp' => ['imagecreatefromwebp', 'webp'],
    ];

    $info = @getimagesize($file['tmp_name']);
    if (!isset($loaders[$mime]) || $info === false || $info['mime'] !== $mime) {
        return $fail('فقط عکس JPEG، PNG یا WebP مجاز است.');
    }

    [$width, $height] = $info;
    if (min($width, $height) < PRODUCT_IMAGE_MIN_SIDE || max($width, $height) > PRODUCT_IMAGE_MAX_SIDE) {
        return $fail('ابعاد عکس باید بین ' . PRODUCT_IMAGE_MIN_SIDE . ' تا ' . PRODUCT_IMAGE_MAX_SIDE . ' پیکسل باشد.');
    }

    [$loader, $ext] = $loaders[$mime];
    $image = @$loader($file['tmp_name']);
    if (!$image) {
        return $fail('فایل عکس خراب است.');
    }

    // عکس‌های خیلی بزرگ به عرض ۱۴۰۰ کوچک می‌شن
    if ($width > 1400) {
        $scaled = imagescale($image, 1400);
        if ($scaled) {
            imagedestroy($image);
            $image = $scaled;
        }
    }

    $dir = product_image_dir();
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $name = 'product_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $path = $dir . '/' . $name;

    if ($ext === 'png') {
        imagesavealpha($image, true);
        $saved = imagepng($image, $path, 6);
    } elseif ($ext === 'webp') {
        $saved = imagewebp($image, $path, 85);
    } else {
        $saved = imagejpeg($image, $path, 85);
    }
    imagedestroy($image);

    return $saved ? ['ok' => true, 'name' => $name, 'error' => null] : $fail('ذخیره عکس ناموفق بود.');
}

// عکس اصلی و همه thumbnailهاش رو پاک می‌کنه (فقط اگه محصول دیگه‌ای ازش استفاده نکنه)
function delete_product_image(?string $name): void
{
    if (!$name || !preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
        return;
    }

    $stmt = mysqli_prepare(Database::getConnection(), "SELECT COUNT(*) AS c FROM products WHERE image = ?");
    mysqli_stmt_bind_param($stmt, 's', $name);
    mysqli_stmt_execute($stmt);
    if ((int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'] > 0) {
        return;
    }

    $dir = product_image_dir();
    @unlink($dir . '/' . $name);
    foreach (glob($dir . '/thumbs/*/' . pathinfo($name, PATHINFO_FILENAME) . '.webp') ?: [] as $thumb) {
        @unlink($thumb);
    }
}

/**
 * آدرس نسخه‌ی کوچک‌شده‌ی عکس (برش وسطِ ۳ به ۲، WebP) که بار اول ساخته و بعد کش می‌شه.
 * اگه GD/WebP نبود یا فایل اصلی پیدا نشد، همون عکس اصلی (یا عکس جایگزین) برمی‌گرده.
 *
 * @return array{url:string, width:int, height:int}
 */
function product_image(?string $name, int $width = 480): array
{
    $height = (int) round($width * PRODUCT_IMAGE_RATIO);
    $placeholder = ['url' => url('assets/img/placeholder.svg'), 'width' => $width, 'height' => $height];

    if (!$name || !preg_match('/^[A-Za-z0-9_.-]+$/', $name)) {
        return $placeholder;
    }

    $source = product_image_dir() . '/' . $name;
    if (!is_file($source)) {
        return $placeholder;
    }

    $original = ['url' => url('uploads/products/' . $name), 'width' => $width, 'height' => $height];

    if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
        return $original;
    }

    $thumbRelative = 'thumbs/' . $width . '/' . pathinfo($name, PATHINFO_FILENAME) . '.webp';
    $thumbPath = product_image_dir() . '/' . $thumbRelative;

    if (!is_file($thumbPath) || filemtime($thumbPath) < filemtime($source)) {
        if (!make_cover_thumbnail($source, $thumbPath, $width, $height)) {
            return $original;
        }
    }

    return ['url' => url('uploads/products/' . $thumbRelative), 'width' => $width, 'height' => $height];
}

function make_cover_thumbnail(string $source, string $target, int $width, int $height): bool
{
    $info = @getimagesize($source);
    if ($info === false) {
        return false;
    }

    $loader = ['image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng', 'image/webp' => 'imagecreatefromwebp'][$info['mime']] ?? null;
    $image = $loader ? @$loader($source) : false;
    if (!$image) {
        return false;
    }

    [$srcW, $srcH] = $info;

    // «cover»: بخش وسط عکس با نسبت هدف بریده می‌شه
    $targetRatio = $width / $height;
    if ($srcW / $srcH > $targetRatio) {
        $cropH = $srcH;
        $cropW = (int) round($srcH * $targetRatio);
    } else {
        $cropW = $srcW;
        $cropH = (int) round($srcW / $targetRatio);
    }
    $cropX = intdiv($srcW - $cropW, 2);
    $cropY = intdiv($srcH - $cropH, 2);

    $thumb = imagecreatetruecolor($width, $height);
    imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
    imagecopyresampled($thumb, $image, 0, 0, $cropX, $cropY, $width, $height, $cropW, $cropH);
    imagedestroy($image);

    $dir = dirname($target);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $ok = imagewebp($thumb, $target, 82);
    imagedestroy($thumb);

    return $ok;
}
