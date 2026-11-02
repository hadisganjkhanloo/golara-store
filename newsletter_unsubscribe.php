<?php
// لینک «لغو عضویت» داخل هر ایمیل خبرنامه.
// با باز کردن لینک (GET) فقط صفحه تأیید نشون داده می‌شه؛ لغو واقعی با زدن دکمه (POST) انجام می‌شه،
// چون بعضی نرم‌افزارهای ایمیل لینک‌ها رو خودکار باز می‌کنن (پیش‌نمایش/آنتی‌ویروس) و نباید کسی ناخواسته لغو بشه.

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$newsletter = new Newsletter();
$row = $newsletter->findByToken($token);
$done = false;

if ($row && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $newsletter->unsubscribe($token);
    $done = true;
}

set_seo(['title' => 'لغو عضویت در خبرنامه', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';
?>

<h1>لغو عضویت در خبرنامه</h1>

<?php if (!$row): ?>
    <div class="alert alert-error" role="alert">این لینک نامعتبر است.</div>
<?php elseif ($done): ?>
    <div class="alert alert-success" role="status">عضویتت لغو شد و دیگر ایمیلی برایت ارسال نمی‌شود.</div>
<?php else: ?>
    <form method="post" action="<?php echo url('newsletter_unsubscribe.php'); ?>" class="box">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="token" value="<?php echo clean_input($token); ?>">
        <p>آیا می‌خواهی ایمیل <strong dir="ltr"><?php echo clean_input($row['email']); ?></strong> از خبرنامه حذف شود؟</p>
        <button type="submit" class="btn btn-danger">بله، لغو عضویت</button>
    </form>
<?php endif; ?>

<a href="<?php echo url('index.php'); ?>" class="btn btn-outline" style="margin-top:14px;">بازگشت به فروشگاه</a>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
