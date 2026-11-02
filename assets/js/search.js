// جستجوی زنده (Ajax + debounce)

(function () {
    'use strict';

    const searchInput = document.getElementById('q');
    const resultsBox = document.getElementById('searchResults');
    const paginationBox = document.getElementById('searchPagination');
    let debounceTimer;

    if (!searchInput) {
        return;
    }

    searchInput.addEventListener('input', function () {
        // هر بار که کاربر تایپ می‌کنه، تایمر قبلی رو پاک می‌کنیم
        clearTimeout(debounceTimer);
        const query = searchInput.value.trim();

        if (query === '') {
            resultsBox.innerHTML = '';
            paginationBox.innerHTML = '';
            return;
        }

        // ۴۰۰ میلی‌ثانیه صبر می‌کنیم؛ اگه تا اون موقع کاربر دوباره تایپ کرد، این درخواست لغو می‌شه
        debounceTimer = setTimeout(async function () {
            resultsBox.textContent = 'در حال جستجو...';

            const result = await Golara.api('GET', '/search?q=' + encodeURIComponent(query));

            // اگه تا جواب برسه کاربر چیز دیگه‌ای تایپ کرده، این جواب کهنه رو نادیده می‌گیریم
            if (searchInput.value.trim() !== query) {
                return;
            }

            if (!result.ok) {
                resultsBox.textContent = result.message;
                paginationBox.innerHTML = '';
                return;
            }

            resultsBox.innerHTML = result.data.html;
            paginationBox.innerHTML = result.data.pagination;
        }, 400);
    });
})();
