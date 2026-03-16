<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/seo.php';

$search = trim($_GET['q'] ?? '');
$products = site_get_products(null, $search ?: null, 48);
$user = site_current_user();
$isSignedIn = $user !== null;
$csrf = site_csrf_token();
$_seoTitle = ($search ? 'Search: ' . $search : 'All Products') . ' - ' . opd_site_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($_seoTitle, ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260315c" />
  <?php opd_seo_meta([
    'title' => $_seoTitle,
    'description' => $search
      ? 'Search results for "' . $search . '" - oilfield equipment and supplies from ' . opd_site_name()
      : 'Browse all oilfield equipment, tools, parts, and supplies. Nationwide shipping with same-day delivery in Oklahoma.',
    'canonical' => $search ? '/products.php?q=' . urlencode($search) : '/products.php',
    'noindex' => $search !== '',
  ]); ?>
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>
  <div class="notice" id="favorite-message" style="display:none;"></div>

  <main class="page">
    <nav aria-label="Breadcrumb">
      <ol class="breadcrumb">
        <li><a href="/">Home</a></li>
        <li><span class="breadcrumb-current">Products</span></li>
      </ol>
    </nav>
    <section class="panel">
      <div class="section-title">
        <div>
          <h1><?php echo $search !== '' ? 'Search results' : 'All products'; ?></h1>
          <?php if ($search !== ''): ?>
            <p class="meta">Showing results for: <strong><?php echo htmlspecialchars($search, ENT_QUOTES); ?></strong> (<?php echo count($products); ?> found)</p>
          <?php else: ?>
            <p class="meta">Search and select the right inventory for your crew.</p>
          <?php endif; ?>
        </div>
        <form method="GET">
          <input type="search" name="q" placeholder="Search products" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>" />
        </form>
      </div>
      <div class="grid cols-3">
        <?php foreach ($products as $product): ?>
          <?php $productId = $product['id'] ?? ''; ?>
          <div class="card">
            <div class="tag"><?php echo htmlspecialchars($product['status'] ?? 'available', ENT_QUOTES); ?></div>
            <?php if (!empty($product['imageUrl'])): ?>
              <img class="product-thumb" src="<?php echo htmlspecialchars($product['imageUrl'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?>" loading="lazy" />
            <?php else: ?>
              <div class="image-placeholder">No image</div>
            <?php endif; ?>
            <h3><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?></h3>
            <div class="meta"><?php echo htmlspecialchars($product['sku'] ?? '', ENT_QUOTES); ?></div>
            <div class="price">$<?php echo number_format((float) ($product['price'] ?? 0), 2); ?></div>
            <div class="meta"><?php echo htmlspecialchars($product['category'] ?? 'General', ENT_QUOTES); ?></div>
            <div class="product-card-actions">
              <div class="favorite-wrap">
                <div class="favorite-message-inline" data-favorite-message hidden>
                  Please Sign-In to Select Favorites.
                  <a href="/login.php">Sign in</a> or <a href="/register.php">Register</a>
                </div>
                <button
                  type="button"
                  class="favorite-btn"
                  data-favorite
                  data-product-id="<?php echo htmlspecialchars($productId, ENT_QUOTES); ?>"
                  aria-label="Add to favorites"
                >
                  <svg class="favorite-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                  </svg>
                </button>
                <div class="favorite-dropdown" data-favorite-menu hidden></div>
              </div>
              <a class="btn" href="/product.php?id=<?php echo urlencode($productId); ?>">View details</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
  <script>
    (function () {
      if (window.Favorites && typeof Favorites.init === 'function') {
        Favorites.init({ csrfToken: <?php echo json_encode($csrf); ?>, isSignedIn: <?php echo $isSignedIn ? 'true' : 'false'; ?> })
      }
    })()
  </script>
</body>
</html>
