// جاوااسکریپت مشترک همه صفحه‌های فروشگاه
//
//  ۱) Golara.api()        : تنها راه حرف زدن با سرور؛ خروجی همیشه {ok, status, message, data, errors}
//  ۲) همگام‌سازی تب‌ها     : هر تغییر سبد/علاقه‌مندی به تب‌های دیگه خبر داده می‌شه (BroadcastChannel)
//                            و تب‌ها وقتی دوباره دیده می‌شن، خودشون از سرور تازه می‌شن
//  ۳) علاقه‌مندی‌ها، سبد خرید، کد تخفیف، خبرنامه و دستیار هوشمند
//  ۴) اجزای کوچک رابط: اسلایدر (توقف)، پنل فیلتر، بزرگ‌نمایی عکس، چاپ، تأیید حذف
//
// هیچ اسکریپت inline ای تو صفحه‌ها نیست (سیاست CSP فقط فایل‌های خود سایت رو اجرا می‌کنه)؛
// آدرس API از <meta name="api-url"> و توکن CSRF از <meta name="csrf-token"> خونده می‌شه.

(function () {
    'use strict';

    function metaContent(name) {
        const meta = document.querySelector('meta[name="' + name + '"]');
        return meta ? meta.content : '';
    }

    const apiUrl = metaContent('api-url');

    function csrfToken() {
        return metaContent('csrf-token');
    }

    // ---------- ۱) کلاینت API ----------
    // همه جواب‌ها (چه موفق چه خطا) یه شکل دارن، پس هر جایی فقط به result.ok نگاه می‌کنیم
    async function api(method, path, body) {
        const options = {
            method: method,
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': csrfToken() }
        };

        if (body instanceof FormData) {
            options.body = body;
        } else if (body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }

        let response;
        try {
            response = await fetch(apiUrl + path, options);
        } catch (error) {
            return { ok: false, status: 0, message: 'ارتباط با سرور برقرار نشد.', data: null, errors: {} };
        }

        let json = null;
        try {
            json = await response.json();
        } catch (error) {
            // جواب JSON نبود (مثلاً خطای سرور)
        }

        return {
            ok: response.ok && json !== null && json.success === true,
            status: response.status,
            message: json && json.message ? json.message : (response.ok ? '' : 'مشکلی پیش آمد، دوباره تلاش کنید.'),
            data: json && json.data !== undefined ? json.data : null,
            errors: json && json.errors ? json.errors : {}
        };
    }

    // ---------- پیام کوتاه گوشه صفحه ----------
    function toast(message, type) {
        if (!message) {
            return;
        }

        let box = document.getElementById('toastBox');
        if (!box) {
            box = document.createElement('div');
            box.id = 'toastBox';
            box.className = 'toast-box';
            box.setAttribute('role', 'status');
            box.setAttribute('aria-live', 'polite');
            document.body.appendChild(box);
        }

        const item = document.createElement('div');
        item.className = 'toast toast-' + (type || 'success');
        item.textContent = message;
        box.appendChild(item);

        setTimeout(function () {
            item.classList.add('is-leaving');
            setTimeout(function () { item.remove(); }, 300);
        }, 3200);
    }

    // ---------- شمارنده‌ها و وضعیت قلب‌ها ----------
    function setCartCount(count) {
        const badge = document.getElementById('cartBadge');
        if (badge) {
            badge.textContent = count;
        }
    }

    function setWishlistCount(count) {
        const badge = document.getElementById('wishlistBadge');
        if (badge) {
            badge.textContent = count;
        }
    }

    // قلب همه کارت‌ها و دکمه‌های صفحه رو با لیست شناسه‌های علاقه‌مندی هماهنگ می‌کنه
    function applyWishlistIds(ids) {
        const set = new Set((ids || []).map(String));

        document.querySelectorAll('.wish-btn, .wish-btn-text').forEach(function (el) {
            const active = set.has(String(el.dataset.productId));
            el.classList.toggle('is-active', active);
            el.setAttribute('aria-pressed', active ? 'true' : 'false');

            const label = el.querySelector('.wish-btn-label');
            if (label) {
                label.textContent = active ? 'در علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها';
            }
        });
    }

    // ---------- ۲) همگام‌سازی بین تب‌ها ----------
    // وقتی تب A چیزی رو عوض می‌کنه، به بقیه تب‌های همین مرورگر پیام می‌ده تا از سرور تازه بشن.
    // BroadcastChannel تو همه مرورگرهای امروزی هست؛ برای مرورگرهای قدیمی از رویداد storage استفاده می‌کنیم.
    const channel = 'BroadcastChannel' in window ? new BroadcastChannel('golara-store') : null;

    function notifyOtherTabs(kind) {
        try {
            if (channel) {
                channel.postMessage({ kind: kind, at: Date.now() });
            } else {
                localStorage.setItem('golara-sync', kind + ':' + Date.now());
            }
        } catch (error) {
            // مرورگر ذخیره‌سازی رو بسته؛ مشکلی نیست، تب‌ها با فوکوس شدن تازه می‌شن
        }
    }

    let lastRefresh = 0;
    let refreshing = false;
    let refreshTimer = null;
    let refreshAgain = false;

    // شمارنده‌ها، قلب‌ها و (اگه صفحه سبد/علاقه‌مندی بازه) محتوای صفحه رو از سرور دوباره می‌گیره
    async function runRefresh() {
        refreshing = true;
        const result = await api('GET', '/state');
        refreshing = false;
        lastRefresh = Date.now();

        if (result.ok) {
            setCartCount(result.data.cart.count);
            setWishlistCount(result.data.wishlist.count);
            applyWishlistIds(result.data.wishlist.ids);

            if (document.getElementById('cartContent')) {
                reloadCart();
            }
            if (document.getElementById('wishlistContent')) {
                reloadWishlist();
            }
        }

        // تو حین این درخواست، تغییر دیگه‌ای خبر داده بود؛ دوباره می‌گیریم تا هیچ تغییری گم نشه
        if (refreshAgain) {
            refreshAgain = false;
            refreshState();
        }
    }

    // درخواست‌های پشت‌سرهم رو یکی می‌کنه، ولی هیچ‌کدوم رو دور نمی‌ندازه:
    // اگه دو تغییر کمتر از ۴۰۰ میلی‌ثانیه فاصله داشتن، یه دریافت بعد از آخرین‌شون انجام می‌شه
    function refreshState() {
        if (refreshing) {
            refreshAgain = true;
            return;
        }
        if (refreshTimer) {
            return;
        }

        const wait = Math.max(0, 400 - (Date.now() - lastRefresh));
        refreshTimer = setTimeout(function () {
            refreshTimer = null;
            runRefresh();
        }, wait);
    }

    if (channel) {
        channel.addEventListener('message', refreshState);
    }
    window.addEventListener('storage', function (event) {
        if (event.key === 'golara-sync') {
            refreshState();
        }
    });

    // برگشتن به تب (یا دکمه Back که صفحه رو از حافظه نشون می‌ده) = احتمالاً اطلاعات کهنه شده
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'visible') {
            refreshState();
        }
    });
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            refreshState();
        }
    });
    window.addEventListener('focus', refreshState);

    // ---------- ۳) علاقه‌مندی‌ها ----------
    // قلب روشن = حذف (DELETE)، خاموش = افزودن (PUT). دو عمل جدا و ایدمپوتنت، نه toggle
    document.addEventListener('click', async function (event) {
        const btn = event.target.closest('.wish-btn, .wish-btn-text');
        if (!btn || btn.disabled) {
            return;
        }

        const productId = btn.dataset.productId;
        const wasActive = btn.classList.contains('is-active');

        btn.disabled = true;
        const result = await api(wasActive ? 'DELETE' : 'PUT', '/wishlist/' + encodeURIComponent(productId));
        btn.disabled = false;

        if (!result.ok) {
            toast(result.message, 'error');
            return;
        }

        setWishlistCount(result.data.count);
        applyWishlistIds(result.data.ids);
        notifyOtherTabs('wishlist');

        if (document.getElementById('wishlistContent')) {
            reloadWishlist();
        }
    });

    async function reloadWishlist() {
        const box = document.getElementById('wishlistContent');
        const result = await api('GET', '/wishlist');

        if (box && result.ok) {
            box.innerHTML = result.data.html;
            setWishlistCount(result.data.count);
        }
    }

    // ---------- سبد خرید ----------
    async function addToCart(productId, quantity) {
        const result = await api('POST', '/cart/items', { product_id: Number(productId), quantity: quantity || 1 });

        if (result.data && result.data.count !== undefined) {
            setCartCount(result.data.count);
        }
        if (result.ok) {
            notifyOtherTabs('cart');
        }

        return result;
    }

    // دکمه «افزودن به سبد» روی کارت محصول‌ها (هر جای سایت)
    document.addEventListener('click', async function (event) {
        const btn = event.target.closest('.js-add-to-cart');
        if (!btn || btn.disabled) {
            return;
        }

        btn.disabled = true;
        const result = await addToCart(btn.dataset.productId, 1);
        btn.disabled = false;

        toast(result.ok ? 'به سبد خرید اضافه شد ✓' : result.message, result.ok ? 'success' : 'error');
    });

    async function reloadCart() {
        const box = document.getElementById('cartContent');
        const result = await api('GET', '/cart');

        if (box && result.ok) {
            box.innerHTML = result.data.html;
            setCartCount(result.data.count);
        }
    }

    // دکمه‌های + و − و حذف و کد تخفیف داخل سبد (event delegation چون جدول بعد از هر تغییر دوباره ساخته می‌شه)
    const cartContent = document.getElementById('cartContent');
    if (cartContent) {
        // جواب سبد (حتی تو خطا، مثلاً کمبود موجودی یا کد تخفیف نامعتبر، وضعیت تازه سبد رو داره)
        function applyCartResult(result) {
            if (result.data && result.data.html) {
                cartContent.innerHTML = result.data.html;
                setCartCount(result.data.count);
            }

            if (result.ok) {
                notifyOtherTabs('cart');
            } else {
                toast(result.message, 'error');
            }
        }

        cartContent.addEventListener('click', async function (event) {
            const btn = event.target.closest('[data-action]');
            if (!btn || btn.disabled) {
                return;
            }

            const action = btn.dataset.action;
            const control = btn.closest('.qty-control');
            const productId = btn.dataset.productId || (control ? control.dataset.productId : '');

            cartContent.classList.add('is-busy');

            let result;
            if (action === 'remove') {
                result = await api('DELETE', '/cart/items/' + encodeURIComponent(productId));
            } else if (action === 'remove-coupon') {
                result = await api('DELETE', '/cart/coupon');
            } else if (action === 'inc' || action === 'dec') {
                const current = parseInt(control.querySelector('.qty-value').textContent, 10) || 0;
                result = await api('PATCH', '/cart/items/' + encodeURIComponent(productId), {
                    quantity: current + (action === 'inc' ? 1 : -1)
                });
            } else {
                cartContent.classList.remove('is-busy');
                return;
            }

            cartContent.classList.remove('is-busy');
            applyCartResult(result);
        });

        cartContent.addEventListener('submit', async function (event) {
            const form = event.target.closest('[data-coupon-form]');
            if (!form) {
                return;
            }

            event.preventDefault();

            cartContent.classList.add('is-busy');
            const result = await api('PUT', '/cart/coupon', { code: form.querySelector('input[name="code"]').value });
            cartContent.classList.remove('is-busy');

            applyCartResult(result);
            if (result.ok) {
                toast(result.message, 'success');
            }
        });
    }

    // ---------- خبرنامه (Ajax تا صفحه بالا نپره) ----------
    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const input = newsletterForm.querySelector('input[name="email"]');
            const button = newsletterForm.querySelector('button[type="submit"]');
            const message = document.getElementById('newsletterMessage');

            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = 'در حال ثبت...';

            const result = await api('POST', '/newsletter', { email: input.value.trim() });

            button.disabled = false;
            button.textContent = originalText;

            message.textContent = result.ok ? result.message : (result.errors.email || result.message);
            message.className = 'newsletter-message ' + (result.ok ? 'is-success' : 'is-error');

            if (result.ok) {
                input.value = '';
            }
        });
    }

    // ---------- دستیار هوشمند ----------
    const aiToggle = document.getElementById('aiChatToggle');
    if (aiToggle) {
        const panel = document.getElementById('aiChatPanel');
        const form = document.getElementById('aiChatForm');
        const input = document.getElementById('aiChatInput');
        const box = document.getElementById('aiChatMessages');

        function addMessage(text, who) {
            const msg = document.createElement('div');
            msg.className = 'ai-msg ai-msg-' + who;
            msg.textContent = text;
            box.appendChild(msg);
            box.scrollTop = box.scrollHeight;
            return msg;
        }

        function setOpen(open) {
            panel.classList.toggle('is-open', open);
            aiToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open) {
                input.focus();
            } else {
                aiToggle.focus();
            }
        }

        aiToggle.addEventListener('click', function () {
            setOpen(!panel.classList.contains('is-open'));
        });

        document.getElementById('aiChatClose').addEventListener('click', function () {
            setOpen(false);
        });

        panel.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        });

        document.getElementById('aiChatReset').addEventListener('click', async function () {
            await api('DELETE', '/assistant/messages');
            box.innerHTML = '';
            addMessage('گفتگوی جدید شروع شد 🌸 چطور می‌تونم کمکت کنم؟', 'bot');
        });

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            const message = input.value.trim();
            if (message === '') {
                return;
            }

            addMessage(message, 'user');
            input.value = '';
            input.disabled = true;

            const loading = addMessage('در حال تایپ...', 'bot');
            loading.classList.add('ai-msg-loading');

            const result = await api('POST', '/assistant/messages', { message: message });

            loading.remove();
            addMessage(result.ok ? result.data.reply : '⚠️ ' + result.message, 'bot');

            input.disabled = false;
            input.focus();
        });
    }

    // ---------- ۴) اجزای کوچک رابط ----------

    // اسلایدر صفحه اول: دکمه توقف (برای کسی که نمی‌تونه با حرکت خودکار کنار بیاد). با prefers-reduced-motion CSS خودش متوقفش می‌کنه.
    const heroSlider = document.getElementById('heroSlider');
    const heroPause = document.getElementById('heroPause');
    if (heroSlider && heroPause) {
        heroPause.addEventListener('click', function () {
            const paused = heroSlider.classList.toggle('is-paused');
            heroPause.setAttribute('aria-pressed', paused ? 'true' : 'false');
            heroPause.textContent = paused ? 'ادامه اسلایدر' : 'توقف اسلایدر';
        });
    }

    // پنل فیلتر محصولات: روی دسکتاپ همیشه بازه، روی موبایل جمع‌شده (کاربر با زدن «فیلتر و مرتب‌سازی» بازش می‌کنه)
    const filterPanel = document.getElementById('filterPanel');
    if (filterPanel && window.matchMedia('(min-width: 821px)').matches) {
        filterPanel.open = true;
    }

    // بزرگ‌نمایی عکس محصول: کلیک روی عکس یه لایه‌ی تمام‌صفحه باز می‌کنه (Esc یا کلیک بیرون = بستن)
    document.addEventListener('click', function (event) {
        const link = event.target.closest('[data-zoom]');
        if (!link) {
            return;
        }

        event.preventDefault();

        const overlay = document.createElement('div');
        overlay.className = 'zoom-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'عکس بزرگ‌شده');

        const image = document.createElement('img');
        image.src = link.href;
        image.alt = link.querySelector('img') ? link.querySelector('img').alt : '';

        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'zoom-close';
        close.setAttribute('aria-label', 'بستن');
        close.textContent = '×';

        overlay.appendChild(image);
        overlay.appendChild(close);
        document.body.appendChild(overlay);
        close.focus();

        function dismiss() {
            overlay.remove();
            document.removeEventListener('keydown', onKey);
            link.focus();
        }
        function onKey(e) {
            if (e.key === 'Escape') {
                dismiss();
            }
        }

        overlay.addEventListener('click', function (e) {
            if (e.target !== image) {
                dismiss();
            }
        });
        document.addEventListener('keydown', onKey);
    });

    // دکمه چاپ فاکتور
    document.addEventListener('click', function (event) {
        if (event.target.closest('[data-print]')) {
            window.print();
        }
    });

    // تأیید قبل از عملیات خطرناک: <form data-confirm="متن سوال">
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-confirm]');
        if (form && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });

    // برای اسکریپت‌های مخصوص هر صفحه (assets/js/product.js و ...)
    window.Golara = {
        api: api,
        toast: toast,
        addToCart: addToCart,
        setCartCount: setCartCount,
        notifyOtherTabs: notifyOtherTabs
    };
})();
