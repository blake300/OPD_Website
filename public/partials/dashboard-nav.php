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
    ['label' => 'My Equipment', 'href' => '/dashboard-equipment.php', 'file' => 'dashboard-equipment.php'],
];
$activeLabel = 'Menu';
foreach ($items as $item) {
    if ($current === $item['file']) {
        $activeLabel = $item['label'];
        break;
    }
}
?>
<aside class="dashboard-sidebar">
  <button class="dashboard-mobile-toggle" type="button" aria-expanded="false" aria-controls="dashboard-nav-menu">
    <span class="dashboard-mobile-toggle-label"><?php echo htmlspecialchars($activeLabel, ENT_QUOTES); ?></span>
    <svg class="dashboard-mobile-toggle-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <polyline points="6 9 12 15 18 9"></polyline>
    </svg>
  </button>
  <nav class="dashboard-menu" id="dashboard-nav-menu">
    <?php foreach ($items as $item): ?>
      <?php $active = $current === $item['file'] ? 'is-active' : ''; ?>
      <a class="dashboard-link <?php echo $active; ?>" href="<?php echo $item['href']; ?>">
        <?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?>
      </a>
    <?php endforeach; ?>
    <a class="dashboard-link" href="/logout.php">Log out</a>
  </nav>
</aside>
<script nonce="<?php echo opd_csp_nonce(); ?>">
  (function () {
    var toggle = document.querySelector('.dashboard-mobile-toggle')
    var menu = document.getElementById('dashboard-nav-menu')
    if (!toggle || !menu) return
    toggle.addEventListener('click', function () {
      var isOpen = menu.classList.toggle('is-open')
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false')
    })
  })()
</script>
