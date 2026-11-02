<?php
// تبدیل تاریخ میلادی به شمسی (جلالی)؛ همه تاریخ‌های نمایش‌داده‌شده به کاربر از اینجا رد می‌شن.
// الگوریتم استاندارد و بدون وابستگی (ext-intl لازم نیست).

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];

    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return [$jy, $jm, $jd];
}

// «۱۴۰۵/۰۸/۱۱» یا با ساعت «۱۴۰۵/۰۸/۱۱ ۱۲:۰۵». ورودی: رشته‌ی تاریخ دیتابیس یا timestamp
function format_date($value, bool $withTime = false): string
{
    if ($value === null || $value === '') {
        return '';
    }

    $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
    if ($timestamp === false) {
        return '';
    }

    [$jy, $jm, $jd] = gregorian_to_jalali((int) date('Y', $timestamp), (int) date('n', $timestamp), (int) date('j', $timestamp));

    $text = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);

    return $withTime ? $text . ' ' . date('H:i', $timestamp) : $text;
}
