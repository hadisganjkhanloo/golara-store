</main>
<script src="<?php echo asset('assets/js/app.js'); ?>"></script>
<?php foreach (($page_scripts ?? []) as $page_script): ?>
    <script src="<?php echo asset($page_script); ?>"></script>
<?php endforeach; ?>
</body>
</html>
