<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/site_auth.php';
$dashboardUser = site_require_auth();
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
$items = [
    ['label' => 'Dashboard', 'href' => '/dashboard.php', 'file' => 'dashboard.php'],
    ['label' => 'Account', 'href' => '/dashboard-account.php', 'file' => 'dashboard-account.php'],
    ['label' => 'Orders', 'href' => '/dashboard-orders.php', 'file' => 'dashboard-orders.php'],
    ['label' => 'Accounting Codes', 'href' => '/dashboard-accounting-codes.php', 'file' => 'dashboard-accounting-codes.php'],
    ['label' => 'Favorites', 'href' => '/dashboard-favorites.php', 'file' => 'dashboard-favorites.php'],
    ['label' => 'Vendors', 'href' => '/dashboard-vendors.php', 'file' => 'dashboard-vendors.php'],
    ['label' => 'Clients', 'href' => '/dashboard-clients.php', 'file' => 'dashboard-clients.php'],
    ['label' => 'Your Equipment', 'href' => '/dashboard-equipment.php', 'file' => 'dashboard-equipment.php'],
];
?>
<aside class="dashboard-sidebar">
  <nav class="dashboard-menu">
    <?php foreach ($items as $item): ?>
      <?php $active = $current === $item['file'] ? 'is-active' : ''; ?>
      <a class="dashboard-link <?php echo $active; ?>" href="<?php echo $item['href']; ?>">
        <?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>
      </a>
    <?php endforeach; ?>
    <a class="dashboard-link" href="/logout.php">Log out</a>
  </nav>
</aside>
