<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/catalog.php';

header('Content-Type: application/xml; charset=utf-8');

$baseUrl = 'https://oilpatchdepot.com';
$now = gmdate('Y-m-d');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc><?php echo $baseUrl; ?>/</loc><changefreq>daily</changefreq><priority>1.0</priority></url>
  <url><loc><?php echo $baseUrl; ?>/products.php</loc><changefreq>daily</changefreq><priority>0.8</priority></url>
<?php
// Categories
$categories = opd_public_product_categories();
foreach ($categories as $cat):
?>
  <url><loc><?php echo $baseUrl . '/category.php?category=' . urlencode($cat); ?></loc><changefreq>daily</changefreq><priority>0.8</priority></url>
<?php endforeach; ?>
<?php
// Products
$pdo = opd_db();
$stmt = $pdo->query("SELECT id, updatedAt FROM products WHERE COALESCE(status, 'active') != 'inactive' AND COALESCE(category, '') != 'Hidden' ORDER BY updatedAt DESC");
$products = $stmt->fetchAll();
foreach ($products as $p):
    $lastmod = $p['updatedAt'] ? date('Y-m-d', strtotime($p['updatedAt'])) : $now;
?>
  <url><loc><?php echo $baseUrl . '/product.php?id=' . urlencode($p['id']); ?></loc><lastmod><?php echo $lastmod; ?></lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>
<?php endforeach; ?>
<?php
// CMS Pages
try {
    $stmt = $pdo->query("SELECT slug, updatedAt FROM pages WHERE COALESCE(status, 'published') = 'published' ORDER BY updatedAt DESC");
    $pages = $stmt->fetchAll();
    foreach ($pages as $pg):
        $lastmod = $pg['updatedAt'] ? date('Y-m-d', strtotime($pg['updatedAt'])) : $now;
?>
  <url><loc><?php echo $baseUrl . '/page.php?slug=' . urlencode($pg['slug']); ?></loc><lastmod><?php echo $lastmod; ?></lastmod><changefreq>monthly</changefreq><priority>0.5</priority></url>
<?php
    endforeach;
} catch (Throwable $e) {
    // pages table may not exist yet
}
?>
  <url><loc><?php echo $baseUrl; ?>/login.php</loc><changefreq>monthly</changefreq><priority>0.3</priority></url>
  <url><loc><?php echo $baseUrl; ?>/register.php</loc><changefreq>monthly</changefreq><priority>0.3</priority></url>
</urlset>
