<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_require_auth();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $productId = $_POST['productId'] ?? '';
        if ($productId !== '') {
            site_add_favorite($user['id'], $productId);
            $message = 'Added to favorites.';
        }
    }
    if ($action === 'remove') {
        $favoriteId = $_POST['favoriteId'] ?? '';
        if ($favoriteId !== '') {
            site_remove_favorite($favoriteId);
            $message = 'Removed from favorites.';
        }
    }
}

$favorites = site_get_favorites($user['id']);
$products = site_get_products(null, null, 6);
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Favorites - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel">
          <h2>Favorites</h2>
          <?php if ($message): ?>
            <div class="notice"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
          <?php endif; ?>
          <?php if (!$favorites): ?>
            <div class="notice">No favorites yet.</div>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Item</th>
                  <th>Category</th>
                  <th>Price</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($favorites as $favorite): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($favorite['name'], ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($favorite['category'] ?? '', ENT_QUOTES); ?></td>
                    <td>$<?php echo number_format((float) ($favorite['price'] ?? 0), 2); ?></td>
                    <td>
                      <form method="POST">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="action" value="remove" />
                        <input type="hidden" name="favoriteId" value="<?php echo htmlspecialchars($favorite['id'], ENT_QUOTES); ?>" />
                        <button class="btn-outline" type="submit">Remove</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>

        <section class="panel">
          <h2>Add more favorites</h2>
          <div class="grid cols-3">
            <?php foreach ($products as $product): ?>
              <div class="card">
                <h3><?php echo htmlspecialchars($product['name'] ?? 'Product', ENT_QUOTES); ?></h3>
                <div class="price">$<?php echo number_format((float) ($product['price'] ?? 0), 2); ?></div>
                <form method="POST">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                  <input type="hidden" name="action" value="add" />
                  <input type="hidden" name="productId" value="<?php echo htmlspecialchars($product['id'], ENT_QUOTES); ?>" />
                  <button class="btn-outline" type="submit">Favorite</button>
                </form>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
