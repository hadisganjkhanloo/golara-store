<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>درس Ajax - شمارنده کلیک</title>
    <style>
        body { font-family: Tahoma, sans-serif; text-align: center; padding: 60px; background: #fff8fb; }
        #count { font-size: 60px; color: #c1536e; font-weight: bold; }
        button { font-size: 18px; padding: 10px 24px; border-radius: 999px; border: none; background: #e588a3; color: #fff; cursor: pointer; }
    </style>
</head>
<body>

<h1>تعداد کلیک (بدون رفرش صفحه)</h1>
<div id="count">0</div>
<button id="clickBtn">بزن!</button>

<script>
// مرحله ۱: دکمه رو پیدا می‌کنیم
const btn = document.getElementById('clickBtn');
const countBox = document.getElementById('count');

// مرحله ۲: وقتی کلیک شد، یه تابع اجرا بشه
btn.addEventListener('click', function () {

    // مرحله ۳: به فایل counter.php درخواست می‌فرستیم (بدون رفرش صفحه!)
    fetch('counter.php')
        .then(function (response) {
            return response.text(); // جواب رو به صورت متن ساده می‌خونیم
        })
        .then(function (data) {
            // مرحله ۴: عدد جدید رو تو صفحه جایگزین می‌کنیم
            countBox.textContent = data;
        });
});
</script>

</body>
</html>
