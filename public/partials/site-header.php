<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/site_auth.php';
$siteUser = site_current_user();
?>
<div class="topbar">
  <div class="topbar-inner">
    <div>Nation Wide Shipping - Oklahoma Same Day Delivery</div>
    <div class="topbar-actions">
      <?php if ($siteUser): ?>
        <span>Howdy, <?php echo htmlspecialchars($siteUser['name'] ?? 'Customer', ENT_QUOTES); ?></span>
        <a class="topbar-link" href="/dashboard.php">Account</a>
        <a class="topbar-link" href="/logout.php">Log out</a>
      <?php else: ?>
        <a class="topbar-link" href="/login.php">Sign in</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<header class="site-header">
  <div class="site-header-inner">
    <a class="logo" href="/index.php">
      <span class="logo-badge">OPD</span>
      <span class="logo-text">OilPatchDepot<span>.com</span></span>
    </a>
    <nav class="nav">
      <a href="/category.php?category=AutoBailer%20Artifical%20Lift">AutoBailer Artifical Lift</a>
      <a href="/category.php?category=Parts">Parts</a>
      <a href="/category.php?category=Tools">Tools</a>
      <a href="/category.php?category=Services">Services</a>
      <a href="/category.php?category=Supplies">Supplies</a>
      <a href="/category.php?category=Used%20Equipment">Used Equipment</a>
      <a class="cta" href="/cart.php">Cart</a>
    </nav>
  </div>
</header>
