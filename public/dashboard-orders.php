<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_require_auth();
$orders = site_get_orders_for_user($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Orders - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel">
          <h2>Order history</h2>
          <?php if (!$orders): ?>
            <div class="notice">No orders yet.</div>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Order</th>
                  <th>Status</th>
                  <th>Total</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($orders as $order): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($order['number'] ?? $order['id'], ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($order['status'] ?? 'new', ENT_QUOTES); ?></td>
                    <td>$<?php echo number_format((float) ($order['total'] ?? 0), 2); ?></td>
                    <td><?php echo htmlspecialchars($order['createdAt'] ?? '', ENT_QUOTES); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
