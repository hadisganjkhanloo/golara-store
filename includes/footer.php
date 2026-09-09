</main>

<a href="https://wa.me/989909592514" target="_blank" rel="noopener" class="whatsapp-float" title="گفتگو در واتساپ"><?php echo icon('whatsapp', 'icon icon-whatsapp'); ?></a>

<button type="button" id="aiChatToggle" class="ai-chat-float" title="دستیار هوشمند گل‌آرا"><?php echo icon('zap'); ?></button>

<div class="ai-chat-panel" id="aiChatPanel">
    <div class="ai-chat-header">
        <span><?php echo icon('zap'); ?> دستیار زیبایی گل‌آرا</span>
        <div>
            <button type="button" id="aiChatReset" title="گفتگوی جدید"><?php echo icon('rotate-ccw'); ?></button>
            <button type="button" id="aiChatClose" title="بستن"><?php echo icon('x'); ?></button>
        </div>
    </div>
    <div class="ai-chat-messages" id="aiChatMessages">
        <div class="ai-msg ai-msg-bot">سلام! من دستیار هوشمند گل‌آرا هستم 🌸 درباره آرایش، مراقبت پوست یا محصولات فروشگاه هرچی بخوای بپرس.</div>
    </div>
    <form class="ai-chat-input-row" id="aiChatForm">
        <input type="text" id="aiChatInput" placeholder="سوالت رو بنویس..." autocomplete="off">
        <button type="submit" title="ارسال"><?php echo icon('send'); ?></button>
    </form>
</div>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-col">
            <h4>گل‌آرا</h4>
            <p class="footer-about">فروشگاه اینترنتی لوازم آرایشی و بهداشتی اورجینال، با ضمانت اصالت کالا و ارسال سریع به سراسر کشور.</p>
        </div>
        <div class="footer-col">
            <h4>دسته‌بندی‌ها</h4>
            <ul>
                <?php foreach ($nav_categories as $fc): ?>
                    <li><a href="/danshjoyar/index.php?category=<?php echo $fc['id']; ?>"><?php echo clean_input($fc['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="footer-col">
            <h4>دسترسی سریع</h4>
            <ul>
                <li><a href="/danshjoyar/index.php">صفحه اصلی</a></li>
                <li><a href="/danshjoyar/search.php">جستجو</a></li>
                <li><a href="/danshjoyar/wishlist.php">علاقه‌مندی‌ها</a></li>
                <li><a href="/danshjoyar/cart.php">سبد خرید</a></li>
                <li><a href="/danshjoyar/contact.php">تماس با ما</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>ارتباط با ما</h4>
            <ul>
                <li><?php echo icon('phone'); ?> ۰۹۹۰۹۵۹۲۵۱۴</li>
                <li><?php echo icon('mail'); ?> info@golara.shop</li>
                <li><?php echo icon('map-pin'); ?> تهران، ایران</li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>گل‌آرا — پروژه آموزشی فروشگاه لوازم آرایشی &copy; <?php echo date('Y'); ?> · <a href="/danshjoyar/admin/login.php" style="color:inherit; opacity:.6;">ورود مدیر</a></p>
        </div>
    </div>
</footer>

<script>
// تابع مشترک: توکن CSRF رو از متا تگ هدر می‌خونه تا تو همه fetch های POST بفرستیمش
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
}

// event delegation: یه گوش‌دهنده رو کل صفحه، برای همه دکمه‌های قلب (چه تو کارت محصول، چه تو صفحه جزئیات)
document.addEventListener('click', function (event) {
    const btn = event.target.closest('.wish-btn, .wish-btn-text');
    if (!btn || btn.disabled) {
        return; // جلوگیری از کلیک‌های پشت‌سرهم تا وقتی جواب قبلی نیومده
    }

    const productId = btn.dataset.productId;
    btn.disabled = true;

    fetch('/danshjoyar/ajax/toggle_wishlist.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + productId + '&csrf_token=' + encodeURIComponent(getCsrfToken())
    })
        .then(function (response) {
            return response.json();
        })
        .then(function (data) {
            const wishlistBadge = document.getElementById('wishlistBadge');
            if (wishlistBadge) {
                wishlistBadge.textContent = data.count;
            }

            // چون ممکنه همین محصول تو چند بخش صفحه تکرار شده باشه، همه رو آپدیت می‌کنیم
            const matches = document.querySelectorAll(
                '.wish-btn[data-product-id="' + productId + '"], .wish-btn-text[data-product-id="' + productId + '"]'
            );
            matches.forEach(function (el) {
                el.classList.toggle('is-active', data.active);
                const label = el.querySelector('.wish-btn-label');
                if (label) {
                    label.textContent = data.active ? 'در علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها';
                }

                // تو خود صفحه علاقه‌مندی‌ها، اگه قلب خاموش شد کل کارت محصول رو حذف کن
                if (!data.active && window.location.pathname.indexOf('wishlist.php') !== -1) {
                    const card = el.closest('.product-card');
                    if (card) {
                        card.remove();
                    }
                }

                el.disabled = false;
            });
        })
        .catch(function () {
            btn.disabled = false;
        });
});

// دستیار هوشمند (چت با Gemini)
(function () {
    const toggleBtn = document.getElementById('aiChatToggle');
    const panel = document.getElementById('aiChatPanel');
    const closeBtn = document.getElementById('aiChatClose');
    const resetBtn = document.getElementById('aiChatReset');
    const form = document.getElementById('aiChatForm');
    const input = document.getElementById('aiChatInput');
    const messagesBox = document.getElementById('aiChatMessages');

    if (!toggleBtn) {
        return;
    }

    toggleBtn.addEventListener('click', function () {
        panel.classList.toggle('is-open');
        if (panel.classList.contains('is-open')) {
            input.focus();
        }
    });

    closeBtn.addEventListener('click', function () {
        panel.classList.remove('is-open');
    });

    function addMessage(text, who) {
        const msg = document.createElement('div');
        msg.className = 'ai-msg ai-msg-' + who;
        msg.textContent = text;
        messagesBox.appendChild(msg);
        messagesBox.scrollTop = messagesBox.scrollHeight;
        return msg;
    }

    resetBtn.addEventListener('click', function () {
        fetch('/danshjoyar/ajax/ai_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'reset=1&csrf_token=' + encodeURIComponent(getCsrfToken())
        }).then(function () {
            messagesBox.innerHTML = '';
            addMessage('گفتگوی جدید شروع شد 🌸 چطور می‌تونم کمکت کنم؟', 'bot');
        });
    });

    form.addEventListener('submit', function (event) {
        event.preventDefault();

        const message = input.value.trim();
        if (message === '') {
            return;
        }

        addMessage(message, 'user');
        input.value = '';
        input.disabled = true;

        const loadingMsg = addMessage('در حال تایپ...', 'bot');
        loadingMsg.classList.add('ai-msg-loading');

        fetch('/danshjoyar/ajax/ai_chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'message=' + encodeURIComponent(message) + '&csrf_token=' + encodeURIComponent(getCsrfToken())
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                loadingMsg.remove();
                if (data.success) {
                    addMessage(data.reply, 'bot');
                } else {
                    addMessage('⚠️ ' + data.error, 'bot');
                }
            })
            .catch(function () {
                loadingMsg.remove();
                addMessage('⚠️ ارتباط با سرور برقرار نشد.', 'bot');
            })
            .finally(function () {
                input.disabled = false;
                input.focus();
            });
    });
})();
</script>

</body>
</html>
