<?php

/**
 * خبرنامه با تأیید دومرحله‌ای (double opt-in) و لینک لغو عضویت:
 *  ۱) کاربر ایمیلش رو وارد می‌کنه  → ردیف با token ساخته می‌شه (هنوز تأییدنشده)
 *  ۲) لینک تأیید به ایمیلش می‌ره     → با کلیک، confirmed_at پر می‌شه
 *  ۳) هر ایمیل خبرنامه لینک لغو عضویت داره → unsubscribed_at پر می‌شه
 * چون بدون تأیید، هرکسی می‌تونست ایمیل یه نفر دیگه رو تو لیست بذاره.
 */
class Newsletter extends Model
{
    protected static string $table = 'newsletter';

    public const PENDING = 'pending';           // ایمیل تأیید ارسال بشه
    public const ALREADY = 'already_confirmed'; // از قبل فعاله

    public function findByEmail(string $email): ?array
    {
        $stmt = mysqli_prepare($this->conn, "SELECT * FROM newsletter WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    public function findByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) {
            return null;
        }

        $stmt = mysqli_prepare($this->conn, "SELECT * FROM newsletter WHERE token = ?");
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    /**
     * @return array{status:string, token:?string} status = PENDING (باید ایمیل تأیید بره) یا ALREADY
     */
    public function subscribe(string $email): array
    {
        $existing = $this->findByEmail($email);

        if ($existing && $existing['confirmed_at'] !== null && $existing['unsubscribed_at'] === null) {
            return ['status' => self::ALREADY, 'token' => null];
        }

        $token = bin2hex(random_bytes(20));

        if ($existing) {
            // تأییدنشده یا لغوشده بود: توکن تازه می‌گیره و دوباره منتظر تأیید می‌مونه
            $stmt = mysqli_prepare(
                $this->conn,
                "UPDATE newsletter SET token = ?, confirmed_at = NULL, unsubscribed_at = NULL WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'si', $token, $existing['id']);
        } else {
            $stmt = mysqli_prepare($this->conn, "INSERT INTO newsletter (email, token) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ss', $email, $token);
        }
        mysqli_stmt_execute($stmt);

        return ['status' => self::PENDING, 'token' => $token];
    }

    public function confirm(string $token): ?array
    {
        $row = $this->findByToken($token);
        if (!$row) {
            return null;
        }

        $stmt = mysqli_prepare($this->conn, "UPDATE newsletter SET confirmed_at = COALESCE(confirmed_at, NOW()), unsubscribed_at = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $row['id']);
        mysqli_stmt_execute($stmt);

        return $row;
    }

    public function unsubscribe(string $token): ?array
    {
        $row = $this->findByToken($token);
        if (!$row) {
            return null;
        }

        $stmt = mysqli_prepare($this->conn, "UPDATE newsletter SET unsubscribed_at = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $row['id']);
        mysqli_stmt_execute($stmt);

        return $row;
    }

    // ایمیل تأیید رو می‌فرسته (تو حالت log تو storage/mail.log نوشته می‌شه)
    public static function sendConfirmation(string $email, string $token): bool
    {
        $link = absolute_url('newsletter_confirm.php?token=' . $token);

        return Mailer::send(
            $email,
            'تأیید عضویت در خبرنامه ' . SHOP_NAME,
            "سلام!\n\nبرای فعال‌شدن عضویتت در خبرنامه " . SHOP_NAME . " روی لینک زیر بزن:\n$link\n\n" .
            "اگه تو این درخواست رو ندادی، این ایمیل رو نادیده بگیر؛ بدون تأیید، هیچ ایمیلی برات فرستاده نمی‌شه."
        );
    }
}
