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

// Hide Used Equipment products when current quantity is 0
if (($product['category'] ?? '') === 'Used Equipment') {
    $sold = site_equipment_sold_quantity($productId);
    $inv = (int) ($product['inventory'] ?? 0);
    if ($inv > 0 && $sold >= $inv) {
        http_response_code(404);
        echo 'Product not found';
        exit;
    }
}

$user = site_current_user();
$isSignedIn = $user !== null;
$isService = !empty($product['service']);
$daysOut = (int) ($product['daysOut'] ?? 0);
$firstAvailableDate = '';
if ($isService) {
    $firstAvailableDate = gmdate('Y-m-d', strtotime('+' . max(0, $daysOut) . ' days'));
}

$productImages = $productId ? site_get_product_images($productId) : [];
if (!$productImages && !empty($product['imageUrl'])) {
    $productImages = [[
        'url' => $product['imageUrl'],
        'isPrimary' => 1,
    ]];
}
$primaryImageUrl = '';
if ($productImages) {
    foreach ($productImages as $image) {
        if (!empty($image['isPrimary'])) {
            $primaryImageUrl = (string) ($image['url'] ?? '');
            break;
        }
    }
    if ($primaryImageUrl === '') {
        $primaryImageUrl = (string) ($productImages[0]['url'] ?? '');
    }
}

