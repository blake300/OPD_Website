<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_require_auth();
$orders = site_get_orders_for_user($user['id']);
$favorites = site_get_favorites($user['id']);
$clients = site_simple_list('clients', $user['id']);
$equipment = site_simple_list('equipment', $user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="dashboard-card">
          <p>
            Hello <strong><?php echo htmlspecialchars($user['name'] ?? 'Customer', ENT_QUOTES); ?></strong>
            (not <?php echo htmlspecialchars($user['name'] ?? 'Customer', ENT_QUOTES); ?>?
            <a class="highlight" href="/logout.php">Log out</a>)
          </p>
          <p>
            From your account dashboard you can view your recent orders, manage your shipping and billing
            addresses, and edit your password and account details.
          </p>
        </section>

        <section class="panel">
          <h2>Overview</h2>
          <div class="grid cols-3">
            <div class="card">
              <div class="meta">Orders</div>
              <div class="price"><?php echo count($orders); ?></div>
            </div>
            <div class="card">
              <div class="meta">Favorites</div>
              <div class="price"><?php echo count($favorites); ?></div>
            </div>
            <div class="card">
              <div class="meta">Clients</div>
              <div class="price"><?php echo count($clients); ?></div>
            </div>
            <div class="card">
              <div class="meta">Equipment</div>
              <div class="price"><?php echo count($equipment); ?></div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
