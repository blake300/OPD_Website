<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';

$user = opd_require_role(['admin', 'manager']);
$csrf = opd_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>OPD Admin</title>
  <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="/assets/css/admin.css" />
</head>
<body>
  <header class="top-bar">
    <div class="brand">
      <span class="brand-badge">OPD</span>
      <div>
        <div class="brand-title">Admin Command</div>
        <div class="brand-sub">Oil Patch Depot</div>
      </div>
    </div>
    <div class="top-actions">
      <span class="range-pill">Last 30 days</span>
      <span class="user-pill"><?php echo htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES); ?></span>
      <button class="primary-btn">New Product</button>
      <button class="ghost-btn">Run Report</button>
      <a class="ghost-btn" href="/admin-logout.php">Logout</a>
    </div>
  </header>

  <div class="layout">
    <aside class="sidebar">
      <div class="sidebar-title">Sections</div>
      <nav class="nav" id="nav"></nav>
    </aside>

    <main class="main">
      <section id="dashboard" class="hero">
        <div class="hero-left">
          <div class="eyebrow">Command Center</div>
          <h1>Admin Panel Overview</h1>
          <p>A control surface for sales, inventory, customers, and operations.</p>
          <div class="hero-actions">
            <button class="primary-btn">Review Alerts</button>
            <button class="ghost-btn">Customize Widgets</button>
          </div>
        </div>
        <div class="hero-right" id="metrics"></div>
      </section>

      <section id="products" class="panel">
        <div class="panel-header">
          <div>
            <div class="eyebrow">Products</div>
            <h2>Product Management Workspace</h2>
            <p>Create and maintain SKUs, pricing, inventory, and publish status.</p>
          </div>
          <div class="panel-actions">
            <input id="products-search" placeholder="Search name, SKU, category" />
            <button class="ghost-btn" data-refresh="products">Refresh</button>
          </div>
        </div>
        <div class="panel-body" id="products-panel"></div>
      </section>

      <section class="resource-stack" id="resource-stack"></section>
    </main>
  </div>

  <script src="/assets/js/admin.js"></script>
</body>
</html>