$variants = site_get_product_variants($productId);
$hasVariants = !empty($variants);
$relatedProducts = site_get_related_products($productId, 6);
$hasAssociatedProducts = !empty($relatedProducts);
$relatedGroups = [];
if ($relatedProducts) {
    foreach ($relatedProducts as $related) {
        $categoryLabel = trim((string) ($related['category'] ?? ''));
        if ($categoryLabel === '') {
            $categoryLabel = 'Uncategorized';
        }
        if (!isset($relatedGroups[$categoryLabel])) {
            $relatedGroups[$categoryLabel] = [];
        }
        $relatedGroups[$categoryLabel][] = $related;
    }
}
$showMainAddToCart = !$hasVariants && !$hasAssociatedProducts;
$showAssociatedSection = $hasVariants || $hasAssociatedProducts;
$mainGridCols = 'cols-2';
$message = '';
$messageIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $postedProductId = $_POST['productId'] ?? $productId;
    $targetProductId = $productId;
    $targetProduct = $product;
    if (is_string($postedProductId) && $postedProductId !== '' && $postedProductId !== $productId) {
        $lookup = site_get_product($postedProductId);
        if ($lookup) {
            $targetProductId = $postedProductId;
            $targetProduct = $lookup;
        }
    }

    if (!$targetProduct) {
        $message = 'Product not found.';
        $messageIsError = true;
    } else {
        $variantId = $_POST['variantId'] ?? null;
        $variantId = is_string($variantId) && $variantId !== '' ? $variantId : null;
        $variantsForProduct = $targetProductId === $productId
            ? $variants
            : site_get_product_variants($targetProductId);
        $validVariantIds = array_column($variantsForProduct, 'id');
        if ($variantId && !in_array($variantId, $validVariantIds, true)) {
            $variantId = null;
        }
        if ($variantsForProduct && !$variantId) {
            $message = 'Select a product variation.';
            $messageIsError = true;
        } else {
            $arrivalDate = $_POST['arrivalDate'] ?? null;
            $arrivalDateValue = null;
            if (is_string($arrivalDate)) {
                $arrivalDate = trim($arrivalDate);
                if ($arrivalDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrivalDate)) {
                    $arrivalDateValue = $arrivalDate;
                }
            }
            $targetIsService = !empty($targetProduct['service']);
            if ($targetIsService && !$isSignedIn) {
                $message = 'You must sign in to book services.';
                $messageIsError = true;
            } elseif ($targetIsService && $arrivalDateValue === null) {
                $message = 'An arrival date is required for service items.';
                $messageIsError = true;
            } else {
                site_add_to_cart($targetProductId, $quantity, $variantId, $arrivalDateValue);
                $message = 'Added to cart.';
            }
        }
    }

    // AJAX response — skip full page render and return JSON
    if (!empty($_POST['_ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => !$messageIsError, 'message' => $message]);
        exit;
    }
}

$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?> - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260315c" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <nav aria-label="Breadcrumb">
      <ol class="breadcrumb">
        <li><a href="/">Home</a></li>
        <?php if (!empty($product['category'])): ?>
          <li><a href="/category.php?category=<?php echo urlencode($product['category']); ?>"><?php echo htmlspecialchars($product['category'], ENT_QUOTES); ?></a></li>
        <?php else: ?>
          <li><a href="/products.php">Products</a></li>
        <?php endif; ?>
        <li><span class="breadcrumb-current"><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?></span></li>
      </ol>
    </nav>
    <section class="panel">
      <div class="section-title">
        <div>
          <div class="tag"><?php echo htmlspecialchars($product['status'] ?? 'available', ENT_QUOTES); ?></div>
          <h2><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?></h2>
          <p class="meta"><?php echo htmlspecialchars($product['sku'] ?? '', ENT_QUOTES); ?></p>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="notice <?php echo $messageIsError ? 'is-error' : ''; ?>">
          <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
        </div>
      <?php endif; ?>
      <div class="notice" id="favorite-message" style="display:none;"></div>

      <div class="grid product-detail-grid <?php echo htmlspecialchars($mainGridCols, ENT_QUOTES); ?><?php echo $hasVariants ? ' has-variants' : ''; ?>">
        <div class="card product-image-card">
          <div class="product-gallery">
            <div class="product-gallery-main">
              <?php if ($primaryImageUrl !== ''): ?>
                <img id="product-main-image" class="product-image" src="<?php echo htmlspecialchars($primaryImageUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?>" />
              <?php else: ?>
                <div class="image-placeholder">No image</div>
              <?php endif; ?>
            </div>
            <?php if (count($productImages) > 1): ?>
              <div class="product-gallery-thumbs">
                <?php foreach ($productImages as $index => $image): ?>
                  <?php
                  $thumbUrl = (string) ($image['url'] ?? '');
                  $thumbActive = $thumbUrl !== '' && $thumbUrl === $primaryImageUrl;
                  ?>
                  <?php if ($thumbUrl !== ''): ?>
                    <button
                      type="button"
                      class="product-gallery-thumb <?php echo $thumbActive ? 'is-active' : ''; ?>"
                      data-gallery-thumb
                      data-src="<?php echo htmlspecialchars($thumbUrl, ENT_QUOTES); ?>"
                      aria-pressed="<?php echo $thumbActive ? 'true' : 'false'; ?>"
                    >
                      <img src="<?php echo htmlspecialchars($thumbUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?>" />
                    </button>
                  <?php endif; ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card product-spec-card">
          <div class="product-price-header">
            <?php if ($hasVariants || $hasAssociatedProducts): ?>
              <span class="product-price-from">from</span>
            <?php endif; ?>
            <span class="product-price-amount">$<?php echo number_format((float) ($product['price'] ?? 0), 2); ?></span>
          </div>

          <?php if (!empty($product['shortDescription'])): ?>
            <p class="product-short-description">
              <?php echo nl2br(htmlspecialchars((string) $product['shortDescription'], ENT_QUOTES)); ?>
            </p>
          <?php endif; ?>

          <?php if ($showMainAddToCart): ?>
            <form method="POST" class="product-cart-row" id="main-add-form">
              <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
              <input type="hidden" name="productId" value="<?php echo htmlspecialchars($productId, ENT_QUOTES); ?>" />
              <label class="visually-hidden" for="quantity">Quantity</label>
              <input type="number" id="quantity" name="quantity" value="1" min="1" class="product-qty-input" />
              <button class="btn" type="submit">Add</button>
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
            </form>
          <?php endif; ?>

          <div id="product-form-error" class="notice is-error" style="display:none;" role="alert"></div>

          <?php if ($isService): ?>
            <div class="service-dates">
              <div class="service-date-row">
                <span class="service-date-label">First Date Available</span>
                <span class="service-date-value"><?php echo htmlspecialchars($firstAvailableDate, ENT_QUOTES); ?></span>
              </div>
              <?php if ($isSignedIn): ?>
              <div class="service-date-row">
                <label class="service-date-label" for="main-arrival-date">Arrival Date</label>
                <input
                  type="date"
                  id="main-arrival-date"
                  name="arrivalDate"
                  class="service-date-input"
                  min="<?php echo htmlspecialchars($firstAvailableDate, ENT_QUOTES); ?>"
                  value=""
                  required
                  <?php echo $showMainAddToCart ? 'form="main-add-form"' : ''; ?>
                />
              </div>
              <?php else: ?>
              <div class="service-auth-notice">
                <p>You must sign in to book services. <a href="/login.php">Sign in</a> or <a href="/login.php?register=1">Register</a></p>
              </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

          <div class="specs-checkout-badge desktop-checkout-badge">
            <img class="specs-safe-checkout" src="/assets/Guaranteed Safe Checkout.jpg" alt="Guaranteed Safe Checkout" />
          </div>
        </div>
      </div>
      <div class="specs-checkout-badge mobile-checkout-badge">
        <img class="specs-safe-checkout" src="/assets/Guaranteed Safe Checkout.jpg" alt="Guaranteed Safe Checkout" />
      </div>
    </section>

    <?php if ($showAssociatedSection): ?>
      <section class="panel">
        <?php if ($hasVariants): ?>
          <div class="associated-group">
            <div class="associated-group-list">
              <details class="variant-dropdown" open>
                <summary class="associated-summary">
                  <span class="variant-toggle" aria-hidden="true"></span>
                  <div class="associated-product-media">
                    <?php if ($primaryImageUrl !== ''): ?>
                      <img class="product-thumb" src="<?php echo htmlspecialchars($primaryImageUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?>" />
                    <?php else: ?>
                      <div class="image-placeholder">No image</div>
                    <?php endif; ?>
                  </div>
                  <div class="associated-product-info">
                    <div class="associated-product-name"><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?></div>
                  </div>
                </summary>
                <div class="variant-dropdown-body">
                  <div class="card associated-product">
                    <table class="table">
                      <thead>
                        <tr>
                          <th>Variant</th>
                          <th>Price</th>
                          <th>Qty</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($variants as $variant): ?>
                          <?php
                          $variantId = (string) ($variant['id'] ?? '');
                          $variantKey = preg_replace('/[^a-zA-Z0-9_-]/', '', $variantId);
                          $variantFormId = 'variant-' . $variantKey;
                          $variantQtyId = 'variant-qty-' . $variantKey;
                          $variantName = $variant['name'] ?? 'Variant';
                          $variantSku = (string) ($variant['sku'] ?? '');
                          $variantPrice = $variant['price'] ?? $product['price'] ?? 0;
                          ?>
                          <tr>
                            <td>
                              <form id="<?php echo htmlspecialchars($variantFormId, ENT_QUOTES); ?>" method="POST">
                                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                                <input type="hidden" name="productId" value="<?php echo htmlspecialchars($productId, ENT_QUOTES); ?>" />
                                <input type="hidden" name="variantId" value="<?php echo htmlspecialchars($variantId, ENT_QUOTES); ?>" />
                                <?php if ($isService): ?>
                                  <input type="hidden" name="arrivalDate" value="" class="sync-arrival-date" />
                                <?php endif; ?>
                              </form>
                              <div><?php echo htmlspecialchars($variantName, ENT_QUOTES); ?></div>
                            </td>
                            <td>$<?php echo number_format((float) $variantPrice, 2); ?></td>
                            <td>
                              <label class="visually-hidden" for="<?php echo htmlspecialchars($variantQtyId, ENT_QUOTES); ?>">Quantity</label>
                              <select id="<?php echo htmlspecialchars($variantQtyId, ENT_QUOTES); ?>" name="quantity" form="<?php echo htmlspecialchars($variantFormId, ENT_QUOTES); ?>" class="product-qty-input">
                                <?php for ($qi = 1; $qi <= 25; $qi++): ?><option value="<?php echo $qi; ?>"><?php echo $qi; ?></option><?php endfor; ?>
                              </select>
                            </td>
                            <td>
                              <div class="favorite-actions favorite-actions--inline">
                                <button class="btn" type="submit" form="<?php echo htmlspecialchars($variantFormId, ENT_QUOTES); ?>">Add</button>
                                <div class="favorite-wrap">
                                  <div class="favorite-message-inline" data-favorite-message hidden>
                                    Please Sign-In to Select Favorites.
                                    <a href="/login.php">Sign in</a> or <a href="/register.php">Register</a>
                                  </div>
                                  <button
                                    type="button"
                                    class="favorite-btn favorite-btn--small"
                                    data-favorite
                                    data-product-id="<?php echo htmlspecialchars($productId, ENT_QUOTES); ?>"
                                    data-variant-id="<?php echo htmlspecialchars($variantId, ENT_QUOTES); ?>"
                                    aria-label="Add to favorites"
                                  >
                                    <svg class="favorite-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                  </button>
                                  <div class="favorite-dropdown" data-favorite-menu hidden></div>
                                </div>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </details>
            </div>
          </div>
        <?php endif; ?>
        <?php foreach ($relatedGroups as $groupLabel => $groupProducts): ?>
          <div class="associated-group">
            <div class="associated-group-list">
              <?php foreach ($groupProducts as $related): ?>
                <?php
                $relatedId = (string) ($related['id'] ?? '');
                $associatedVariants = $relatedId !== '' ? site_get_product_variants($relatedId) : [];
                $hasAssociatedVariants = !empty($associatedVariants);
                $relatedIsService = !empty($related['service']);
                $relatedDaysOut = (int) ($related['daysOut'] ?? 0);
                $relatedFirstDate = $relatedIsService
                    ? gmdate('Y-m-d', strtotime('+' . max(0, $relatedDaysOut) . ' days'))
                    : '';
                ?>
                <details class="variant-dropdown">
                  <summary class="associated-summary">
                    <span class="variant-toggle" aria-hidden="true"></span>
                    <div class="associated-product-media">
                      <?php if (!empty($related['imageUrl'])): ?>
                        <img class="product-thumb" src="<?php echo htmlspecialchars($related['imageUrl'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($related['name'] ?? 'Product', ENT_QUOTES); ?>" />
                      <?php else: ?>
                        <div class="image-placeholder">No image</div>
                      <?php endif; ?>
                    </div>
                    <div class="associated-product-info">
                      <div class="associated-product-name"><?php echo htmlspecialchars($related['name'] ?? 'Product', ENT_QUOTES); ?></div>
                    </div>
                  </summary>
                  <div class="variant-dropdown-body">
                    <div class="card associated-product">
                      <table class="table">
                        <thead>
                          <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Qty</th>
                            <th></th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php if ($hasAssociatedVariants): ?>
                            <?php foreach ($associatedVariants as $variant): ?>
                              <?php
                              $assocFormId = 'assoc-' . $relatedId . '-variant-' . ($variant['id'] ?? '');
                              $assocQtyId = 'assoc-qty-' . $relatedId . '-' . ($variant['id'] ?? '');
                              $assocName = $variant['name'] ?? 'Variant';
                              $assocSku = $variant['sku'] ?? '';
                              $assocPrice = $variant['price'] ?? $related['price'] ?? 0;
                              ?>
                              <tr>
                                <td>
                                  <form id="<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>" method="POST">
                                    <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                                    <input type="hidden" name="productId" value="<?php echo htmlspecialchars($relatedId, ENT_QUOTES); ?>" />
                                    <input type="hidden" name="variantId" value="<?php echo htmlspecialchars($variant['id'] ?? '', ENT_QUOTES); ?>" />
                                  </form>
                                  <div><?php echo htmlspecialchars($assocName, ENT_QUOTES); ?></div>
                                </td>
                                <td>$<?php echo number_format((float) $assocPrice, 2); ?></td>
                                <td>
                                  <label class="visually-hidden" for="<?php echo htmlspecialchars($assocQtyId, ENT_QUOTES); ?>">Quantity</label>
                                  <select id="<?php echo htmlspecialchars($assocQtyId, ENT_QUOTES); ?>" name="quantity" form="<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>" class="product-qty-input">
                                    <?php for ($qi = 1; $qi <= 25; $qi++): ?><option value="<?php echo $qi; ?>"><?php echo $qi; ?></option><?php endfor; ?>
                                  </select>
                                </td>
                                <td>
                                  <?php if ($relatedIsService && $isSignedIn): ?>
                                    <label class="visually-hidden" for="assoc-arrival-<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>">Arrival Date</label>
                                    <input
                                      type="date"
                                      id="assoc-arrival-<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>"
                                      name="arrivalDate"
                                      class="table-date-input"
                                      min="<?php echo htmlspecialchars($relatedFirstDate, ENT_QUOTES); ?>"
                                      value="<?php echo htmlspecialchars($relatedFirstDate, ENT_QUOTES); ?>"
                                      required
                                      data-arrival-target="main"
                                      form="<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>"
                                    />
                                  <?php elseif ($relatedIsService): ?>
                                    <div class="service-auth-notice">
                                      <p>You must sign in to book services. <a href="/login.php">Sign in</a> or <a href="/login.php?register=1">Register</a></p>
                                    </div>
                                  <?php endif; ?>
                                  <div class="favorite-actions favorite-actions--inline">
                                    <button class="btn" type="submit" form="<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>">Add</button>
                                    <div class="favorite-wrap">
                                      <div class="favorite-message-inline" data-favorite-message hidden>
                                        Please Sign-In to Select Favorites.
                                        <a href="/login.php">Sign in</a> or <a href="/register.php">Register</a>
                                      </div>
                                      <button
                                        type="button"
                                        class="favorite-btn favorite-btn--small"
                                        data-favorite
                                        data-product-id="<?php echo htmlspecialchars($relatedId, ENT_QUOTES); ?>"
                                        data-variant-id="<?php echo htmlspecialchars($variant['id'] ?? '', ENT_QUOTES); ?>"
                                        aria-label="Add to favorites"
                                      >
                                        <svg class="favorite-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                      </button>
                                      <div class="favorite-dropdown" data-favorite-menu hidden></div>
                                    </div>
                                  </div>
                                </td>
                              </tr>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <?php
                            $assocFormId = 'assoc-' . $relatedId . '-base';
                            $assocQtyId = 'assoc-qty-' . $relatedId;
                            $assocPrice = $related['price'] ?? 0;
                            ?>
                            <tr>
                              <td>
                                <form id="<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>" method="POST">
                                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                                  <input type="hidden" name="productId" value="<?php echo htmlspecialchars($relatedId, ENT_QUOTES); ?>" />
                                </form>
                                <div><?php echo htmlspecialchars($related['name'] ?? 'Product', ENT_QUOTES); ?></div>
                              </td>
                              <td>$<?php echo number_format((float) $assocPrice, 2); ?></td>
                              <td>
                                <label class="visually-hidden" for="<?php echo htmlspecialchars($assocQtyId, ENT_QUOTES); ?>">Quantity</label>
                                <select id="<?php echo htmlspecialchars($assocQtyId, ENT_QUOTES); ?>" name="quantity" form="<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>" class="product-qty-input">
                                  <?php for ($qi = 1; $qi <= 25; $qi++): ?><option value="<?php echo $qi; ?>"><?php echo $qi; ?></option><?php endfor; ?>
                                </select>
                              </td>
                              <td>
                                <?php if ($relatedIsService && $isSignedIn): ?>
                                  <label class="visually-hidden" for="assoc-arrival-<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>">Arrival Date</label>
                                  <input
                                    type="date"
                                    id="assoc-arrival-<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>"
                                    name="arrivalDate"
                                    class="table-date-input"
                                    min="<?php echo htmlspecialchars($relatedFirstDate, ENT_QUOTES); ?>"
                                    value="<?php echo htmlspecialchars($relatedFirstDate, ENT_QUOTES); ?>"
                                    required
                                    data-arrival-target="main"
                                    form="<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>"
                                  />
                                <?php elseif ($relatedIsService): ?>
                                  <div class="service-auth-notice">
                                    <p>You must sign in to book services. <a href="/login.php">Sign in</a> or <a href="/login.php?register=1">Register</a></p>
                                  </div>
                                <?php endif; ?>
                                <div class="favorite-actions favorite-actions--inline">
                                  <button class="btn" type="submit" form="<?php echo htmlspecialchars($assocFormId, ENT_QUOTES); ?>">Add</button>
                                  <div class="favorite-wrap">
                                    <div class="favorite-message-inline" data-favorite-message hidden>
                                      Please Sign-In to Select Favorites.
                                      <a href="/login.php">Sign in</a> or <a href="/register.php">Register</a>
                                    </div>
                                    <button
                                      type="button"
                                      class="favorite-btn favorite-btn--small"
                                      data-favorite
                                      data-product-id="<?php echo htmlspecialchars($relatedId, ENT_QUOTES); ?>"
                                      aria-label="Add to favorites"
                                    >
                                      <svg class="favorite-icon" width="14" height="14" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path d="M16.8 3.69a4.4 4.4 0 0 0-6.22 0L10 4.26l-.58-.58a4.4 4.4 0 0 0-6.22 6.22l.58.58L10 17.38l6.22-6.22.58-.58a4.4 4.4 0 0 0 0-6.22z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                      </svg>
                                    </button>
                                    <div class="favorite-dropdown" data-favorite-menu hidden></div>
                                  </div>
                                </div>
                              </td>
                            </tr>
                          <?php endif; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </details>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>

    <?php if (!empty($product['longDescription'])): ?>
      <section class="panel">
        <div class="card">
          <h3>Description</h3>
          <div class="product-long-description">
            <?php
              $desc = (string) $product['longDescription'];
              $desc = str_replace(['\\n', '\n'], "\n", $desc);
              $desc = strip_tags($desc, '<strong><em><b><i><u><br><p><ul><ol><li><a><h1><h2><h3><h4><h5><h6><span><div><table><tr><td><th><thead><tbody><img><hr><blockquote><sub><sup>');
              // Remove blank lines between/around HTML tags to prevent extra spacing
              $desc = preg_replace('/\n\s*\n/', "\n", $desc);
              $desc = preg_replace('/>\s*\n\s*</', '><', $desc);
              // Only nl2br for text outside HTML block elements
              if (strpos($desc, '<ul') !== false || strpos($desc, '<ol') !== false || strpos($desc, '<p') !== false) {
                  // Split on block tags, nl2br only the plain text parts
                  $desc = preg_replace('/(?<!\>)\n(?!\s*<)/', '<br>' . "\n", $desc);
                  echo $desc;
              } else {
                  echo nl2br($desc);
              }
            ?>
          </div>
        </div>
      </section>
    <?php endif; ?>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
  <?php if (count($productImages) > 1): ?>
    <script>
      (function () {
        var mainImage = document.getElementById('product-main-image')
        if (!mainImage) {
          return
        }
        var thumbs = document.querySelectorAll('[data-gallery-thumb]')
        for (var i = 0; i < thumbs.length; i += 1) {
          thumbs[i].addEventListener('click', function (event) {
            var button = event.currentTarget
            var nextSrc = button.getAttribute('data-src') || ''
            if (!nextSrc) {
              return
            }
            mainImage.src = nextSrc
            for (var j = 0; j < thumbs.length; j += 1) {
              thumbs[j].classList.remove('is-active')
              thumbs[j].setAttribute('aria-pressed', 'false')
            }
            button.classList.add('is-active')
            button.setAttribute('aria-pressed', 'true')
          })
        }
      })()
    </script>
  <?php endif; ?>
  <script>
    (function () {
      var mainArrival = document.getElementById('main-arrival-date')
      if (mainArrival) {
        var syncTargets = document.querySelectorAll('.sync-arrival-date')
        var syncArrival = function () {
          var value = mainArrival.value || ''
          for (var i = 0; i < syncTargets.length; i += 1) {
            syncTargets[i].value = value
          }
        }
        mainArrival.addEventListener('change', syncArrival)
        mainArrival.addEventListener('input', syncArrival)
      }
      function showProductFormError(msg) {
        var errEl = document.getElementById('product-form-error')
        if (errEl) {
          errEl.textContent = msg
          errEl.style.display = ''
          errEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
        }
      }

      var postForms = document.querySelectorAll('form[method="POST"]')
      for (var f = 0; f < postForms.length; f += 1) {
        (function (form) {
          form.addEventListener('submit', function (e) {
            // Validate service date inputs
            var dateInput = form.querySelector('input[type="date"][name="arrivalDate"][required]')
            if (!dateInput && form.id) {
              dateInput = document.querySelector('input[type="date"][name="arrivalDate"][required][form="' + form.id + '"]')
            }
            if (dateInput && !dateInput.value) {
              e.preventDefault()
              showProductFormError('Please select an arrival date before adding to cart.')
              return
            }
            var syncedInput = form.querySelector('.sync-arrival-date')
            if (syncedInput && !syncedInput.value) {
              e.preventDefault()
              showProductFormError('Please select an arrival date before adding to cart.')
              return
            }

            // Submit via fetch so the page doesn't reload and scroll position is preserved
            e.preventDefault()
            var submitBtn = e.submitter || form.querySelector('button[type="submit"]')
            var originalText = submitBtn ? submitBtn.textContent : 'Add'
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = '...' }

            var formData = new FormData(form)
            formData.append('_ajax', '1')

            fetch(window.location.href, { method: 'POST', body: formData })
              .then(function (resp) { return resp.json() })
              .then(function (data) {
                if (submitBtn) { submitBtn.disabled = false }
                if (data.success) {
                  if (submitBtn) {
                    submitBtn.textContent = 'Added!'
                    setTimeout(function () { submitBtn.textContent = originalText }, 1500)
                  }
                } else {
                  if (submitBtn) { submitBtn.textContent = originalText }
                  showProductFormError(data.message || 'Could not add to cart.')
                }
              })
              .catch(function () {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText }
                showProductFormError('Could not add to cart. Please try again.')
              })
          })
        })(postForms[f])
      }

      if (window.Favorites && typeof Favorites.init === 'function') {
        Favorites.init({ csrfToken: <?php echo json_encode($csrf); ?>, isSignedIn: <?php echo $isSignedIn ? 'true' : 'false'; ?> })
      }
    })()
  </script>
</body>
</html>
