<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

start_secure_session();
require_admin_login();

$couponModel = new Coupon();
$errors = [];
$old = ['code' => '', 'type' => 'percent', 'value' => '', 'min_order' => '0', 'max_uses' => '', 'expires' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    $action = $_POST['action'] ?? '';

    if ($action === 'toggle') {
        $coupon = $couponModel->find((int) ($_POST['id'] ?? 0));
        if ($coupon) {
            $couponModel->setActive((int) $coupon['id'], !(int) $coupon['is_active']);
            $_SESSION['admin_flash'] = ['type' => 'success', 'text' => 'وضعیت کد تخفیف تغییر کرد.'];
        }
        redirect('coupons.php');
    }

    if ($action === 'create') {
        $old = [
            'code' => Coupon::normalize($_POST['code'] ?? ''),
            'type' => ($_POST['type'] ?? '') === 'fixed' ? 'fixed' : 'percent',
            'value' => normalize_digits(trim($_POST['value'] ?? '')),
            'min_order' => normalize_digits(trim($_POST['min_order'] ?? '0')),
            'max_uses' => normalize_digits(trim($_POST['max_uses'] ?? '')),
            'expires' => trim($_POST['expires'] ?? ''),
        ];

        if (!preg_match('/^[A-Z0-9_-]{3,40}$/', $old['code'])) {
            $errors[] = 'کد باید ۳ تا ۴۰ کاراکتر و فقط شامل حروف انگلیسی، عدد، _ و - باشد.';
        } elseif ($couponModel->findByCode($old['code'])) {
            $errors[] = 'این کد قبلاً ساخته شده است.';
        }
        if (!preg_match('/^[0-9]+$/', $old['value']) || (int) $old['value'] < 1) {
            $errors[] = 'مقدار تخفیف باید یک عدد مثبت باشد.';
        } elseif ($old['type'] === 'percent' && (int) $old['value'] > 100) {
            $errors[] = 'تخفیف درصدی نمی‌تواند بیشتر از ۱۰۰ باشد.';
        }
        if (!preg_match('/^[0-9]+$/', $old['min_order'])) {
            $errors[] = 'حداقل خرید باید یک عدد (یا ۰) باشد.';
        }
        if ($old['max_uses'] !== '' && !preg_match('/^[0-9]+$/', $old['max_uses'])) {
            $errors[] = 'حداکثر دفعات استفاده باید عدد باشد (یا خالی = نامحدود).';
        }
        $expires = null;
        if ($old['expires'] !== '') {
            $ts = strtotime($old['expires'] . ' 23:59:59');
            if ($ts === false) {
                $errors[] = 'تاریخ انقضا معتبر نیست (فرمت: 2026-12-31).';
            } else {
                $expires = date('Y-m-d H:i:s', $ts);
            }
        }

        if (empty($errors)) {
            $couponModel->create(
                $old['code'],
                $old['type'],
                (int) $old['value'],
                (int) $old['min_order'],
                $old['max_uses'] === '' ? null : (int) $old['max_uses'],
                $expires
            );
            $_SESSION['admin_flash'] = ['type' => 'success', 'text' => 'کد تخفیف ' . $old['code'] . ' ساخته شد.'];
            redirect('coupons.php');
        }
    }
}

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

require_once __DIR__ . '/_header.php';

$coupons = $couponModel->all('id DESC');
?>

<h1>کدهای تخفیف</h1>

<?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>" role="status"><?php echo clean_input($flash['text']); ?></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?><div><?php echo clean_input($error); ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="coupons.php" class="admin-toolbar">
    <?php echo csrf_field(); ?>
    <input type="hidden" name="action" value="create">
    <div class="filter-field">
        <label for="c_code">کد</label>
        <input type="text" id="c_code" name="code" value="<?php echo clean_input($old['code']); ?>" maxlength="40" required dir="ltr" placeholder="SUMMER20">
    </div>
    <div class="filter-field">
        <label for="c_type">نوع</label>
        <select id="c_type" name="type">
            <option value="percent" <?php echo $old['type'] === 'percent' ? 'selected' : ''; ?>>درصدی</option>
            <option value="fixed" <?php echo $old['type'] === 'fixed' ? 'selected' : ''; ?>>مبلغ ثابت (تومان)</option>
        </select>
    </div>
    <div class="filter-field">
        <label for="c_value">مقدار</label>
        <input type="text" id="c_value" name="value" value="<?php echo clean_input($old['value']); ?>" inputmode="numeric" required>
    </div>
    <div class="filter-field">
        <label for="c_min">حداقل خرید (تومان)</label>
        <input type="text" id="c_min" name="min_order" value="<?php echo clean_input($old['min_order']); ?>" inputmode="numeric">
    </div>
    <div class="filter-field">
        <label for="c_max">حداکثر دفعات (خالی = نامحدود)</label>
        <input type="text" id="c_max" name="max_uses" value="<?php echo clean_input($old['max_uses']); ?>" inputmode="numeric">
    </div>
    <div class="filter-field">
        <label for="c_exp">انقضا (اختیاری، مثل 2026-12-31)</label>
        <input type="text" id="c_exp" name="expires" value="<?php echo clean_input($old['expires']); ?>" dir="ltr">
    </div>
    <div class="filter-actions"><button type="submit" class="btn">ساخت کد</button></div>
</form>

<div class="table-scroll">
<table class="admin-table" style="margin-top:14px;">
    <thead>
        <tr>
            <th scope="col">کد</th>
            <th scope="col">تخفیف</th>
            <th scope="col">حداقل خرید</th>
            <th scope="col">استفاده</th>
            <th scope="col">انقضا</th>
            <th scope="col">وضعیت</th>
            <th scope="col"><span class="sr-only">عملیات</span></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($coupons)): ?>
            <tr><td colspan="7" style="text-align:center;">هنوز کدی ساخته نشده.</td></tr>
        <?php endif; ?>
        <?php foreach ($coupons as $c): ?>
            <?php $expired = $c['expires_at'] !== null && strtotime($c['expires_at']) < time(); ?>
            <tr>
                <td dir="ltr"><strong><?php echo clean_input($c['code']); ?></strong></td>
                <td><?php echo $c['type'] === 'percent' ? (int) $c['value'] . '٪' : format_price($c['value']); ?></td>
                <td><?php echo format_price($c['min_order']); ?></td>
                <td><?php echo (int) $c['used_count']; ?><?php echo $c['max_uses'] !== null ? ' از ' . (int) $c['max_uses'] : ''; ?></td>
                <td><?php echo $c['expires_at'] ? format_date($c['expires_at']) : '—'; ?></td>
                <td>
                    <?php if ($expired): ?><span class="badge badge-off">منقضی</span>
                    <?php elseif ((int) $c['is_active']): ?><span class="badge badge-ok">فعال</span>
                    <?php else: ?><span class="badge badge-off">غیرفعال</span><?php endif; ?>
                </td>
                <td>
                    <form method="post" action="coupons.php" class="inline-form">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo $c['id']; ?>">
                        <button type="submit" class="btn btn-outline"><?php echo (int) $c['is_active'] ? 'غیرفعال کن' : 'فعال کن'; ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
</div>

<?php require_once __DIR__ . '/_footer.php'; ?>
