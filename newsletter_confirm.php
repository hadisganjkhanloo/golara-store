<?php
// لینکی که تو ایمیل تأیید خبرنامه هست: عضویت رو فعال می‌کنه

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

$row = (new Newsletter())->confirm((string) ($_GET['token'] ?? ''));

set_seo(['title' => 'تأیید عضویت در خبرنامه', 'noindex' => true]);
require_once __DIR__ . '/includes/header.php';
?>

<h1>تأیید عضویت در خبرنامه</h1>

<?php if ($row): ?>
    <div class="alert alert-success" role="status">عضویتت تأیید شد 🌸 از این به بعد تخفیف‌ها و محصولات جدید رو برات می‌فرستیم. هر ایمیل لینک لغو عضویت داره.</div>
<?php else: ?>
    <div class="alert alert-error" role="alert">این لینک نامعتبر است. دوباره از پایین صفحه اصلی عضو شو.</div>
<?php endif; ?>

<a href="<?php echo url('index.php'); ?>" class="btn">بازگشت به فروشگاه</a>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
