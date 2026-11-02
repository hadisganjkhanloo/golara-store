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
                    <li><a href="<?php echo url('products.php?category=' . $fc['id']); ?>"><?php echo clean_input($fc['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="footer-col">
            <h4>دسترسی سریع</h4>
            <ul>
                <li><a href="<?php echo url('index.php'); ?>">صفحه اصلی</a></li>
                <li><a href="<?php echo url('products.php'); ?>">همه محصولات</a></li>
                <li><a href="<?php echo url('search.php'); ?>">جستجو</a></li>
                <li><a href="<?php echo url('wishlist.php'); ?>">علاقه‌مندی‌ها</a></li>
                <li><a href="<?php echo url('cart.php'); ?>">سبد خرید</a></li>
                <li><a href="<?php echo url('contact.php'); ?>">تماس با ما</a></li>
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
            <p>گل‌آرا — پروژه آموزشی فروشگاه لوازم آرایشی &copy; <?php echo date('Y'); ?> · <a href="<?php echo url('admin/login.php'); ?>" style="color:inherit; opacity:.6;">ورود مدیر</a></p>
        </div>
    </div>
</footer>


<script>
// آدرس پایه API (از BASE_PATH ساخته می‌شه، پس روی هر دامنه/پوشه‌ای درسته)
window.GOLARA = { apiUrl: '<?php echo url('api/v1'); ?>' };
</script>
<script src="<?php echo asset('assets/js/app.js'); ?>"></script>

</body>
</html>
