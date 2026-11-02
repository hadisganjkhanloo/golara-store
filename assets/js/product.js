// اسکریپت صفحه محصول: افزودن به سبد، انتخاب ستاره، ثبت نظر
// (جدا از HTML نوشته شده تا سیاست امنیتی CSP هیچ اسکریپت inline ای لازم نداشته باشه)

(function () {
    'use strict';

    // ---------- افزودن به سبد ----------
    const addToCartBtn = document.getElementById('addToCartBtn');
    if (addToCartBtn) {
        addToCartBtn.addEventListener('click', async function () {
            const btn = this;
            const message = document.getElementById('addToCartMessage');
            const qtyInput = document.getElementById('addToCartQty');
            const originalText = btn.textContent;

            // Loading state: تا جواب نیومده، دکمه غیرفعال بشه و متنش عوض بشه
            btn.disabled = true;
            btn.textContent = 'در حال افزودن...';

            const result = await Golara.addToCart(btn.dataset.productId, parseInt(qtyInput.value, 10) || 1);

            // چه موفق چه ناموفق، دکمه رو به حالت اول برگردون
            btn.disabled = false;
            btn.textContent = originalText;

            message.className = 'action-message ' + (result.ok ? 'is-success' : 'is-error');
            message.textContent = result.ok
                ? 'به سبد خرید اضافه شد ✓ (در سبد: ' + result.data.quantity + ' عدد)'
                : result.message;
            setTimeout(function () { message.textContent = ''; }, 3500);
        });
    }

    // ---------- ستاره‌های انتخاب امتیاز ----------
    const starPicker = document.getElementById('starPicker');
    if (starPicker) {
        const starButtons = starPicker.querySelectorAll('.star-picker-btn');
        const ratingInput = document.getElementById('ratingInput');

        starButtons.forEach(function (starBtn) {
            starBtn.addEventListener('click', function () {
                const value = parseInt(starBtn.dataset.value, 10);
                ratingInput.value = value;

                starButtons.forEach(function (b) {
                    const isFilled = parseInt(b.dataset.value, 10) <= value;
                    b.querySelector('svg').classList.toggle('is-filled', isFilled);
                    b.setAttribute('aria-pressed', parseInt(b.dataset.value, 10) === value ? 'true' : 'false');
                });
            });
        });
    }

    // ---------- ارسال فرم نظر با Ajax ----------
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm) {
        reviewForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const productId = reviewForm.dataset.productId;
            const rating = document.getElementById('ratingInput').value;
            const comment = document.getElementById('reviewComment').value;
            const errorBox = document.getElementById('reviewFormError');

            if (rating === '0') {
                errorBox.textContent = 'لطفاً یک امتیاز انتخاب کنید.';
                errorBox.style.display = 'block';
                return;
            }

            const submitBtn = reviewForm.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.textContent = 'در حال ارسال...';

            const result = await Golara.api('POST', '/products/' + encodeURIComponent(productId) + '/reviews', {
                rating: parseInt(rating, 10),
                comment: comment
            });

            submitBtn.disabled = false;
            submitBtn.textContent = 'ثبت نظر';

            if (!result.ok) {
                errorBox.textContent = result.message;
                errorBox.style.display = 'block';
                return;
            }

            errorBox.style.display = 'none';

            // نظر جدید رو بالای لیست اضافه می‌کنیم
            document.getElementById('reviewsList').insertAdjacentHTML('afterbegin', result.data.html);

            // میانگین امتیاز رو آپدیت می‌کنیم
            document.getElementById('reviewsAverageText').textContent = result.data.average + ' از ۵ (' + result.data.count + ' نظر)';

            // فرم رو با یه پیام تشکر جایگزین می‌کنیم
            const thanks = document.createElement('p');
            thanks.className = 'review-login-note';
            thanks.setAttribute('role', 'status');
            thanks.textContent = 'ممنون از ثبت نظرت 🌸';
            reviewForm.replaceWith(thanks);
        });
    }
})();
