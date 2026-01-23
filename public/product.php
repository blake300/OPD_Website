<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$productId = $_GET['id'] ?? '';
$product = $productId ? site_get_product($productId) : null;
if (!$product) {
    http_response_code(404);
    echo 'Product not found';
    exit;
}
$variants = site_get_product_variants($productId);
$relatedProducts = site_get_related_products($productId, 6);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $quantity = (int) ($_POST['quantity'] ?? 1);
    $variantId = $_POST['variantId'] ?? null;
    site_add_to_cart($productId, $quantity, $variantId ?: null);
    $message = 'Added to cart.';
}

$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?> - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel">
      <div class="section-title">
        <div>
          <div class="tag"><?php echo htmlspecialchars($product['status'] ?? 'available', ENT_QUOTES); ?></div>
          <h2><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?></h2>
          <p class="meta"><?php echo htmlspecialchars($product['sku'] ?? '', ENT_QUOTES); ?></p>
        </div>
        <div class="price">$<?php echo number_format((float) ($product['price'] ?? 0), 2); ?></div>
      </div>

      <?php if ($message): ?>
        <div class="notice"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <div class="grid cols-3">
        <div class="card product-image-card">
          <?php if (!empty($product['imageUrl'])): ?>
            <img class="product-image" src="<?php echo htmlspecialchars($product['imageUrl'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?>" />
          <?php else: ?>
            <div class="image-placeholder">No image</div>
          <?php endif; ?>
        </div>
        <div class="card">
          <h3>Specifications</h3>
          <p class="meta">Category: <?php echo htmlspecialchars($product['category'] ?? 'General', ENT_QUOTES); ?></p>
          <p class="meta">Inventory: <?php echo htmlspecialchars((string) ($product['inventory'] ?? 0), ENT_QUOTES); ?></p>
          <p>Built for field durability with verified inventory controls.</p>
        </div>
        <div class="card">
          <h3>Add to cart</h3>
          <form method="POST" class="form-grid">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <?php if ($variants): ?>
              <div>
                <label for="variantId">Variant</label>
                <select name="variantId" id="variantId">
                  <?php foreach ($variants as $variant): ?>
                    <option value="<?php echo htmlspecialchars($variant['id'], ENT_QUOTES); ?>">
                      <?php echo htmlspecialchars($variant['name'], ENT_QUOTES); ?> ($<?php echo number_format((float) ($variant['price'] ?? 0), 2); ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
            <div data-qty-wrap>
              <label for="quantity">Quantity</label>
              <div style="display:flex; gap:8px; align-items:center;">
                <button type="button" class="btn-outline" data-add-qty="minus">-</button>
                <input type="number" id="quantity" name="quantity" value="1" min="1" />
                <button type="button" class="btn-outline" data-add-qty="plus">+</button>
              </div>
            </div>
            <button class="btn" type="submit">Add to cart</button>
          </form>
        </div>
      </div>
    </section>

    <?php if ($relatedProducts): ?>
      <section class="panel">
        <div class="section-title">
          <h2>Associated products</h2>
        </div>
        <div class="grid cols-3">
          <?php foreach ($relatedProducts as $related): ?>
            <div class="card">
              <?php if (!empty($related['imageUrl'])): ?>
                <img class="product-thumb" src="<?php echo htmlspecialchars($related['imageUrl'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($related['name'] ?? 'Product', ENT_QUOTES); ?>" />
              <?php else: ?>
                <div class="image-placeholder">No image</div>
              <?php endif; ?>
              <h3><?php echo htmlspecialchars($related['name'] ?? 'Product', ENT_QUOTES); ?></h3>
              <div class="meta"><?php echo htmlspecialchars($related['sku'] ?? '', ENT_QUOTES); ?></div>
              <div class="price">$<?php echo number_format((float) ($related['price'] ?? 0), 2); ?></div>
              <a class="btn" href="/product.php?id=<?php echo urlencode($related['id']); ?>">View details</a>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
