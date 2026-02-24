<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$message = '';
$messageIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $postedProductId = $_POST['productId'] ?? '';
    $quantity = (int) ($_POST['quantity'] ?? 1);
    if (!is_string($postedProductId) || $postedProductId === '') {
        $message = 'Select a valid product.';
        $messageIsError = true;
    } else {
        $selectedProduct = site_get_product($postedProductId);
        if (!$selectedProduct) {
            $message = 'Product not found.';
            $messageIsError = true;
        } else {
            $hasVariants = !empty(site_get_product_variants($postedProductId));
            $hasAssociations = !empty(site_get_related_products($postedProductId, 1));
            if ($hasVariants || $hasAssociations) {
                $message = 'Select a product variation for this item.';
                $messageIsError = true;
            } else {
                site_add_to_cart($postedProductId, $quantity);
                $message = 'Added to cart.';
            }
        }
    }
}

$categories = site_get_categories();
$selected = $_GET['category'] ?? ($categories[0] ?? '');
$limit = 78;
$products = $selected ? site_get_products($selected, null, $limit + 1) : [];
// Filter out sold-out Used Equipment products
if ($selected === 'Used Equipment') {
    $products = array_filter($products, function ($p) {
        $inv = (int) ($p['inventory'] ?? 0);
        if ($inv <= 0) {
            return true; // no inventory tracking, show it
        }
        $sold = site_equipment_sold_quantity($p['id']);
        return $sold < $inv;
    });
    $products = array_values($products);
}
$hasMore = count($products) > $limit;
if ($hasMore) {
    $products = array_slice($products, 0, $limit);
}
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Categories - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel">
      <div class="section-title">
        <div>
          <h2>Categories</h2>
        </div>
        <form method="GET">
          <select name="category" onchange="this.form.submit()">
            <?php foreach ($categories as $category): ?>
              <option value="<?php echo htmlspecialchars($category, ENT_QUOTES); ?>" <?php echo $category === $selected ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($category, ENT_QUOTES); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>
      <?php if ($selected === 'Used Equipment'): ?>
        <div class="equip-list-banner">
          <span class="equip-list-text">Got Equipment to Sell?</span>
          <?php
          $currentUser = site_current_user();
          if ($currentUser):
          ?>
            <a class="btn equip-list-btn" href="/dashboard-equipment.php">List Here</a>
          <?php else: ?>
            <a class="btn equip-list-btn" href="/login.php?redirect=/dashboard-equipment.php&equip=1">List Here</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <?php if ($message): ?>
        <div class="notice <?php echo $messageIsError ? 'is-error' : ''; ?>">
          <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
        </div>
      <?php endif; ?>
      <div class="grid category-grid" id="category-grid">
        <?php foreach ($products as $product): ?>
          <?php
          $productId = $product['id'] ?? '';
          $hasVariants = $productId ? !empty(site_get_product_variants($productId)) : false;
          $hasAssociations = $productId ? !empty(site_get_related_products($productId, 1)) : false;
          $isService = !empty($product['service']);
          $showFromPrice = $hasVariants || $hasAssociations || $isService;
          $showQuickAdd = !$showFromPrice;
          $productUrl = '/product.php?id=' . urlencode((string) $productId);
          $formAction = $selected !== '' ? '/category.php?category=' . urlencode($selected) : '/category.php';
          $qtyInputId = 'qty-' . ($productId ?: 'product');
          ?>
          <div class="card category-card">
            <a class="product-thumb-link" href="<?php echo htmlspecialchars($productUrl, ENT_QUOTES); ?>">
              <?php if (!empty($product['imageUrl'])): ?>
                <img class="product-thumb" src="<?php echo htmlspecialchars($product['imageUrl'], ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?>" />
              <?php else: ?>
                <div class="image-placeholder">No image</div>
              <?php endif; ?>
            </a>
            <h3><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?></h3>
            <div class="category-card-footer">
              <div class="price">
                <?php if ($showFromPrice): ?>
                  <span class="price-from">from</span>
                <?php endif; ?>
                $<?php echo number_format((float) ($product['price'] ?? 0), 2); ?>
              </div>
              <?php if ($showQuickAdd || $showFromPrice): ?>
                <div class="category-card-actions">
                  <?php if ($showQuickAdd): ?>
                    <form method="POST" class="product-card-actions" action="<?php echo htmlspecialchars($formAction, ENT_QUOTES); ?>">
                      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                      <input type="hidden" name="productId" value="<?php echo htmlspecialchars($productId, ENT_QUOTES); ?>" />
                      <button class="btn" type="submit">Add</button>
                      <label class="visually-hidden" for="<?php echo htmlspecialchars($qtyInputId, ENT_QUOTES); ?>">Quantity</label>
                      <input class="product-qty-input" type="number" id="<?php echo htmlspecialchars($qtyInputId, ENT_QUOTES); ?>" name="quantity" value="1" min="1" />
                    </form>
                  <?php endif; ?>
                  <?php if ($showFromPrice): ?>
                    <a class="btn product-card-link" href="<?php echo htmlspecialchars($productUrl, ENT_QUOTES); ?>">View details</a>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
        <?php if (!$products): ?>
          <div class="notice">No products in this category yet.</div>
        <?php endif; ?>
      </div>
      <?php if ($hasMore): ?>
        <div class="category-load-more">
          <button class="btn-outline" type="button" id="category-load-more" data-offset="<?php echo count($products); ?>">
            Load more
          </button>
        </div>
      <?php endif; ?>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
  <script>
    (function () {
      var loadBtn = document.getElementById('category-load-more')
      var grid = document.getElementById('category-grid')
      if (!loadBtn || !grid) {
        return
      }
      var category = <?php echo json_encode($selected); ?>
      var csrf = <?php echo json_encode($csrf); ?>
      var formAction = <?php echo json_encode($selected !== '' ? '/category.php?category=' . urlencode($selected) : '/category.php'); ?>
      var offset = parseInt(loadBtn.getAttribute('data-offset') || '0', 10)
      var limit = <?php echo (int) $limit; ?>

      function buildCard(item) {
        var card = document.createElement('div')
        card.className = 'card category-card'

        var link = document.createElement('a')
        link.className = 'product-thumb-link'
        link.href = '/product.php?id=' + encodeURIComponent(item.id || '')
        if (item.imageUrl) {
          var img = document.createElement('img')
          img.className = 'product-thumb'
          img.src = item.imageUrl
          img.alt = item.name || 'Product'
          link.appendChild(img)
        } else {
          var placeholder = document.createElement('div')
          placeholder.className = 'image-placeholder'
          placeholder.textContent = 'No image'
          link.appendChild(placeholder)
        }
        card.appendChild(link)

        var title = document.createElement('h3')
        title.textContent = item.name || 'Product'
        card.appendChild(title)

        var footer = document.createElement('div')
        footer.className = 'category-card-footer'

        var priceWrap = document.createElement('div')
        priceWrap.className = 'price'
        var isService = item.service === true || item.service === 1 || item.service === '1'
        if (item.hasVariants || item.hasAssociations || isService) {
          var from = document.createElement('span')
          from.className = 'price-from'
          from.textContent = 'from'
          priceWrap.appendChild(from)
        }
        priceWrap.appendChild(document.createTextNode('$' + Number(item.price || 0).toFixed(2)))
        footer.appendChild(priceWrap)

        var actions = document.createElement('div')
        actions.className = 'category-card-actions'

        var canQuickAdd = !(item.hasVariants || item.hasAssociations || isService)
        if (canQuickAdd) {
          var form = document.createElement('form')
          form.method = 'POST'
          form.className = 'product-card-actions'
          form.action = formAction

          var csrfInput = document.createElement('input')
          csrfInput.type = 'hidden'
          csrfInput.name = '_csrf'
          csrfInput.value = csrf
          form.appendChild(csrfInput)

          var idInput = document.createElement('input')
          idInput.type = 'hidden'
          idInput.name = 'productId'
          idInput.value = item.id || ''
          form.appendChild(idInput)

          var addBtn = document.createElement('button')
          addBtn.className = 'btn'
          addBtn.type = 'submit'
          addBtn.textContent = 'Add'
          form.appendChild(addBtn)

          var qtyLabel = document.createElement('label')
          qtyLabel.className = 'visually-hidden'
          qtyLabel.textContent = 'Quantity'
          form.appendChild(qtyLabel)

          var qtyInput = document.createElement('input')
          qtyInput.className = 'product-qty-input'
          qtyInput.type = 'number'
          qtyInput.name = 'quantity'
          qtyInput.value = '1'
          qtyInput.min = '1'
          form.appendChild(qtyInput)

          actions.appendChild(form)
        } else {
          var viewBtn = document.createElement('a')
          viewBtn.className = 'btn product-card-link'
          viewBtn.href = '/product.php?id=' + encodeURIComponent(item.id || '')
          viewBtn.textContent = 'View details'
          actions.appendChild(viewBtn)
        }

        footer.appendChild(actions)
        card.appendChild(footer)
        return card
      }

      loadBtn.addEventListener('click', function () {
        loadBtn.disabled = true
        loadBtn.textContent = 'Loading...'
        var url = '/api/category_products.php?category=' + encodeURIComponent(category) +
          '&offset=' + encodeURIComponent(offset) +
          '&limit=' + encodeURIComponent(limit)
        fetch(url)
          .then(function (resp) { return resp.json() })
          .then(function (data) {
            var items = Array.isArray(data.items) ? data.items : []
            items.forEach(function (item) {
              grid.appendChild(buildCard(item))
            })
            offset = data.nextOffset || (offset + items.length)
            if (!data.hasMore || !items.length) {
              loadBtn.remove()
              return
            }
            loadBtn.disabled = false
            loadBtn.textContent = 'Load more'
          })
          .catch(function () {
            loadBtn.disabled = false
            loadBtn.textContent = 'Load more'
          })
      })
    })()
  </script>
</body>
</html>
