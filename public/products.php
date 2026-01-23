<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';

$search = trim($_GET['q'] ?? '');
$products = site_get_products(null, $search ?: null, 48);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Products - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel">
      <div class="section-title">
        <div>
          <h2>All products</h2>
          <p class="meta">Search and select the right inventory for your crew.</p>
        </div>
        <form method="GET">
          <input type="search" name="q" placeholder="Search products" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>" />
        </form>
      </div>
      <div class="grid cols-3">
        <?php foreach ($products as $product): ?>
          <div class="card">
            <div class="tag"><?php echo htmlspecialchars($product['status'] ?? 'available', ENT_QUOTES); ?></div>
            <?php if (!empty($product['imageUrl'])): ?>
              <img class="product-thumb" src="<?php echo htmlspecialchars($product['imageUrl'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?>" />
            <?php else: ?>
              <div class="image-placeholder">No image</div>
            <?php endif; ?>
            <h3><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?></h3>
            <div class="meta"><?php echo htmlspecialchars($product['sku'] ?? '', ENT_QUOTES); ?></div>
            <div class="price">$<?php echo number_format((float) ($product['price'] ?? 0), 2); ?></div>
            <div class="meta"><?php echo htmlspecialchars($product['category'] ?? 'General', ENT_QUOTES); ?></div>
            <a class="btn" href="/product.php?id=<?php echo urlencode($product['id']); ?>">View details</a>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
