<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/catalog.php';
require_once __DIR__ . '/../config/config.php';

$user = opd_require_role(['admin', 'manager']);
$csrf = opd_csrf_token();

// Prevent browser from caching this page so stale sessions don't confuse users
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>OPD Admin</title>
  <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
  <meta name="product-categories" content="<?php echo htmlspecialchars(json_encode(opd_product_categories(), JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="/assets/css/admin.css?v=20260315d" />
</head>
<body>
  <header class="top-bar">
    <div class="brand">
      <span class="brand-badge">OPD</span>
      <div>
        <div class="brand-title">Admin Command</div>
        <div class="brand-sub"><?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></div>
      </div>
    </div>
    <div class="top-actions">
      <span class="range-pill">Last 30 days</span>
      <span class="user-pill"><?php echo htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES); ?></span>
      <button class="primary-btn">New Product</button>
      <button class="ghost-btn">Run Report</button>
      <a class="ghost-btn" href="/admin-charge.php">Manual Charge</a>
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
            <select id="products-category-filter" aria-label="Filter by category"></select>
            <button class="ghost-btn" id="import-products-btn" type="button">Import Products</button>
            <button class="ghost-btn" id="import-variants-btn" type="button">Import Variants</button>
            <button class="ghost-btn" id="import-images-btn" type="button">Import Images</button>
            <button class="ghost-btn" id="export-products-btn" type="button">Export</button>
            <button class="ghost-btn" id="products-add" type="button">Add New Product</button>
            <button class="ghost-btn" id="products-move" type="button">Move</button>
            <button class="primary-btn" id="products-save" type="button">Save Changes</button>
            <button class="ghost-btn" data-refresh="products">Refresh</button>
          </div>
        </div>
        <div class="panel-body" id="products-panel"></div>
      </section>

      <!-- Import Modal -->
      <div id="import-modal" class="import-modal-overlay" style="display:none;">
        <div class="import-modal">
          <div class="import-modal-header">
            <h3 id="import-modal-title">Import</h3>
            <button class="import-modal-close" id="import-modal-close" type="button">&times;</button>
          </div>
          <div class="import-modal-body">
            <div class="import-modal-field">
              <label>Mode</label>
              <select id="import-mode">
                <option value="add">Add New (skip existing)</option>
                <option value="update">Update Existing (skip new)</option>
              </select>
            </div>
            <div class="import-modal-field">
              <label>CSV File</label>
              <input type="file" id="import-csv-file" accept=".csv" />
            </div>
            <div class="import-modal-field">
              <a id="import-example-link" href="#" class="import-example-link" download target="_blank">Download Example CSV</a>
            </div>
            <div id="import-modal-message" class="import-modal-message" style="display:none;"></div>
          </div>
          <div class="import-modal-footer">
            <button class="ghost-btn" id="import-modal-cancel" type="button">Cancel</button>
            <button class="primary-btn" id="import-modal-submit" type="button">Import</button>
          </div>
        </div>
      </div>

      <!-- Export Products Modal -->
      <div id="export-modal" class="import-modal-overlay" style="display:none;">
        <div class="import-modal export-modal">
          <div class="import-modal-header">
            <h3>Export Products</h3>
            <button class="import-modal-close" id="export-modal-close" type="button">&times;</button>
          </div>
          <div class="import-modal-body">
            <div class="import-modal-field">
              <label>Fields to Export</label>
              <div class="export-checkbox-grid" id="export-fields-list"></div>
            </div>
            <div class="import-modal-field">
              <label>Categories to Export</label>
              <div class="export-checkbox-grid" id="export-categories-list"></div>
            </div>
            <div class="import-modal-field">
              <label>Types to Export</label>
              <div class="export-checkbox-grid" id="export-types-list"></div>
            </div>
            <div class="import-modal-field">
              <label class="export-checkbox"><input type="checkbox" id="export-include-variants" /> Include Variants</label>
            </div>
          </div>
          <div class="import-modal-footer">
            <button class="ghost-btn" id="export-modal-cancel" type="button">Cancel</button>
            <button class="primary-btn" id="export-modal-submit" type="button">Export CSV</button>
          </div>
        </div>
      </div>

      <section class="resource-stack" id="resource-stack"></section>

      <section id="pages" class="panel">
        <div class="panel-header">
          <div>
            <div class="eyebrow">Content</div>
            <h2>Page Builder</h2>
            <p>Create and manage custom pages with text, images, videos, and headlines.</p>
          </div>
          <div class="panel-actions">
            <button class="ghost-btn" id="pages-add" type="button">+ New Page</button>
          </div>
        </div>
        <div class="panel-body">
          <div id="pages-list"></div>
        </div>
      </section>

      <section id="page-editor" class="panel" style="display: none;">
        <div class="panel-header">
          <div>
            <div class="eyebrow">Page Builder</div>
            <h2 id="page-editor-title">Create Page</h2>
          </div>
          <div class="panel-actions">
            <button class="ghost-btn" id="page-editor-cancel" type="button">Cancel</button>
            <button class="primary-btn" id="page-editor-save" type="button">Save Page</button>
          </div>
        </div>
        <div class="panel-body">
          <div class="form-grid cols-2" style="margin-bottom: 24px;">
            <div>
              <label for="page-title">Page Title</label>
              <input type="text" id="page-title" placeholder="Enter page title" />
            </div>
            <div>
              <label for="page-slug">Page Address (URL)</label>
              <div style="display: flex; align-items: center; gap: 4px;">
                <span style="color: #666;">/page/</span>
                <input type="text" id="page-slug" placeholder="my-page" style="flex: 1;" />
              </div>
            </div>
            <div>
              <label for="page-template">Template</label>
              <select id="page-template">
                <option value="custom">Custom Layout</option>
                <option value="single-column">Single Column</option>
                <option value="two-column">Two Column</option>
                <option value="hero-content">Hero + Content</option>
              </select>
            </div>
            <div>
              <label for="page-status">Status</label>
              <select id="page-status">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
              </select>
            </div>
            <div class="span-2">
              <label for="page-meta">Meta Description</label>
              <textarea id="page-meta" rows="2" placeholder="Brief description for search engines"></textarea>
            </div>
          </div>

          <div class="page-builder-layout">
            <div class="page-builder-editor">
              <h3 style="margin: 0 0 16px; font-size: 15px; color: #333;">Page Rows</h3>

              <div class="page-builder-controls">
                <div class="page-builder-control">
                  <label for="page-row-count">Number of Rows</label>
                  <input type="number" id="page-row-count" min="1" value="1" />
                </div>
                <div class="page-builder-control">
                  <button class="ghost-btn" id="page-row-add" type="button">+ Add Row</button>
                </div>
              </div>

              <div id="page-rows-list"></div>
            </div>

            <div class="page-builder-preview">
              <div class="page-builder-preview-header">
                <span>Live Preview</span>
              </div>
              <div class="page-builder-preview-content" id="page-preview-content">
                <div class="page-builder-preview-empty">Add sections to see a preview</div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section id="used-equipment" class="panel">
        <div class="panel-header">
          <div>
            <div class="eyebrow">Marketplace</div>
            <h2>Used Equipment</h2>
            <p>Review and approve user-submitted equipment listings.</p>
          </div>
          <div class="panel-actions">
            <button class="ghost-btn" id="used-equip-refresh" type="button">Refresh</button>
          </div>
        </div>
        <div class="panel-body" id="used-equip-panel"></div>
      </section>

      <section id="sales-tax" class="panel">
        <div class="panel-header">
          <div>
            <div class="eyebrow">Finance</div>
            <h2>Sales Tax Rates</h2>
            <p>Manage tax rates by zip code group.</p>
          </div>
          <div class="panel-actions">
            <button class="ghost-btn" id="tax-add-group" type="button">+ Add Rate Group</button>
          </div>
        </div>
        <div class="panel-body" id="tax-panel"></div>
      </section>

      <section id="invoices" class="panel">
        <div class="panel-header">
          <div>
            <div class="eyebrow">Finance</div>
            <h2>Invoices</h2>
            <p>Track and manage invoice payments.</p>
          </div>
          <div class="panel-actions">
            <select id="invoice-status-filter">
              <option value="">All</option>
              <option value="pending">Pending</option>
              <option value="overdue">Overdue</option>
              <option value="paid">Paid</option>
            </select>
            <button class="ghost-btn" id="invoice-refresh" type="button">Refresh</button>
          </div>
        </div>
        <div class="panel-body" id="invoice-panel"></div>
      </section>

      <section id="db-health" class="panel">
        <div class="panel-header">
          <div>
            <div class="eyebrow">Database</div>
            <h2>Schema Health Check</h2>
            <p>Verify required tables and columns before changes go live.</p>
          </div>
          <div class="panel-actions">
            <button class="ghost-btn" id="db-health-run" type="button">Run Check</button>
          </div>
        </div>
        <div class="panel-body" id="db-health-panel"></div>
      </section>
    </main>
  </div>

  <script src="/assets/js/admin.js?v=20260331a" nonce="<?php echo opd_csp_nonce(); ?>"></script>
</body>
</html>
