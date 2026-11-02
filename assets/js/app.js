// جاوااسکریپت مشترک همه صفحه‌های فروشگاه
//
//  ۱) Golara.api()        : تنها راه حرف زدن با سرور؛ خروجی همیشه {ok, status, message, data, errors}
//  ۲) همگام‌سازی تب‌ها     : هر تغییر سبد/علاقه‌مندی به تب‌های دیگه خبر داده می‌شه (BroadcastChannel)
//                            و تب‌ها وقتی دوباره دیده می‌شن، خودشون از سرور تازه می‌شن
//  ۳) علاقه‌مندی‌ها، سبد خرید، خبرنامه و دستیار هوشمند

(function () {
    'use strict';

    const config = window.GOLARA || {};

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
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
            response = await fetch(config.apiUrl + path, options);
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
    // (نسخه‌ی اول «دورریختن» بود و تب دیگه یه تغییر عقب می‌موند)
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

    async function reloadCart() {
        const box = document.getElementById('cartContent');
        const result = await api('GET', '/cart');

        if (box && result.ok) {
            box.innerHTML = result.data.html;
            setCartCount(result.data.count);
        }
    }

    // دکمه‌های + و − و حذف داخل جدول سبد (event delegation چون جدول بعد از هر تغییر دوباره ساخته می‌شه)
    const cartContent = document.getElementById('cartContent');
    if (cartContent) {
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
            } else {
                const current = parseInt(control.querySelector('.qty-value').textContent, 10) || 0;
                result = await api('PATCH', '/cart/items/' + encodeURIComponent(productId), {
                    quantity: current + (action === 'inc' ? 1 : -1)
                });
            }

            cartContent.classList.remove('is-busy');

            // حتی تو خطا (مثلاً کمبود موجودی) سرور وضعیت تازه سبد رو برمی‌گردونه
            if (result.data && result.data.html) {
                cartContent.innerHTML = result.data.html;
                setCartCount(result.data.count);
            }

            if (result.ok) {
                notifyOtherTabs('cart');
            } else {
                toast(result.message, 'error');
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

        aiToggle.addEventListener('click', function () {
            panel.classList.toggle('is-open');
            if (panel.classList.contains('is-open')) {
                input.focus();
            }
        });

        document.getElementById('aiChatClose').addEventListener('click', function () {
            panel.classList.remove('is-open');
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

    // برای اسکریپت‌های داخل صفحه‌ها (صفحه محصول، جستجو، پنل مدیریت)
    window.Golara = {
        api: api,
        toast: toast,
        addToCart: addToCart,
        setCartCount: setCartCount,
        notifyOtherTabs: notifyOtherTabs
    };
})();
