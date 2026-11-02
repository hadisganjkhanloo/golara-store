</main>

<?php if (SHOP_WHATSAPP !== ''): ?>
    <a href="https://wa.me/<?php echo clean_input(preg_replace('/\D/', '', SHOP_WHATSAPP)); ?>" target="_blank" rel="noopener" class="whatsapp-float" aria-label="گفتگو در واتساپ (در تب جدید باز می‌شود)"><?php echo icon('whatsapp', 'icon icon-whatsapp'); ?></a>
<?php endif; ?>

<button type="button" id="aiChatToggle" class="ai-chat-float" aria-label="دستیار هوشمند <?php echo clean_input(SHOP_NAME); ?>" aria-expanded="false" aria-controls="aiChatPanel"><?php echo icon('zap'); ?></button>

<div class="ai-chat-panel" id="aiChatPanel" role="dialog" aria-label="دستیار زیبایی <?php echo clean_input(SHOP_NAME); ?>">
    <div class="ai-chat-header">
        <span><?php echo icon('zap'); ?> دستیار زیبایی <?php echo clean_input(SHOP_NAME); ?></span>
        <div>
            <button type="button" id="aiChatReset" aria-label="گفتگوی جدید"><?php echo icon('rotate-ccw'); ?></button>
            <button type="button" id="aiChatClose" aria-label="بستن"><?php echo icon('x'); ?></button>
        </div>
    </div>
    <div class="ai-chat-messages" id="aiChatMessages" aria-live="polite">
        <div class="ai-msg ai-msg-bot">سلام! من دستیار هوشمند <?php echo clean_input(SHOP_NAME); ?> هستم 🌸 درباره آرایش، مراقبت پوست یا محصولات فروشگاه هرچی بخوای بپرس.</div>
    </div>
    <form class="ai-chat-input-row" id="aiChatForm">
        <input type="text" id="aiChatInput" placeholder="سوالت رو بنویس..." autocomplete="off" aria-label="پیام شما">
        <button type="submit" aria-label="ارسال"><?php echo icon('send'); ?></button>
    </form>
</div>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-col">
            <h2 class="footer-title"><?php echo clean_input(SHOP_NAME); ?></h2>
            <p class="footer-about">فروشگاه اینترنتی لوازم آرایشی و بهداشتی اورجینال، با ضمانت اصالت کالا و ارسال سریع به سراسر کشور.</p>
        </div>
        <div class="footer-col">
            <h2 class="footer-title">دسته‌بندی‌ها</h2>
            <ul>
                <?php foreach ($nav_categories as $fc): ?>
                    <li><a href="<?php echo url('products.php?category=' . $fc['id']); ?>"><?php echo clean_input($fc['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="footer-col">
            <h2 class="footer-title">دسترسی سریع</h2>
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
            <h2 class="footer-title">ارتباط با ما</h2>
            <ul>
                <?php if (SHOP_PHONE !== ''): ?><li><?php echo icon('phone'); ?> <?php echo clean_input(SHOP_PHONE); ?></li><?php endif; ?>
                <?php if (SHOP_EMAIL !== ''): ?><li><?php echo icon('mail'); ?> <?php echo clean_input(SHOP_EMAIL); ?></li><?php endif; ?>
                <?php if (SHOP_ADDRESS !== ''): ?><li><?php echo icon('map-pin'); ?> <?php echo clean_input(SHOP_ADDRESS); ?></li><?php endif; ?>
                <li><a href="<?php echo url('contact.php'); ?>"><?php echo icon('message-circle'); ?> ارسال پیام به ما</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p><?php echo clean_input(SHOP_NAME); ?> &copy; <?php echo substr(format_date(time()), 0, 4); ?> — همه حقوق محفوظ است · <a href="<?php echo url('admin/login.php'); ?>" class="footer-admin-link">ورود مدیر</a></p>
        </div>
    </div>
</footer>

<script src="<?php echo asset('assets/js/app.js'); ?>"></script>
<?php foreach (($page_scripts ?? []) as $page_script): ?>
    <script src="<?php echo asset($page_script); ?>"></script>
<?php endforeach; ?>

</body>
</html>
