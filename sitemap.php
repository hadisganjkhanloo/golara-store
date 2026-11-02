<?php
// sitemap.xml پویا: صفحه اصلی، فهرست محصولات، دسته‌بندی‌ها و تک‌تک محصولات.
// آدرس /sitemap.xml با rewrite تو .htaccess به این فایل می‌رسه.

require_once __DIR__ . '/config/db.php';

header('Content-Type: application/xml; charset=utf-8');

$urls = [
    [absolute_url('index.php'), null, '1.0'],
    [absolute_url('products.php'), null, '0.9'],
    [absolute_url('contact.php'), null, '0.3'],
];

foreach ((new Category())->all('id ASC') as $category) {
    $urls[] = [absolute_url('products.php?category=' . $category['id']), null, '0.8'];
}

foreach ((new Product())->allForSitemap() as $product) {
    $urls[] = [absolute_url('product.php?id=' . $product['id']), date('c', strtotime($product['modified'])), '0.7'];
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as [$loc, $lastmod, $priority]) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</loc>\n";
    if ($lastmod) {
        echo "    <lastmod>$lastmod</lastmod>\n";
    }
    echo "    <priority>$priority</priority>\n";
    echo "  </url>\n";
}
echo "</urlset>\n";
