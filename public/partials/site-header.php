<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/site_auth.php';
require_once __DIR__ . '/../../src/api_helpers.php';
require_once __DIR__ . '/../../src/store.php';
$siteUser = site_current_user();
$_siteName = opd_site_name();
$_cartCount = site_cart_count();
?>
<div class="site-header-wrapper">
  <div class="topbar<?php echo $siteUser ? ' topbar--signed-in' : ''; ?>">
    <div class="topbar-inner">
      <div class="topbar-shipping">Nation Wide Shipping - Oklahoma Same Day Delivery</div>
      <div class="topbar-actions">
        <?php if ($siteUser): ?>
          <span>Howdy, <?php echo htmlspecialchars($siteUser['name'] ?? 'Customer', ENT_QUOTES); ?></span>
          <a class="topbar-link" href="/dashboard.php">Account</a>
          <a class="topbar-link" href="/logout.php">Log out</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <header class="site-header">
    <div class="site-header-inner">
      <a class="logo" href="/index.php">
        <img src="/assets/Oil-Patch-Depot-Logo_New.jpg" alt="<?php echo htmlspecialchars($_siteName, ENT_QUOTES); ?>" class="logo-image">
      </a>

      <form class="search-bar" action="/products.php" method="get" role="search">
        <input
          type="text"
          name="q"
          placeholder="Search products..."
          class="search-input"
          value="<?php echo htmlspecialchars($_GET['q'] ?? '', ENT_QUOTES); ?>"
          autocomplete="off"
          aria-autocomplete="list"
          aria-expanded="false"
          aria-haspopup="listbox"
          aria-controls="search-suggestions"
        >
        <button type="submit" class="search-button">
          <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M9 17A8 8 0 1 0 9 1a8 8 0 0 0 0 16zM18 18l-4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </button>
        <div class="search-suggestions" id="search-suggestions" role="listbox" hidden></div>
      </form>

      <div class="header-actions">
        <?php if ($siteUser): ?>
          <a href="/dashboard.php" class="header-action-link" title="Account">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM3 19a7 7 0 1 1 14 0H3z"/>
            </svg>
          </a>
        <?php else: ?>
          <a href="/login.php" class="header-action-link" title="Sign In">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
              <path d="M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM3 19a7 7 0 1 1 14 0H3z"/>
            </svg>
          </a>
        <?php endif; ?>

        <a href="/cart.php" class="header-action-link header-cart-link" title="Cart">
          <svg width="20" height="20" viewBox="0 0 20 18" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
            <path d="M0 1a1 1 0 0 1 1-1h2.4a1 1 0 0 1 .98.8l.35 1.7h13.57a1 1 0 0 1 .98 1.22l-1.5 7.5a1 1 0 0 1-.98.78H7.26a1 1 0 0 1-.98-.8L3.6 2.5H1A1 1 0 0 1 0 1zm7 12a2 2 0 1 1 0 4 2 2 0 0 1 0-4zm9 0a2 2 0 1 1 0 4 2 2 0 0 1 0-4z"/>
          </svg>
          <?php if ($_cartCount > 0): ?>
            <span class="cart-badge" data-cart-count="<?php echo $_cartCount; ?>"><?php echo $_cartCount; ?></span>
          <?php endif; ?>
        </a>
      </div>
    </div>
  </header>

  <nav class="category-nav">
    <div class="category-nav-inner">
      <a href="/category.php?category=AutoBailer%20Artificial%20Lift">AutoBailer Artificial Lift</a>
      <a href="/category.php?category=Parts">Parts</a>
      <a href="/category.php?category=Tools">Tools</a>
      <a href="/category.php?category=Services">Services</a>
      <a href="/category.php?category=Supplies">Supplies</a>
      <a href="/category.php?category=Used%20Equipment">Used Equipment</a>
    </div>
  </nav>
</div>
