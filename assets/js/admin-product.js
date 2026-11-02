// فرم افزودن محصول در پنل مدیریت: پیش‌نمایش عکس + ارسال با Ajax

(function () {
    'use strict';

    const form = document.getElementById('addProductForm');
    if (!form) {
        return;
    }

    // پیش‌نمایش آنی عکس، قبل از هر آپلودی - فقط تو خود مرورگر
    document.getElementById('image').addEventListener('change', function () {
        const file = this.files[0];
        const preview = document.getElementById('imagePreview');

        if (!file) {
            preview.hidden = true;
            return;
        }

        const reader = new FileReader();
        reader.onload = function (event) {
            preview.src = event.target.result;
            preview.hidden = false;
        };
        reader.readAsDataURL(file);
    });

    // ارسال کل فرم (همراه با فایل) با Ajax به API، به‌جای رفرش کامل صفحه
    form.addEventListener('submit', async function (event) {
        event.preventDefault();

        const submitBtn = document.getElementById('submitBtn');
        const alertsBox = document.getElementById('formAlerts');

        submitBtn.disabled = true;
        submitBtn.textContent = 'در حال ذخیره...';

        // FormData خودش فایل‌ها رو هم از تو فرم جمع می‌کنه؛ نیازی نیست دستی اضافه کنیم
        const result = await Golara.api('POST', '/admin/products', new FormData(form));

        submitBtn.disabled = false;
        submitBtn.textContent = 'ذخیره محصول';

        alertsBox.textContent = '';
        const box = document.createElement('div');

        if (!result.ok) {
            box.className = 'alert alert-error';
            box.setAttribute('role', 'alert');
            const messages = Object.values(result.errors);
            (messages.length ? messages : [result.message]).forEach(function (text) {
                const line = document.createElement('div');
                line.textContent = text;
                box.appendChild(line);
            });
            alertsBox.appendChild(box);
            return;
        }

        box.className = 'alert alert-success';
        box.textContent = result.message + ' می‌تونی محصول بعدی رو هم اضافه کنی.';
        alertsBox.appendChild(box);

        // فرم رو برای افزودن محصول بعدی خالی می‌کنیم (بدون نیاز به رفرش صفحه)
        form.reset();
        document.getElementById('imagePreview').hidden = true;
    });
})();
