<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_current_user();
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $key = $_POST['key'] ?? '';
        $qty = (int) ($_POST['quantity'] ?? 1);
        if ($key !== '') {
            site_update_cart_item($key, $qty);
            $message = 'Cart updated.';
        }
    }
    if ($action === 'remove') {
        $key = $_POST['key'] ?? '';
        if ($key !== '') {
            site_remove_cart_item($key);
            $message = 'Item removed.';
        }
    }
}

$items = site_cart_items();
$total = site_cart_total($items);
$taxableTotal = site_cart_taxable_total($items);
$hasAnyServiceItem = site_cart_has_any_service_items($items);
$isServiceOnlyCart = site_cart_has_only_service_items($items);
$csrf = site_csrf_token();

$clients = [];
$billableClients = [];
$accountingStructure = ['location' => [], 'code1' => [], 'code2' => []];
$userAddress = [
    'address' => '',
    'address2' => '',
    'city' => '',
    'state' => '',
    'zip' => ''
];
$pdo = opd_db();
$hasLargeDelivery = site_cart_has_large_delivery($items);
$cartTotalWeight = site_cart_total_weight($items);
$shippingZones = [];
for ($z = 1; $z <= 3; $z++) {
    $statesRaw = site_get_setting_value('shipping_zone' . $z . '_states');
    $flat = site_get_setting_float('shipping_zone' . $z . '_flat', 0.0);
    $perLb = site_get_setting_float('shipping_zone' . $z . '_per_lb', 0.0);
    $stateList = [];
    if ($statesRaw !== null && $statesRaw !== '') {
        foreach (explode(',', $statesRaw) as $s) {
            $s = strtoupper(trim($s));
            if ($s !== '') {
                $stateList[] = $s;
            }
        }
    }
    $shippingZones[$z] = ['states' => $stateList, 'flat' => $flat, 'perLb' => $perLb];
}
$deliveryZones = require __DIR__ . '/../src/delivery_zones.php';
$deliveryCosts = [
    'small' => [
        1 => site_get_setting_float('delivery_small_zone1', 0.0),
        2 => site_get_setting_float('delivery_small_zone2', 0.0),
        3 => site_get_setting_float('delivery_small_zone3', 0.0),
    ],
    'large' => [
        1 => site_get_setting_float('delivery_large_zone1', 0.0),
        2 => site_get_setting_float('delivery_large_zone2', 0.0),
        3 => site_get_setting_float('delivery_large_zone3', 0.0),
    ],
];

if ($user) {
    $clients = site_simple_list('clients', $user['id']);
    foreach ($clients as $client) {
        if (site_client_is_billable($client)) {
            $billableClients[] = $client;
        }
    }
    $cartId = site_get_cart_id($user['id']);
    $savedClientId = $cartId ? site_get_latest_cart_accounting_client_id($cartId) : null;
    $accountingStructure = site_get_accounting_structure_for_client($user['id'], $savedClientId);
    $stmt = $pdo->prepare(
        'SELECT address, address2, city, state, zip, shippingAddress1, shippingAddress2, shippingCity, shippingState, shippingPostcode FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();
    if ($row) {
        $userAddress = [
            'address' => $row['shippingAddress1'] ?? $row['address'] ?? '',
            'address2' => $row['shippingAddress2'] ?? $row['address2'] ?? '',
            'city' => $row['shippingCity'] ?? $row['city'] ?? '',
            'state' => $row['shippingState'] ?? $row['state'] ?? '',
            'zip' => $row['shippingPostcode'] ?? $row['zip'] ?? '',
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Shopping Cart - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260315c" />
  <style>
    /* Cart UX Improvements */
    .cart-count {
      font-weight: normal;
      color: #666;
      font-size: 0.9em;
    }

    .empty-cart {
      text-align: center;
      padding: 60px 20px;
      background: #f8f9fa;
      border-radius: 8px;
      margin: 40px 0;
    }

    .empty-cart-icon {
      font-size: 64px;
      margin-bottom: 20px;
      opacity: 0.5;
    }

    .empty-cart h3 {
      margin: 0 0 10px;
      color: #333;
    }

    .empty-cart p {
      color: #666;
      margin: 0 0 30px;
    }

    .notice-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
      padding: 12px 16px;
      border-radius: 4px;
      margin-bottom: 20px;
    }

    .notice-info {
      background: #d1ecf1;
      color: #0c5460;
      border: 1px solid #bee5eb;
      padding: 12px 16px;
      border-radius: 4px;
      font-size: 0.9em;
    }

    .help-text {
      display: block;
      font-size: 0.85em;
      color: #666;
      margin-top: 4px;
      font-weight: normal;
    }

    .cart-client label strong {
      display: block;
      margin-bottom: 4px;
      font-size: 1em;
      font-weight: 700;
      color: #111;
    }

    .cart-panel-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .cart-panel-header .btn-continue {
      font-size: 0.8em;
      padding: 6px 12px;
      order: 2;
    }

    .cart-panel-title {
      margin: 0;
      font-size: 1.4em;
      font-weight: 700;
      color: #111;
      order: 1;
    }

    .cart-client-row {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .cart-client select {
      width: auto;
    }

    .cart-client-row .meta {
      margin: 0;
    }

    .cart-groups {
      margin: 20px 0;
      border: none;
      border-radius: 0;
      padding: 0;
    }

    .cart-groups-header {
      padding: 0 0 8px 0;
      background: transparent;
      border-radius: 0;
      border: none;
    }

    .cart-groups-header h3 {
      margin: 0;
      display: inline-block;
      font-size: 1em;
      font-weight: 700;
      color: #111;
    }

    .shipping-options {
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    .shipping-option {
      display: flex;
      flex-direction: column;
      gap: 8px;
      border: 2px solid #dee2e6;
      border-radius: 8px;
      padding: 16px;
      background: #fff;
      transition: all 0.2s;
    }

    .shipping-option:hover {
      border-color: #adb5bd;
    }

    .shipping-option:has(input[name="shipping_method"]:checked),
    .shipping-option.is-selected {
      border-color: #007bff;
      background: #f0f7ff;
      box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.15);
    }

    .radio-row {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      cursor: pointer;
    }

    .radio-row input[type="radio"] {
      margin: 0;
      margin-top: 4px;
      width: 18px;
      height: 18px;
      accent-color: #007bff;
      flex-shrink: 0;
    }

    .shipping-subline {
      padding-top: 4px;
      border-top: 1px solid #e9ecef;
      margin-top: 4px;
    }

    .link-btn {
      background: none;
      border: none;
      padding: 0;
      color: #1c212c;
      font-weight: 600;
      cursor: pointer;
    }

    .link-btn:hover {
      text-decoration: underline;
    }

    .shipping-detail {
      border: 1px solid #e2e2e2;
      border-radius: 8px;
      padding: 12px 14px;
      background: #ffffff;
    }

    .shipping-detail-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 10px;
    }

    .shipping-mode-block {
      margin-bottom: 8px;
    }

    .shipping-mode-block .shipping-mode-panel {
      margin-top: 10px;
      padding: 12px;
      border: 1px solid #e9ecef;
      border-radius: 8px;
      background: #fafbfc;
    }

    .shipping-mode-block .radio-pill {
      width: 100%;
      justify-content: center;
    }

    .radio-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 16px;
      border: 2px solid #dee2e6;
      border-radius: 10px;
      font-size: 0.85rem;
      font-weight: 500;
      color: #495057;
      background: #f8f9fa;
      cursor: pointer;
      transition: all 0.2s;
    }

    .radio-pill:hover {
      border-color: #adb5bd;
      background: #e9ecef;
    }

    .radio-pill:has(input:checked) {
      border-color: #007bff;
      background: #e7f1ff;
      color: #0056b3;
      box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.15);
    }

    .radio-pill input {
      margin: 0;
      accent-color: #007bff;
    }

    .radio-cost.is-prompt {
      color: #1f8a28;
      font-weight: 600;
      cursor: pointer;
    }

    .shipping-detail .notice {
      margin-top: 8px;
    }

    .shipping-detail .notice.is-error {
      background: #fdecea;
      color: #b42318;
      border: 1px solid #f7c0b6;
    }

    .cart-groups-content {
      padding: 0;
    }

    .cart-groups-add {
      margin-top: 8px;
    }

    .cart-groups-add .btn-icon {
      display: inline-flex;
      align-items: center;
      justify-content: center;
    }

    .btn-icon {
      min-width: 40px;
      height: 40px;
      padding: 8px;
      font-size: 1.5em;
      line-height: 1;
      border-radius: 50%;
    }

    .cart-group-row {
      margin-bottom: 20px;
      padding: 16px;
      background: #f8f9fa;
      border-radius: 6px;
      position: relative;
    }

    .cart-group-row .btn-outline {
      font-size: 0.85em;
      padding: 6px 12px;
    }

    .cascading-selects {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .cart-item {
      border-bottom: 1px solid #e0e0e0;
      padding-bottom: 20px;
      margin-bottom: 20px;
    }

    .cart-item:last-child {
      border-bottom: none;
    }

    .image-placeholder {
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f0f0f0;
      font-size: 32px;
      color: #999;
    }

    .cart-item-meta {
      display: flex;
      gap: 16px;
      margin-top: 8px;
      font-size: 0.9em;
    }

    .cart-item-sku {
      color: #666;
    }

    .cart-item-arrival {
      color: #444;
    }

    .cart-qty-wrapper {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .cart-qty-wrapper label {
      margin: 0;
      font-weight: normal;
      color: #666;
    }

    .cart-item-subtotal {
      text-align: right;
      min-width: 100px;
    }

    .btn-link {
      background: none;
      border: none;
      color: #dc3545;
      cursor: pointer;
      padding: 6px 0;
      font-size: 0.9em;
      text-decoration: none;
    }

    .btn-link:hover {
      text-decoration: underline;
    }

    .btn-remove {
      white-space: nowrap;
    }

    .cart-item-groups {
      margin-top: 16px;
    }

    .item-group-card {
      background: #f8f9fa;
      padding: 12px;
      border-radius: 6px;
      margin-bottom: 12px;
      border: 1px solid #dee2e6;
    }

    .item-group-card .btn-outline {
      font-size: 0.85em;
      padding: 4px 10px;
      margin-top: 8px;
    }

    .item-group-select-row {
      display: flex;
      gap: 8px;
      align-items: center;
      margin-bottom: 8px;
    }

    .item-group-select-row select {
      flex: 1;
    }

    .item-group-select-row .btn-icon {
      min-width: 32px;
      height: 32px;
      font-size: 1.2em;
    }

    .item-group-qty-row {
      display: flex;
      gap: 8px;
      align-items: center;
    }

    .item-group-qty-row label {
      margin: 0;
      font-weight: normal;
      color: #666;
    }

    .item-group-qty-row input {
      width: 80px;
      padding: 6px 10px;
      border: 1px solid #ced4da;
      border-radius: 4px;
    }

    .item-group-error {
      color: #dc3545;
      background: #f8d7da;
      border: 1px solid #f5c6cb;
      padding: 8px 12px;
      border-radius: 4px;
      font-size: 0.9em;
      display: none;
      margin-top: 12px;
    }

    .cart-item.is-mismatch .item-group-error {
      display: block;
    }

    .summary-section {
      margin: 24px 0;
      padding: 20px 0;
      border-top: 1px solid #e0e0e0;
    }

    .summary-section:first-child {
      border-top: none;
      padding-top: 0;
    }

    .summary-section h4 {
      margin: 0 0 12px;
      font-size: 1.1em;
    }

    .form-grid input {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #ced4da;
      border-radius: 4px;
      font-size: 0.95em;
    }

    .form-grid input:focus {
      outline: none;
      border-color: #80bdff;
      box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
    }

    .shipping-options {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .radio-option {
      cursor: pointer;
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .radio-option.disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .radio-option input[type="radio"]:checked ~ .radio-content {
      font-weight: 500;
    }

    .radio-option input[type="radio"]:checked ~ .radio-content .radio-label strong {
      color: #0056b3;
    }

    .radio-option input[type="radio"]:checked + * {
      color: #007bff;
    }

    .radio-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
      width: 100%;
    }

    .radio-label {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .radio-meta {
      font-size: 0.85em;
      color: #666;
      font-weight: normal;
    }

    .radio-cost {
      font-weight: bold;
      color: #28a745;
    }

    .summary-divider {
      height: 1px;
      background: #dee2e6;
      margin: 24px 0;
    }

    .summary-tax {
      color: #666;
      font-size: 0.9em;
    }

    .summary-total {
      font-size: 1.3em;
      font-weight: bold;
      padding-top: 12px;
    }

    .btn-checkout {
      width: 100%;
      padding: 16px;
      font-size: 1.1em;
      margin-top: 20px;
      background: #28a745;
      color: white;
      text-align: center;
      text-decoration: none;
      border-radius: 6px;
      display: block;
      transition: background 0.2s;
    }

    .btn-checkout:hover {
      background: #218838;
      text-decoration: none;
      color: white;
    }

    .checkout-note {
      text-align: center;
      font-size: 0.85em;
      color: #666;
      margin: 12px 0 0;
    }

    @media (max-width: 768px) {
      .cart-header {
        flex-direction: column;
        gap: 16px;
      }

      .cart-item-actions {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
      }

      .cart-item-subtotal {
        text-align: left;
      }
    }
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page cart-page">

    <?php if ($message): ?>
      <div class="notice notice-success"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
    <?php endif; ?>

    <?php if (!$items): ?>
      <div class="empty-cart">
        <div class="empty-cart-icon" aria-hidden="true">🛒</div>
        <h3>Your cart is empty</h3>
        <p>Add some products to get started!</p>
        <a href="/products.php" class="btn">Browse Products</a>
      </div>
    <?php else: ?>
      <div class="cart-layout">
        <section class="panel cart-panel">
          <div id="cart-message" class="notice is-error" role="alert" style="display:none;"></div>

          <div class="cart-panel-header">
            <h3 class="cart-panel-title">Shopping Cart<?php if ($items): ?> <span class="cart-count">(<?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'items'; ?>)</span><?php endif; ?></h3>
            <a href="/" class="btn-outline btn-continue">Continue Shopping</a>
          </div>

          <div class="cart-client">
            <label for="cart-client-select">
              <strong>Billable Client</strong>
              <span class="help-text">Optional: Select which client to bill for this order</span>
            </label>
            <div class="cart-client-row">
              <select id="cart-client-select" <?php echo $user ? '' : 'disabled'; ?>>
                <option value="">— Select a client (optional) —</option>
                <?php foreach ($clients as $client): ?>
                  <?php
                    $status = strtolower(trim((string) ($client['status'] ?? '')));
                    $label = (string) ($client['name'] ?? '');
                    $isBillable = site_client_is_billable($client);
                    if ($status === 'pending') {
                        $label = $label !== '' ? $label . ' (Pending)' : 'Pending client';
                    } elseif ($status === 'requested') {
                        $label = $label !== '' ? $label . ' (Requested)' : 'Requested client';
                    } elseif ($status === 'declined') {
                        $label = $label !== '' ? $label . ' (Declined)' : 'Declined client';
                    }
                  ?>
                  <option value="<?php echo htmlspecialchars($client['id'] ?? '', ENT_QUOTES); ?>" data-billable="<?php echo $isBillable ? '1' : '0'; ?>" data-status="<?php echo htmlspecialchars($status, ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($label, ENT_QUOTES); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php if (!$user): ?>
                <div class="meta">💡 <a href="/login.php">Sign in</a> to select a client and use accounting codes.</div>
              <?php elseif (!$clients): ?>
                <div class="meta">💡 Add clients in your <a href="/dashboard-clients.php">dashboard</a> to enable client billing.</div>
              <?php elseif (!$billableClients): ?>
                <div class="meta">💡 Clients are pending approval. Confirm the relationship in your dashboard to bill them.</div>
              <?php endif; ?>
            </div>
          </div>

          <div class="cart-groups">
            <div class="cart-groups-header">
              <h3><span aria-hidden="true">📊</span> Accounting Groups</h3>
            </div>
            <div class="cart-groups-content">
              <div id="accounting-group-list" class="cart-groups-list"></div>
              <div class="cart-groups-add">
                <button class="btn-outline btn-icon" type="button" id="add-group" title="Add accounting group">+</button>
              </div>
            </div>
          </div>

          <div class="cart-items">
            <?php foreach ($items as $item): ?>
              <?php
                $imageUrl = trim((string) ($item['imageUrl'] ?? ''));
                $hasImage = $imageUrl !== '';
                $itemKey = (string) $item['key'];
                $itemQty = (int) $item['quantity'];
                $itemPrice = (float) $item['price'];
                $itemSubtotal = $itemPrice * $itemQty;
              ?>
              <div class="cart-item" data-item-key="<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>" data-item-qty="<?php echo $itemQty; ?>" data-item-price="<?php echo number_format($itemPrice, 2, '.', ''); ?>">
                <div class="cart-item-row">
                  <div class="cart-item-media">
                    <?php if ($hasImage): ?>
                      <img src="<?php echo htmlspecialchars($imageUrl, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>" />
                    <?php else: ?>
                      <div class="image-placeholder" aria-hidden="true">📦</div>
                    <?php endif; ?>
                  </div>
                  <div class="cart-item-info">
                    <?php if (!empty($item['variantName'])): ?>
                      <div class="cart-item-product-name"><?php echo htmlspecialchars($item['productName'], ENT_QUOTES); ?></div>
                      <div class="cart-item-name"><?php echo htmlspecialchars($item['variantName'], ENT_QUOTES); ?></div>
                    <?php else: ?>
                      <div class="cart-item-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?></div>
                    <?php endif; ?>
                    <div class="cart-item-meta">
                      <span class="cart-item-price">$<?php echo number_format($itemPrice, 2); ?> each</span>
                      <?php if (isset($item['sku']) && $item['sku']): ?>
                        <span class="cart-item-sku">SKU: <?php echo htmlspecialchars($item['sku'], ENT_QUOTES); ?></span>
                      <?php endif; ?>
                      <?php if (!empty($item['arrivalDate'])): ?>
                        <span class="cart-item-arrival">Arrival: <?php echo htmlspecialchars((string) $item['arrivalDate'], ENT_QUOTES); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="cart-item-actions">
                    <div class="cart-qty-wrapper">
                      <label for="qty-<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>">Qty:</label>
                      <form method="POST" class="cart-qty-form">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="action" value="update" />
                        <input type="hidden" name="key" value="<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>" />
                        <select id="qty-<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>" name="quantity" class="cart-qty-select" title="Update quantity">
                          <?php for ($i = 1; $i <= 25; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo $i === $itemQty ? 'selected' : ''; ?>>
                              <?php echo $i; ?>
                            </option>
                          <?php endfor; ?>
                          <option value="more">More...</option>
                        </select>
                      </form>
                    </div>
                    <div class="cart-item-subtotal">
                      <strong>$<?php echo number_format($itemSubtotal, 2); ?></strong>
                    </div>
                    <form method="POST" class="cart-remove-form">
                      <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                      <input type="hidden" name="action" value="remove" />
                      <input type="hidden" name="key" value="<?php echo htmlspecialchars($itemKey, ENT_QUOTES); ?>" />
                      <button class="btn-link btn-remove" type="submit" title="Remove from cart"><span aria-hidden="true">✕</span> Remove</button>
                    </form>
                  </div>
                </div>
                <div class="cart-item-groups">
                  <div class="item-group-grid"></div>
                  <div class="item-group-error"><span aria-hidden="true">⚠️</span> Group quantities must equal the item quantity (<?php echo $itemQty; ?>).</div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>

        <aside class="panel cart-summary">
          <h3>Order Summary</h3>

          <div class="summary-line">
            <span>Subtotal (<?php echo count($items); ?> <?php echo count($items) === 1 ? 'item' : 'items'; ?>)</span>
            <span id="summary-subtotal" data-subtotal="<?php echo number_format($total, 2, '.', ''); ?>" data-taxable-subtotal="<?php echo number_format($taxableTotal, 2, '.', ''); ?>">
              $<?php echo number_format($total, 2); ?>
            </span>
          </div>

          <?php if ($isServiceOnlyCart): ?>
          <div class="summary-section">
            <div class="notice-info">Service items — no shipping required. No delivery fee applies.</div>
          </div>
          <?php else: ?>
          <div class="summary-section">
            <h4><span aria-hidden="true">🚚</span> Delivery Method</h4>
            <div class="shipping-options">
              <div class="shipping-option" data-method="pickup">
                <label class="radio-row radio-option">
                  <input type="radio" name="shipping_method" value="pickup" data-cost="0" checked />
                  <div class="radio-content">
                    <span class="radio-label">
                      <strong>Free Pickup</strong>
                      <span class="radio-meta">Ada, OK location</span>
                    </span>
                    <span class="radio-cost">FREE</span>
                  </div>
                </label>
              </div>

              <div class="shipping-option" data-method="standard">
                <label class="radio-row radio-option">
                  <input type="radio" name="shipping_method" value="standard" data-cost="0" />
                  <div class="radio-content">
                    <span class="radio-label">
                      <strong>Standard Shipping</strong>
                      <span class="radio-meta">3-5 business days</span>
                    </span>
                    <span class="radio-cost" data-role="standard-cost">Enter Address for Rate</span>
                  </div>
                </label>
                <div class="shipping-detail" id="shipping-standard-detail" hidden>
                  <p class="required-legend"><span aria-hidden="true">*</span> Required field</p>
                  <div class="form-grid">
                    <div>
                      <label for="standard-address1">Address line 1 *</label>
                      <input id="standard-address1" type="text" placeholder="123 Main St" value="<?php echo htmlspecialchars($userAddress['address'], ENT_QUOTES); ?>" />
                    </div>
                    <div>
                      <label for="standard-address2">Address line 2</label>
                      <input id="standard-address2" type="text" placeholder="Suite 100" value="<?php echo htmlspecialchars($userAddress['address2'], ENT_QUOTES); ?>" />
                    </div>
                    <div>
                      <label for="standard-city">City *</label>
                      <input id="standard-city" type="text" placeholder="Oklahoma City" value="<?php echo htmlspecialchars($userAddress['city'], ENT_QUOTES); ?>" />
                    </div>
                    <div class="form-grid cols-2">
                      <div>
                        <label for="standard-state">State *</label>
                        <input id="standard-state" type="text" placeholder="OK" value="<?php echo htmlspecialchars($userAddress['state'], ENT_QUOTES); ?>" maxlength="2" />
                      </div>
                      <div>
                        <label for="standard-zip">ZIP Code *</label>
                        <input id="standard-zip" type="text" placeholder="73301" value="<?php echo htmlspecialchars($userAddress['zip'], ENT_QUOTES); ?>" />
                      </div>
                    </div>
                  </div>
                  <div class="shipping-detail-actions">
                    <?php if ($user): ?>
                      <label class="checkbox-row" for="standard-use-once">
                        <input id="standard-use-once" type="checkbox" />
                        Use for this Shipment Only
                      </label>
                      <label class="checkbox-row" for="standard-use-update">
                        <input id="standard-use-update" type="checkbox" />
                        Use and Update
                      </label>
                    <?php else: ?>
                      <button type="button" class="btn" id="standard-update">Update Shipping</button>
                    <?php endif; ?>
                  </div>
                  <div class="notice is-error" id="standard-error" role="alert" hidden></div>
                </div>
                <div class="shipping-subline">
                  <button type="button" class="link-btn shipping-toggle" data-toggle="standard">
                    <?php echo $user ? 'Change address' : 'Enter address for rate'; ?>
                  </button>
                </div>
              </div>

              <div class="shipping-option" data-method="same_day">
                <label class="radio-row radio-option">
                  <input type="radio" name="shipping_method" value="same_day" data-cost="0" />
                  <div class="radio-content">
                    <span class="radio-label">
                      <strong>Same-Day Delivery</strong>
                      <span class="radio-meta">Oklahoma only • Order by 10 AM</span>
                    </span>
                    <span class="radio-cost" data-role="same-day-cost">Enter Delivery Location for Rate</span>
                  </div>
                </label>
                <div class="shipping-detail" id="shipping-sameday-detail" hidden>
                  <div class="shipping-mode-block" data-mode="address">
                    <label class="radio-pill">
                      <input type="radio" name="same_day_mode" value="address" />
                      Deliver to Address
                    </label>
                    <div class="shipping-mode-panel" hidden>
                      <div class="form-grid">
                        <div>
                          <label for="same-day-address1">Address line 1 *</label>
                          <input id="same-day-address1" type="text" placeholder="123 Main St" value="<?php echo htmlspecialchars($userAddress['address'], ENT_QUOTES); ?>" />
                        </div>
                        <div>
                          <label for="same-day-address2">Address line 2</label>
                          <input id="same-day-address2" type="text" placeholder="Suite 100" value="<?php echo htmlspecialchars($userAddress['address2'], ENT_QUOTES); ?>" />
                        </div>
                        <div>
                          <label for="same-day-city">City *</label>
                          <input id="same-day-city" type="text" placeholder="Oklahoma City" value="<?php echo htmlspecialchars($userAddress['city'], ENT_QUOTES); ?>" />
                        </div>
                        <div class="form-grid cols-2">
                          <div>
                            <label for="same-day-state">State *</label>
                            <input id="same-day-state" type="text" placeholder="OK" value="<?php echo htmlspecialchars($userAddress['state'], ENT_QUOTES); ?>" maxlength="2" />
                          </div>
                          <div>
                            <label for="same-day-zip">ZIP Code *</label>
                            <input id="same-day-zip" type="text" placeholder="73301" value="<?php echo htmlspecialchars($userAddress['zip'], ENT_QUOTES); ?>" />
                          </div>
                        </div>
                      </div>
                      <div class="shipping-detail-actions">
                        <?php if ($user): ?>
                          <label class="checkbox-row" for="same-day-use-once">
                            <input id="same-day-use-once" type="checkbox" />
                            Use for this Shipment Only
                          </label>
                          <label class="checkbox-row" for="same-day-use-update">
                            <input id="same-day-use-update" type="checkbox" />
                            Use and Update
                          </label>
                        <?php else: ?>
                          <label class="checkbox-row" for="same-day-use-once">
                            <input id="same-day-use-once" type="checkbox" />
                            Use for this Shipment Only
                          </label>
                          <label class="checkbox-row" for="same-day-use-save">
                            <input id="same-day-use-save" type="checkbox" />
                            Use and Save
                          </label>
                        <?php endif; ?>
                      </div>
                      <div class="notice is-error" id="same-day-address-error" role="alert" hidden></div>
                    </div>
                  </div>

                  <div class="shipping-mode-block" data-mode="coords">
                    <label class="radio-pill">
                      <input type="radio" name="same_day_mode" value="coords" />
                      Deliver to Coordinates
                    </label>
                    <div class="shipping-mode-panel" hidden>
                      <div class="form-grid cols-2">
                        <div>
                          <label for="same-day-coord-zip">ZIP Code *</label>
                          <input id="same-day-coord-zip" type="text" placeholder="73301" />
                        </div>
                        <div>
                          <label for="same-day-coord">Coordinates *</label>
                          <input id="same-day-coord" type="text" placeholder="35.4676,-97.5164" />
                        </div>
                      </div>
                      <div class="shipping-detail-actions">
                        <button type="button" class="btn" id="same-day-coord-update">Update Delivery</button>
                      </div>
                      <div class="notice is-error" id="same-day-coord-error" role="alert" hidden></div>
                    </div>
                  </div>

                  <div class="shipping-mode-block" data-mode="saved">
                    <label class="radio-pill">
                      <input type="radio" name="same_day_mode" value="saved" />
                      Deliver to Saved Location
                    </label>
                    <div class="shipping-mode-panel" hidden>
                      <div class="form-grid">
                        <div>
                          <label for="same-day-saved-location">Saved location</label>
                          <select id="same-day-saved-location"></select>
                        </div>
                      </div>
                      <div class="shipping-detail-actions">
                        <button type="button" class="btn" id="same-day-saved-update">Update Delivery</button>
                      </div>
                      <div class="notice is-error" id="same-day-saved-error" role="alert" hidden></div>
                      <div class="notice" id="same-day-saved-empty" hidden></div>
                    </div>
                  </div>
                </div>
                <div class="notice is-error" id="same-day-delivery-zone-error" role="alert" hidden></div>
                <div class="shipping-subline">
                  <button type="button" class="link-btn shipping-toggle" data-toggle="same-day">Change delivery location</button>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

          <div class="summary-divider"></div>

          <div class="summary-line">
            <span>Shipping</span>
            <span id="summary-shipping">$0.00</span>
          </div>
          <div class="summary-line summary-tax">
            <span>Tax</span>
            <span id="summary-tax">—</span>
          </div>
          <div class="summary-line summary-total">
            <span>Estimated Total</span>
            <span id="summary-total">$<?php echo number_format($total, 2); ?></span>
          </div>

          <?php if ($hasAnyServiceItem && !$user): ?>
          <div class="notice-info" style="margin-bottom: 12px;">
            Your cart contains service items. You must
            <a href="/login.php?redirect=<?php echo urlencode('/cart.php'); ?>">sign in</a>
            to proceed to checkout.
          </div>
          <a class="btn btn-primary btn-checkout" href="/login.php?redirect=<?php echo urlencode('/checkout.php'); ?>&service=1" id="checkout-button">
            Sign In to Checkout
          </a>
          <?php else: ?>
          <a class="btn btn-primary btn-checkout" href="/checkout.php" id="checkout-button">
            Proceed to Checkout →
          </a>
          <?php endif; ?>
          <p class="checkout-note">You'll confirm your order on the next page</p>
        </aside>
      </div>
    <?php endif; ?>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>

  <script nonce="<?php echo opd_csp_nonce(); ?>">
    (function(){
      const isSignedIn = <?php echo $user ? 'true' : 'false'; ?>;
      const isServiceOnlyCart = <?php echo $isServiceOnlyCart ? 'true' : 'false'; ?>;
      const csrfToken = '<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>';
      let accountingStructure = <?php echo json_encode($accountingStructure, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      const groupStorageBase = 'opd_cart_groups_v1';
      const assignmentStorageBase = 'opd_cart_assignments_v1';
      const clientStorageKey = 'opd_cart_client_v1';

      const messageEl = document.getElementById('cart-message');
      const groupList = document.getElementById('accounting-group-list');
      const addGroupButton = document.getElementById('add-group');
      const itemEls = Array.from(document.querySelectorAll('.cart-item'));
      const clientSelect = document.getElementById('cart-client-select');

      const shippingInputs = Array.from(document.querySelectorAll('input[name="shipping_method"]'));
      const summarySubtotal = document.getElementById('summary-subtotal');
      const summaryShipping = document.getElementById('summary-shipping');
      const summaryTax = document.getElementById('summary-tax');
      const summaryTotal = document.getElementById('summary-total');
      const checkoutButton = document.getElementById('checkout-button');

      const standardCostEl = document.querySelector('[data-role="standard-cost"]');
      const sameDayCostEl = document.querySelector('[data-role="same-day-cost"]');
      const standardDetail = document.getElementById('shipping-standard-detail');
      const sameDayDetail = document.getElementById('shipping-sameday-detail');
      const standardToggle = document.querySelector('[data-toggle="standard"]');
      const sameDayToggle = document.querySelector('[data-toggle="same-day"]');

      const standardFields = {
        address1: document.getElementById('standard-address1'),
        address2: document.getElementById('standard-address2'),
        city: document.getElementById('standard-city'),
        state: document.getElementById('standard-state'),
        zip: document.getElementById('standard-zip')
      };
      const standardUseOnce = document.getElementById('standard-use-once');
      const standardUseUpdate = document.getElementById('standard-use-update');
      const standardUpdateBtn = document.getElementById('standard-update');
      const standardError = document.getElementById('standard-error');

      const sameDayModeInputs = Array.from(document.querySelectorAll('input[name="same_day_mode"]'));
      const sameDayBlocks = Array.from(document.querySelectorAll('.shipping-mode-block'));
      const sameDayAddressFields = {
        address1: document.getElementById('same-day-address1'),
        address2: document.getElementById('same-day-address2'),
        city: document.getElementById('same-day-city'),
        state: document.getElementById('same-day-state'),
        zip: document.getElementById('same-day-zip')
      };
      const sameDayUseOnce = document.getElementById('same-day-use-once');
      const sameDayUseUpdate = document.getElementById('same-day-use-update');
      const sameDayUseSave = document.getElementById('same-day-use-save');
      const sameDayAddressError = document.getElementById('same-day-address-error');
      const sameDayCoordZip = document.getElementById('same-day-coord-zip');
      const sameDayCoord = document.getElementById('same-day-coord');
      const sameDayCoordUpdate = document.getElementById('same-day-coord-update');
      const sameDayCoordError = document.getElementById('same-day-coord-error');
      const sameDaySavedSelect = document.getElementById('same-day-saved-location');
      const sameDaySavedUpdate = document.getElementById('same-day-saved-update');
      const sameDaySavedError = document.getElementById('same-day-saved-error');
      const sameDaySavedEmpty = document.getElementById('same-day-saved-empty');

      const shippingStorageKey = 'opd_cart_shipping_v2';
      const signInHtml = 'Must Sign in to use this Feature. <a href="/login.php">Sign in</a> or <a href="/register.php">Register</a>';
      const defaultAddress = <?php echo json_encode($userAddress, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

      const subtotalValue = summarySubtotal ? parseFloat(summarySubtotal.dataset.subtotal || '0') : 0;
      const taxableSubtotal = summarySubtotal ? parseFloat(summarySubtotal.dataset.taxableSubtotal || '0') : 0;
      const deliveryZoneMap = <?php echo json_encode($deliveryZones, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      const deliveryCosts = <?php echo json_encode($deliveryCosts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      const hasLargeDelivery = <?php echo $hasLargeDelivery ? 'true' : 'false'; ?>;
      const shippingZonesData = <?php echo json_encode($shippingZones, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
      const cartTotalWeight = <?php echo json_encode($cartTotalWeight); ?>;
      let sameDayDeliveryError = '';
      let standardShippingError = '';
      let taxValue = 0;
      let taxRate = 0;
      let taxTimer = null;

      const shippingState = {
        method: 'pickup',
        standard: { address1: '', address2: '', city: '', state: '', zip: '', ready: false },
        sameDay: {
          mode: 'address',
          address: { address1: '', address2: '', city: '', state: '', zip: '' },
          coords: { zip: '', coordinate: '' },
          saved: { path: '', label: '', zip: '', coordinate: '' },
          ready: false
        }
      };

      function trimValue(value) {
        return String(value || '').trim();
      }

      function readAddress(fields) {
        return {
          address1: trimValue(fields.address1 && fields.address1.value),
          address2: trimValue(fields.address2 && fields.address2.value),
          city: trimValue(fields.city && fields.city.value),
          state: trimValue(fields.state && fields.state.value),
          zip: trimValue(fields.zip && fields.zip.value)
        };
      }

      function setAddressFields(fields, data) {
        if (!fields || !data) return;
        if (fields.address1) fields.address1.value = data.address1 || '';
        if (fields.address2) fields.address2.value = data.address2 || '';
        if (fields.city) fields.city.value = data.city || '';
        if (fields.state) fields.state.value = data.state || '';
        if (fields.zip) fields.zip.value = data.zip || '';
      }

      function isAddressComplete(address) {
        if (!address) return false;
        return !!(address.address1 && address.city && address.state && address.zip);
      }

      function setStandardState(address) {
        shippingState.standard = {
          address1: address.address1 || '',
          address2: address.address2 || '',
          city: address.city || '',
          state: address.state || '',
          zip: address.zip || '',
          ready: isAddressComplete(address)
        };
      }

      function setSameDayAddressState(address) {
        shippingState.sameDay.mode = 'address';
        shippingState.sameDay.address = {
          address1: address.address1 || '',
          address2: address.address2 || '',
          city: address.city || '',
          state: address.state || '',
          zip: address.zip || ''
        };
        shippingState.sameDay.ready = isAddressComplete(address);
      }

      function setSameDayCoordsState(zip, coordinate) {
        shippingState.sameDay.mode = 'coords';
        shippingState.sameDay.coords = {
          zip: trimValue(zip),
          coordinate: trimValue(coordinate)
        };
        shippingState.sameDay.ready = !!(shippingState.sameDay.coords.zip && shippingState.sameDay.coords.coordinate);
      }

      function setSameDaySavedState(payload) {
        shippingState.sameDay.mode = 'saved';
        shippingState.sameDay.saved = {
          path: payload.path || '',
          label: payload.label || '',
          zip: payload.zip || '',
          coordinate: payload.coordinate || ''
        };
        shippingState.sameDay.ready = !!(shippingState.sameDay.saved.zip && shippingState.sameDay.saved.coordinate);
      }

      function selectedShippingMethod() {
        const selected = shippingInputs.find((input) => input.checked);
        return selected ? selected.value : 'pickup';
      }

      function selectedSameDayMode() {
        const selected = sameDayModeInputs.find((input) => input.checked);
        return selected ? selected.value : '';
      }

      function getFallbackStateValue() {
        return trimValue(shippingState.sameDay.address.state) ||
          trimValue(shippingState.standard.state) ||
          trimValue(defaultAddress.state);
      }

      function getTaxAddress() {
        const method = selectedShippingMethod();
        if (method === 'standard') {
          return { state: shippingState.standard.state, postal: shippingState.standard.zip };
        }
        if (method === 'same_day') {
          const mode = selectedSameDayMode();
          if (mode === 'address') {
            return { state: shippingState.sameDay.address.state, postal: shippingState.sameDay.address.zip };
          }
          if (mode === 'coords') {
            return { state: getFallbackStateValue(), postal: shippingState.sameDay.coords.zip };
          }
          if (mode === 'saved') {
            return { state: getFallbackStateValue(), postal: shippingState.sameDay.saved.zip };
          }
        }
        return { state: '', postal: '' };
      }

      function showInlineError(el, message, html) {
        if (!el) return;
        if (html) {
          el.innerHTML = html;
        } else {
          el.textContent = message || '';
        }
        el.hidden = false;
      }

      function clearInlineError(el) {
        if (!el) return;
        el.textContent = '';
        el.hidden = true;
      }

      function persistShipping(payload) {
        try {
          localStorage.setItem(shippingStorageKey, JSON.stringify(payload));
        } catch (e) { /* ignore */ }
      }

      function loadPersistedShipping() {
        try {
          const raw = localStorage.getItem(shippingStorageKey);
          if (!raw) return null;
          const data = JSON.parse(raw);
          return data && typeof data === 'object' ? data : null;
        } catch (e) {
          return null;
        }
      }

      function persistSelectedMethod() {
        const payload = loadPersistedShipping() || {};
        payload.method = selectedShippingMethod();
        persistShipping(payload);
      }

      function buildPersistPayload(method, data) {
        return {
          method: method,
          mode: data.mode || '',
          path: data.path || '',
          address1: data.address1 || '',
          address2: data.address2 || '',
          city: data.city || '',
          state: data.state || '',
          zip: data.zip || '',
          coordinate: data.coordinate || '',
          label: data.label || ''
        };
      }

      function showMessage(text) {
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.style.display = 'block';
      }

      function clearMessage() {
        if (!messageEl) return;
        messageEl.textContent = '';
        messageEl.style.display = 'none';
      }

      function formatMoney(value) {
        return `$${Number(value || 0).toFixed(2)}`;
      }

      function getSameDayDeliveryZip() {
        const mode = selectedSameDayMode();
        if (mode === 'address') return trimValue(shippingState.sameDay.address.zip);
        if (mode === 'coords') return trimValue(shippingState.sameDay.coords.zip);
        if (mode === 'saved') return trimValue(shippingState.sameDay.saved.zip);
        return '';
      }

      function calcSameDayDeliveryCost() {
        const zip = getSameDayDeliveryZip();
        if (!zip) return { cost: 0, error: '', ready: false };
        const zone = deliveryZoneMap[zip];
        if (!zone) return { cost: 0, error: 'Sorry, we do not deliver outside Oklahoma', ready: false };
        const cls = hasLargeDelivery ? 'large' : 'small';
        const cost = Number((deliveryCosts[cls] || {})[zone] || 0);
        return { cost: cost, error: '', ready: true };
      }

      function calcStandardShippingCost() {
        const state = trimValue(shippingState.standard.state).toUpperCase();
        if (!state) return { cost: 0, error: '', ready: false };
        for (const zoneKey of Object.keys(shippingZonesData)) {
          const zoneData = shippingZonesData[zoneKey];
          if (zoneData.states && zoneData.states.indexOf(state) !== -1) {
            const cost = Number(zoneData.flat || 0) + (cartTotalWeight * Number(zoneData.perLb || 0));
            return { cost: Math.max(0, cost), error: '', ready: true };
          }
        }
        return { cost: 0, error: 'Sorry we do not ship outside the continental United States', ready: false };
      }

      function setTaxDisplay(value, ratePercent, showDash = false) {
        taxValue = value;
        taxRate = ratePercent || 0;
        if (summaryTax) {
          summaryTax.textContent = showDash ? '—' : formatMoney(taxValue);
        }
      }

      async function fetchTaxQuote() {
        const taxAddress = getTaxAddress();
        const state = trimValue(taxAddress.state);
        const postal = trimValue(taxAddress.postal);
        if (!state || !postal) {
          setTaxDisplay(0, 0, true);
          updateSummaryTotals();
          return;
        }
        try {
          const resp = await fetch('/api/tax_quote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subtotal: taxableSubtotal, state, postal })
          });
          const data = await resp.json().catch(() => ({}));
          if (!resp.ok) {
            throw new Error(data.error || 'Tax lookup failed.');
          }
          if (data.taxable && !data.rateFound) {
            setTaxDisplay(0, 0, true);
          } else {
            setTaxDisplay(Number(data.tax || 0), Number(data.taxRate || 0), false);
          }
        } catch (err) {
          setTaxDisplay(0, 0, true);
        }
        updateSummaryTotals();
      }

      function queueTaxQuote() {
        if (taxTimer) {
          clearTimeout(taxTimer);
        }
        taxTimer = setTimeout(fetchTaxQuote, 350);
      }

      function updateCheckoutLink() {
        if (!checkoutButton) return;
        const base = '/checkout.php';
        checkoutButton.href = activeClientId ? `${base}?clientId=${encodeURIComponent(activeClientId)}` : base;
      }

      async function fetchServerAccounting(clientId) {
        if (!isSignedIn) return null;
        const query = clientId ? `?clientId=${encodeURIComponent(clientId)}` : '';
        const resp = await fetch(`/api/cart_accounting.php${query}`);
        if (!resp.ok) {
          return null;
        }
        const data = await resp.json().catch(() => null);
        if (!data || !Array.isArray(data.groups)) {
          return null;
        }
        return data;
      }

      async function loadAccountingStructure(clientId) {
        if (!isSignedIn) return;
        const query = clientId ? `?clientId=${encodeURIComponent(clientId)}` : '';
        try {
          const resp = await fetch(`/api/accounting_structure.php${query}`);
          if (!resp.ok) {
            console.warn('[OPD] accounting_structure API error:', resp.status, resp.statusText);
            return;
          }
          const data = await resp.json().catch((e) => { console.warn('[OPD] JSON parse error:', e); return null; });
          if (!data || typeof data !== 'object') {
            console.warn('[OPD] accounting_structure returned invalid data:', data);
            return;
          }
          const hasAny = (Array.isArray(data.location) && data.location.length > 0)
            || (Array.isArray(data.code1) && data.code1.length > 0)
            || (Array.isArray(data.code2) && data.code2.length > 0);
          if (clientId && !hasAny) {
            console.warn('[OPD] Client has no accounting codes. clientId:', clientId, 'data:', data);
          }
          accountingStructure = data;
          renderSavedLocations(shippingState.sameDay.saved.path || '');
        } catch (err) {
          console.warn('[OPD] loadAccountingStructure fetch error:', err);
        }
      }

      function saveServerAccounting() {
        if (!isSignedIn) {
          return Promise.resolve();
        }
        const payload = {
          clientId: activeClientId || null,
          groups,
          assignments
        };
        return fetch('/api/cart_accounting.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify(payload)
        }).then(async (resp) => {
          const data = await resp.json().catch(() => ({}));
          if (!resp.ok) {
            throw new Error(data.error || 'Save failed');
          }
          return data;
        });
      }

      function queueSaveToServer() {
        if (!isSignedIn) return;
        if (saveTimer) {
          clearTimeout(saveTimer);
        }
        saveTimer = setTimeout(() => {
          saveServerAccounting().catch((err) => {
            showMessage(`Unable to save accounting groups: ${err.message}`);
          });
        }, 400);
      }

      function cleanAssignments() {
        const currentKeys = itemEls.map((itemEl) => itemEl.dataset.itemKey);
        Object.keys(assignments).forEach((key) => {
          if (!currentKeys.includes(key)) {
            delete assignments[key];
          }
        });
        saveAssignments();
      }

      async function hydrateAccounting() {
        isHydrating = true;
        let serverData = null;

        groups = defaultGroups();
        assignments = {};

        try {
          serverData = await fetchServerAccounting(activeClientId);
          if (serverData && Array.isArray(serverData.groups) && serverData.groups.length > 0) {
            groups = serverData.groups;
          }
          if (serverData && serverData.assignments && Object.keys(serverData.assignments).length > 0) {
            assignments = serverData.assignments;
          }
        } catch (err) {
          assignments = loadAssignments();
        }

        cleanAssignments();
        renderGroups();
        renderItems();
        isHydrating = false;
      }

      document.querySelectorAll('.cart-qty-select').forEach((select) => {
        select.addEventListener('change', (e) => {
          if (select.value === 'more') {
            const customQty = prompt('Enter quantity (1-999):', '26');
            if (customQty && !isNaN(customQty) && parseInt(customQty) > 0) {
              const newOption = document.createElement('option');
              newOption.value = customQty;
              newOption.textContent = customQty;
              newOption.selected = true;
              select.insertBefore(newOption, select.querySelector('[value="more"]'));
              select.form.submit();
            } else {
              e.preventDefault();
              select.value = select.querySelector('option[selected]')?.value || '1';
            }
          } else {
            select.form.submit();
          }
        });
      });

      function groupStorageKeyForClient(clientId) {
        return clientId ? `${groupStorageBase}_${clientId}` : groupStorageBase;
      }

      function assignmentStorageKeyForClient(clientId) {
        return clientId ? `${assignmentStorageBase}_${clientId}` : assignmentStorageBase;
      }

      let activeClientId = '';
      let activeGroupStorageKey = groupStorageBase;
      let activeAssignmentStorageKey = assignmentStorageBase;
      let isHydrating = false;
      let saveTimer = null;

      function setActiveClient(clientId) {
        activeClientId = clientId || '';
        activeGroupStorageKey = groupStorageKeyForClient(activeClientId);
        activeAssignmentStorageKey = assignmentStorageKeyForClient(activeClientId);
        updateCheckoutLink();
      }

      function normalizeClientSelection() {
        if (!clientSelect) {
          return;
        }
        const selected = clientSelect.options[clientSelect.selectedIndex];
        if (selected && selected.disabled) {
          clientSelect.value = '';
          localStorage.removeItem(clientStorageKey);
        }
      }

      if (clientSelect) {
        const savedClient = localStorage.getItem(clientStorageKey);
        if (savedClient) {
          clientSelect.value = savedClient;
        }
        normalizeClientSelection();
        setActiveClient(clientSelect.value);
        clientSelect.addEventListener('change', async () => {
          localStorage.setItem(clientStorageKey, clientSelect.value);
          setActiveClient(clientSelect.value);
          await loadAccountingStructure(activeClientId);
          await hydrateAccounting();
          try {
            await saveServerAccounting();
          } catch (err) {
            showMessage(`Unable to save client selection: ${err.message}`);
          }
        });
      } else {
        setActiveClient('');
      }

      const accountingMaxLevels = 3;

      function normalizeNodeLabel(value) {
        return String(value || '').trim();
      }

      function parseAccountingPath(value) {
        if (Array.isArray(value)) {
          return value.map((part) => normalizeNodeLabel(part)).filter((part) => part !== '');
        }
        if (typeof value === 'string') {
          const trimmed = value.trim();
          if (trimmed === '') {
            return [];
          }
          return trimmed.split(' > ').map((part) => normalizeNodeLabel(part)).filter((part) => part !== '');
        }
        return [];
      }

      function joinAccountingPath(parts) {
        return parts.filter((part) => part !== '').join(' > ');
      }

      function findChildNode(nodes, label) {
        const target = normalizeNodeLabel(label);
        if (!target) {
          return null;
        }
        return (nodes || []).find((node) => normalizeNodeLabel(node.label) === target) || null;
      }

      // Check if a path is at the deepest level (no more children)
      function isAtDeepestLevel(category, pathValue) {
        if (!pathValue) return true; // Empty is considered valid
        const pathParts = parseAccountingPath(pathValue);
        if (!pathParts.length) return true;

        let nodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];
        for (let i = 0; i < pathParts.length; i++) {
          const node = findChildNode(nodes, pathParts[i]);
          if (!node) return true; // Node not found, consider it valid
          if (i === pathParts.length - 1) {
            // This is the last selected node - check if it has children
            return !Array.isArray(node.children) || node.children.length === 0;
          }
          nodes = node.children || [];
        }
        return true;
      }

      function getRequireSubSettings() {
        return accountingStructure.requireSub || {};
      }

      function validateRequireSub() {
        const requireSub = getRequireSubSettings();
        const errors = [];

        groups.forEach((group, index) => {
          if (requireSub.location && group.location && !isAtDeepestLevel('location', group.location)) {
            errors.push(`Group ${index + 1}: Location must be at the lowest sub-level`);
          }
          if (requireSub.code1 && group.code1 && !isAtDeepestLevel('code1', group.code1)) {
            errors.push(`Group ${index + 1}: Code 1 must be at the lowest sub-level`);
          }
          if (requireSub.code2 && group.code2 && !isAtDeepestLevel('code2', group.code2)) {
            errors.push(`Group ${index + 1}: Code 2 must be at the lowest sub-level`);
          }
        });

        return errors;
      }

      function getNodesAtLevel(category, pathParts, level) {
        let nodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];
        for (let i = 0; i < level; i++) {
          const parentLabel = pathParts[i];
          if (!parentLabel) {
            return [];
          }
          const parent = findChildNode(nodes, parentLabel);
          if (!parent || !Array.isArray(parent.children)) {
            return [];
          }
          nodes = parent.children;
        }
        return nodes;
      }

      function buildCascadingOptions(nodes, pathParts, level, fragment) {
        const indent = '\u00A0\u00A0'.repeat(level);
        nodes.forEach((node) => {
          const label = normalizeNodeLabel(node.label);
          if (!label) return;

          const option = document.createElement('option');
          const currentPath = [...pathParts.slice(0, level), label];
          option.value = joinAccountingPath(currentPath);
          option.textContent = indent + label;
          option.dataset.level = String(level);

          const fullPath = joinAccountingPath(pathParts);
          const optionPath = joinAccountingPath(currentPath);
          if (fullPath === optionPath || fullPath.startsWith(optionPath + ' > ')) {
            option.selected = fullPath === optionPath;
          }

          fragment.appendChild(option);

          // If this node is in the current path, show its children
          if (pathParts[level] === label && Array.isArray(node.children) && node.children.length > 0) {
            buildCascadingOptions(node.children, pathParts, level + 1, fragment);
          }
        });
      }

      function findNodeByPath(category, pathValue) {
        const pathParts = parseAccountingPath(pathValue || '');
        if (!pathParts.length) return null;
        let nodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];
        let current = null;
        for (let i = 0; i < pathParts.length; i += 1) {
          current = findChildNode(nodes, pathParts[i]);
          if (!current) return null;
          nodes = current.children || [];
        }
        return current;
      }

      function renderSavedLocations(pathValue) {
        if (!sameDaySavedSelect) return;
        sameDaySavedSelect.innerHTML = '';
        if (!isSignedIn) {
          sameDaySavedSelect.disabled = true;
          if (sameDaySavedUpdate) sameDaySavedUpdate.disabled = true;
          if (sameDaySavedEmpty) {
            sameDaySavedEmpty.innerHTML = signInHtml;
            sameDaySavedEmpty.hidden = false;
          }
          return;
        }

        const rootNodes = Array.isArray(accountingStructure.location) ? accountingStructure.location : [];
        if (!rootNodes.length) {
          sameDaySavedSelect.disabled = true;
          if (sameDaySavedUpdate) sameDaySavedUpdate.disabled = true;
          if (sameDaySavedEmpty) {
            sameDaySavedEmpty.textContent = 'No saved locations yet.';
            sameDaySavedEmpty.hidden = false;
          }
          return;
        }

        if (sameDaySavedEmpty) {
          sameDaySavedEmpty.textContent = '';
          sameDaySavedEmpty.hidden = true;
        }
        sameDaySavedSelect.disabled = false;
        if (sameDaySavedUpdate) sameDaySavedUpdate.disabled = false;

        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = 'Select';
        sameDaySavedSelect.appendChild(blank);

        const pathParts = parseAccountingPath(pathValue || '');
        const fragment = document.createDocumentFragment();
        buildCascadingOptions(rootNodes, pathParts, 0, fragment);
        sameDaySavedSelect.appendChild(fragment);

        const currentValue = joinAccountingPath(pathParts);
        if (currentValue) {
          sameDaySavedSelect.value = currentValue;
        }
      }

      function findChildNode(nodes, label) {
        const target = normalizeNodeLabel(label);
        if (!target) return null;
        for (let i = 0; i < (nodes || []).length; i++) {
          if (normalizeNodeLabel(nodes[i].label) === target) return nodes[i];
        }
        return null;
      }

      function isAtDeepestLevel(category, pathValue) {
        if (!pathValue) return true;
        const pathParts = parseAccountingPath(pathValue);
        if (!pathParts.length) return true;
        let nodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];
        for (let i = 0; i < pathParts.length; i++) {
          const node = findChildNode(nodes, pathParts[i]);
          if (!node) return true;
          if (i === pathParts.length - 1) {
            return !Array.isArray(node.children) || node.children.length === 0;
          }
          nodes = node.children || [];
        }
        return true;
      }

      function buildCascadingOptions(category, nodes, pathParts, level, fragment) {
        let indent = '';
        for (let i = 0; i < level; i++) indent += '\u00A0\u00A0';
        for (let j = 0; j < nodes.length; j++) {
          const node = nodes[j];
          const label = normalizeNodeLabel(node.label);
          if (!label) continue;

          const option = document.createElement('option');
          const currentPath = pathParts.slice(0, level).concat([label]);
          option.value = joinAccountingPath(currentPath);
          option.textContent = indent + label;
          option.dataset.level = String(level);

          const fullPath = joinAccountingPath(pathParts);
          const optionPath = joinAccountingPath(currentPath);
          if (fullPath === optionPath || fullPath.indexOf(optionPath + ' > ') === 0) {
            option.selected = fullPath === optionPath;
          }

          fragment.appendChild(option);

          if (pathParts[level] === label && Array.isArray(node.children) && node.children.length > 0) {
            buildCascadingOptions(category, node.children, pathParts, level + 1, fragment);
          }
        }
      }

      function rebuildCascadingSelect(select, category, pathValue) {
        if (!select) return;
        select.innerHTML = '';

        const blank = document.createElement('option');
        blank.value = '';
        blank.textContent = 'Select';
        select.appendChild(blank);

        const pathParts = parseAccountingPath(pathValue || '');
        const rootNodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];
        const fragment = document.createDocumentFragment();
        buildCascadingOptions(category, rootNodes, pathParts, 0, fragment);
        select.appendChild(fragment);

        const currentValue = joinAccountingPath(pathParts);
        if (currentValue) select.value = currentValue;
      }

      function collapseCascadingSelect(select) {
        if (!select) return;
        if (select.classList.contains('is-expanded')) {
          select.size = 1;
          select.classList.remove('is-expanded');
        }
      }

      function expandCascadingSelect(select) {
        if (!select) return;
        if (typeof select.showPicker === 'function') {
          try { select.showPicker(); } catch (e) { /* ignore */ }
          return;
        }
        const optionCount = select.options ? select.options.length : 0;
        if (optionCount > 1) {
          select.size = Math.min(optionCount, 8);
          select.classList.add('is-expanded');
          select.focus();
        }
      }

      function buildAccountingCategoryField(category, label, group, index) {
        const field = document.createElement('div');
        field.className = 'cart-group-field';
        field.appendChild(document.createElement('label')).textContent = label;

        const currentValue = group[category] || '';
        const nodes = Array.isArray(accountingStructure[category]) ? accountingStructure[category] : [];

        if (typeof AccordionDropdown !== 'undefined') {
          const dd = AccordionDropdown.create(field, nodes, {
            value: currentValue,
            placeholder: 'Select ' + label.toLowerCase() + '...',
            onChange: function (path) {
              if (!isSignedIn) {
                showMessage('Sign in to use accounting codes.');
                dd.setValue(group[category] || '');
                return;
              }
              clearMessage();
              groups[index][category] = path;
              saveGroups();
              renderItems();
            }
          });
        } else {
          // Fallback to native select if AccordionDropdown not loaded
          const select = document.createElement('select');
          select.className = 'accounting-cascading-select';
          select.dataset.category = category;
          rebuildCascadingSelect(select, category, currentValue);
          select.addEventListener('change', function (event) {
            if (!isSignedIn) {
              showMessage('Sign in to use accounting codes.');
              event.target.value = group[category] || '';
              return;
            }
            clearMessage();
            groups[index][category] = event.target.value;
            saveGroups();
            renderItems();
            rebuildCascadingSelect(select, category, event.target.value);
            if (event.target.value && !isAtDeepestLevel(category, event.target.value)) {
              setTimeout(function () { expandCascadingSelect(select); }, 50);
            } else {
              collapseCascadingSelect(select);
            }
          });
          select.addEventListener('blur', function () { collapseCascadingSelect(select); });
          field.appendChild(select);
          console.warn('AccordionDropdown not available, using fallback select for', category);
        }

        return field;
      }

      function defaultGroups() {
        return [{ location: '', code1: '', code2: '' }];
      }

      function loadGroups() {
        // Always return default single group
        // Groups are session-based and don't persist across page reloads
        return defaultGroups();
      }

      function saveGroups() {
        if (!isHydrating) {
          queueSaveToServer();
        }
      }

      function groupLabel(index, showCodes = true) {
        const group = groups[index] || {};
        if (!showCodes) {
          return `Group ${index + 1}`;
        }
        const parts = [group.location, group.code1, group.code2].filter(Boolean);
        const suffix = parts.length ? ' - ' + parts.join(' / ') : '';
        return `Group ${index + 1}${suffix}`;
      }

      function buildGroupOptions(selectedIndex, showCodes = false) {
        const fragment = document.createDocumentFragment();
        groups.forEach((group, index) => {
          const option = document.createElement('option');
          option.value = String(index);
          option.textContent = groupLabel(index, showCodes);
          if (index === selectedIndex) {
            option.selected = true;
          }
          fragment.appendChild(option);
        });
        return fragment;
      }

      let groups = loadGroups();
      let assignments = {};

      function loadAssignments() {
        try {
          const saved = JSON.parse(localStorage.getItem(activeAssignmentStorageKey) || '{}');
          if (saved && typeof saved === 'object') {
            return saved;
          }
        } catch (err) {
          return {};
        }
        return {};
      }

      function saveAssignments() {
        localStorage.setItem(activeAssignmentStorageKey, JSON.stringify(assignments));
        if (!isHydrating) {
          queueSaveToServer();
        }
      }

      function renderGroups() {
        if (!groupList) return;
        groupList.innerHTML = '';

        groups.forEach((group, index) => {
          const row = document.createElement('div');
          row.className = 'cart-group-row';

          const header = document.createElement('div');
          header.className = 'cart-group-title';
          header.textContent = `Group ${index + 1}`;
          row.appendChild(header);

          const fields = document.createElement('div');
          fields.className = 'cart-group-fields';

          fields.appendChild(buildAccountingCategoryField('location', 'Location', group, index));
          fields.appendChild(buildAccountingCategoryField('code1', 'Code 1', group, index));
          fields.appendChild(buildAccountingCategoryField('code2', 'Code 2', group, index));
          row.appendChild(fields);

          if (groups.length > 1) {
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn-outline';
            remove.textContent = 'Remove group';
            remove.addEventListener('click', () => {
              if (!isSignedIn) {
                showMessage('Sign in to use accounting codes.');
                return;
              }
              groups.splice(index, 1);
              if (!groups.length) {
                groups = defaultGroups();
              }
              saveGroups();
              renderGroups();
              renderItems();
            });
            row.appendChild(remove);
          }

          groupList.appendChild(row);
        });
      }

      function normalizeAssignments(itemKey, itemQty) {
        let itemAssignments = assignments[itemKey];
        if (!Array.isArray(itemAssignments) || !itemAssignments.length) {
          itemAssignments = [{ groupIndex: 0, qty: itemQty }];
        }
        itemAssignments = itemAssignments.map((entry) => {
          const groupIndex = Math.min(entry.groupIndex || 0, groups.length - 1);
          const qty = Number.isFinite(entry.qty) ? entry.qty : 1;
          return { groupIndex, qty };
        });
        if (itemAssignments.length === 1) {
          itemAssignments[0].qty = itemQty;
        }
        assignments[itemKey] = itemAssignments;
        return itemAssignments;
      }

      function updateItemMismatch(itemEl, itemAssignments) {
        const itemQty = parseInt(itemEl.dataset.itemQty || '1', 10);
        const hasMultiple = itemAssignments.length > 1;
        const qtyInputs = Array.from(itemEl.querySelectorAll('.item-group-qty'));
        const errorEl = itemEl.querySelector('.item-group-error');
        let mismatch = false;

        if (hasMultiple) {
          const totalQty = itemAssignments.reduce((sum, entry) => sum + (parseInt(entry.qty || '0', 10) || 0), 0);
          mismatch = totalQty !== itemQty;
        }

        itemEl.classList.toggle('is-mismatch', mismatch);
        itemEl.classList.toggle('is-single-group', !hasMultiple);
        qtyInputs.forEach((input) => {
          input.classList.toggle('is-mismatch', mismatch);
        });
        if (errorEl) {
          errorEl.style.display = mismatch ? 'block' : 'none';
        }
        return mismatch;
      }

      function renderItemGroups(itemEl) {
        const itemKey = itemEl.dataset.itemKey;
        const itemQty = parseInt(itemEl.dataset.itemQty || '1', 10);
        const grid = itemEl.querySelector('.item-group-grid');
        if (!grid || !itemKey) return;

        const itemAssignments = normalizeAssignments(itemKey, itemQty);
        grid.innerHTML = '';

        itemAssignments.forEach((entry, idx) => {
          const card = document.createElement('div');
          card.className = 'item-group-card';

          const groupRow = document.createElement('div');
          groupRow.className = 'item-group-select-row';

          const select = document.createElement('select');
          select.appendChild(buildGroupOptions(entry.groupIndex, false));
          select.addEventListener('change', () => {
            entry.groupIndex = parseInt(select.value || '0', 10);
            saveAssignments();
            renderItems();
          });
          groupRow.appendChild(select);

          if (idx === itemAssignments.length - 1) {
            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'btn-outline btn-icon';
            addBtn.textContent = '+';
            addBtn.title = 'Add group';
            addBtn.addEventListener('click', () => {
              itemAssignments.push({ groupIndex: 0, qty: 1 });
              assignments[itemKey] = itemAssignments;
              saveAssignments();
              renderItems();
            });
            groupRow.appendChild(addBtn);
          }

          card.appendChild(groupRow);

          const qtyWrap = document.createElement('div');
          qtyWrap.className = 'item-group-qty-row';
          const qtyLabel = document.createElement('label');
          qtyLabel.textContent = 'Qty';
          const qtyInput = document.createElement('input');
          qtyInput.type = 'number';
          qtyInput.min = '0';
          qtyInput.value = entry.qty;
          qtyInput.className = 'item-group-qty';
          qtyInput.addEventListener('input', () => {
            entry.qty = parseInt(qtyInput.value || '0', 10);
            saveAssignments();
            updateItemMismatch(itemEl, itemAssignments);
          });
          qtyWrap.appendChild(qtyLabel);
          qtyWrap.appendChild(qtyInput);
          card.appendChild(qtyWrap);

          if (itemAssignments.length > 1) {
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn-outline';
            removeBtn.textContent = 'Remove';
            removeBtn.addEventListener('click', () => {
              itemAssignments.splice(idx, 1);
              if (!itemAssignments.length) {
                itemAssignments.push({ groupIndex: 0, qty: itemQty });
              }
              assignments[itemKey] = itemAssignments;
              saveAssignments();
              renderItems();
            });
            card.appendChild(removeBtn);
          }

          grid.appendChild(card);
        });

        updateItemMismatch(itemEl, itemAssignments);
      }

      function renderItems() {
        itemEls.forEach((itemEl) => renderItemGroups(itemEl));
        saveAssignments();
      }

      function updateShippingCostLabels() {
        if (standardCostEl) {
          const stdCalc = calcStandardShippingCost();
          if (stdCalc.ready) {
            standardCostEl.textContent = formatMoney(stdCalc.cost);
            standardCostEl.classList.remove('is-prompt');
          } else if (stdCalc.error) {
            standardCostEl.textContent = stdCalc.error;
            standardCostEl.classList.remove('is-prompt');
          } else {
            standardCostEl.textContent = 'Enter Address for Rate';
            standardCostEl.classList.add('is-prompt');
          }
        }

        if (sameDayCostEl) {
          const dayCalc = calcSameDayDeliveryCost();
          if (dayCalc.ready) {
            sameDayCostEl.textContent = formatMoney(dayCalc.cost);
            sameDayCostEl.classList.remove('is-prompt');
          } else {
            sameDayCostEl.textContent = 'Enter Delivery Location for Rate';
            sameDayCostEl.classList.add('is-prompt');
          }
        }
      }

      function refreshSameDayReady() {
        const mode = selectedSameDayMode();
        shippingState.sameDay.mode = mode;
        if (mode === 'address') {
          shippingState.sameDay.ready = isAddressComplete(shippingState.sameDay.address);
        } else if (mode === 'coords') {
          shippingState.sameDay.ready = !!(shippingState.sameDay.coords.zip && shippingState.sameDay.coords.coordinate);
        } else if (mode === 'saved') {
          shippingState.sameDay.ready = !!(shippingState.sameDay.saved.zip && shippingState.sameDay.saved.coordinate);
        } else {
          shippingState.sameDay.ready = false;
        }
      }

      function updateSummaryTotals() {
        refreshSameDayReady();
        let shippingCost = 0;
        let shippingReady = true;
        sameDayDeliveryError = '';
        standardShippingError = '';
        if (isServiceOnlyCart) {
          shippingCost = 0;
          shippingReady = true;
        } else {
          const selected = shippingInputs.find((input) => input.checked);
          const method = selected ? selected.value : 'pickup';
          if (method === 'standard') {
            const stdCalc = calcStandardShippingCost();
            if (stdCalc.error) {
              standardShippingError = stdCalc.error;
              shippingReady = false;
              shippingCost = 0;
            } else {
              shippingReady = stdCalc.ready;
              shippingCost = stdCalc.cost;
            }
          } else if (method === 'same_day') {
            const deliveryCalc = calcSameDayDeliveryCost();
            if (deliveryCalc.error) {
              sameDayDeliveryError = deliveryCalc.error;
              shippingReady = false;
              shippingCost = 0;
            } else {
              shippingReady = deliveryCalc.ready;
              shippingCost = deliveryCalc.cost;
            }
          }
        }

        if (summaryShipping) {
          summaryShipping.textContent = shippingReady ? `$${shippingCost.toFixed(2)}` : '—';
        }
        if (summaryTotal) {
          summaryTotal.textContent = `$${(subtotalValue + (shippingReady ? shippingCost : 0) + taxValue).toFixed(2)}`;
        }
        if (standardCostEl) {
          const method = selectedShippingMethod();
          if (method === 'standard' && shippingReady) {
            standardCostEl.textContent = formatMoney(shippingCost);
            standardCostEl.classList.remove('is-prompt');
          } else if (method === 'standard' && standardShippingError) {
            standardCostEl.textContent = standardShippingError;
            standardCostEl.classList.remove('is-prompt');
          } else if (method === 'standard') {
            standardCostEl.textContent = 'Enter Address for Rate';
            standardCostEl.classList.add('is-prompt');
          }
        }
        if (sameDayCostEl) {
          const method = selectedShippingMethod();
          if (method === 'same_day' && shippingReady) {
            sameDayCostEl.textContent = formatMoney(shippingCost);
          } else if (method === 'same_day' && sameDayDeliveryError) {
            sameDayCostEl.textContent = sameDayDeliveryError;
          } else if (method === 'same_day') {
            sameDayCostEl.textContent = 'Enter Delivery Location for Rate';
          }
        }
        const standardErrorEl = document.getElementById('standard-error');
        if (standardErrorEl) {
          if (standardShippingError) {
            standardErrorEl.textContent = standardShippingError;
            standardErrorEl.hidden = false;
          } else {
            standardErrorEl.textContent = '';
            standardErrorEl.hidden = true;
          }
        }
        const deliveryErrorEl = document.getElementById('same-day-delivery-zone-error');
        if (deliveryErrorEl) {
          if (sameDayDeliveryError) {
            deliveryErrorEl.textContent = sameDayDeliveryError;
            deliveryErrorEl.hidden = false;
          } else {
            deliveryErrorEl.textContent = '';
            deliveryErrorEl.hidden = true;
          }
        }
      }

      function collapseOtherShippingDetails(method) {
        if (standardDetail && method !== 'standard') {
          standardDetail.setAttribute('hidden', '');
        }
        if (sameDayDetail && method !== 'same_day') {
          sameDayDetail.setAttribute('hidden', '');
        }
      }

      function toggleDetail(panel) {
        if (!panel) return;
        const next = panel.hasAttribute('hidden');
        if (next) {
          if (standardDetail && panel !== standardDetail) standardDetail.setAttribute('hidden', '');
          if (sameDayDetail && panel !== sameDayDetail) sameDayDetail.setAttribute('hidden', '');
          panel.removeAttribute('hidden');
          return;
        }
        panel.setAttribute('hidden', '');
      }

      function selectShippingMethod(value) {
        const input = shippingInputs.find((item) => item.value === value);
        if (input) {
          input.checked = true;
        }
        collapseOtherShippingDetails(value);
      }

      function applyStandardAddress(address, persistMode) {
        setStandardState(address);
        selectShippingMethod('standard');
        updateShippingCostLabels();
        updateSummaryTotals();
        queueTaxQuote();
        persistShipping(buildPersistPayload('standard', {
          mode: persistMode || 'address',
          address1: address.address1,
          address2: address.address2,
          city: address.city,
          state: address.state,
          zip: address.zip
        }));
      }

      function applySameDayAddress(address, persistMode) {
        setSameDayAddressState(address);
        selectShippingMethod('same_day');
        updateShippingCostLabels();
        updateSummaryTotals();
        queueTaxQuote();
        persistShipping(buildPersistPayload('same_day', {
          mode: persistMode || 'address',
          address1: address.address1,
          address2: address.address2,
          city: address.city,
          state: address.state,
          zip: address.zip
        }));
      }

      function applySameDayCoords(zip, coordinate) {
        setSameDayCoordsState(zip, coordinate);
        selectShippingMethod('same_day');
        updateShippingCostLabels();
        updateSummaryTotals();
        queueTaxQuote();
        persistShipping(buildPersistPayload('same_day', {
          mode: 'coords',
          zip: trimValue(zip),
          coordinate: trimValue(coordinate)
        }));
      }

      function applySameDaySaved(payload) {
        setSameDaySavedState(payload);
        selectShippingMethod('same_day');
        updateShippingCostLabels();
        updateSummaryTotals();
        queueTaxQuote();
        persistShipping(buildPersistPayload('same_day', {
          mode: 'saved',
          path: payload.path,
          zip: payload.zip,
          coordinate: payload.coordinate,
          label: payload.label,
          address1: payload.label
        }));
      }

      function initShippingState() {
        const persisted = loadPersistedShipping();
        if (persisted) {
          const method = persisted.method || '';
          if (method) {
            selectShippingMethod(method);
          }
          if (persisted.mode === 'coords') {
            if (sameDayCoordZip) sameDayCoordZip.value = persisted.zip || '';
            if (sameDayCoord) sameDayCoord.value = persisted.coordinate || '';
            setSameDayCoordsState(persisted.zip || '', persisted.coordinate || '');
            const coordRadio = sameDayModeInputs.find((r) => r.value === 'coords');
            if (coordRadio) coordRadio.checked = true;
            updateSameDayPanels();
          } else if (persisted.mode === 'saved') {
            setSameDaySavedState({
              path: persisted.path || '',
              label: persisted.label || '',
              zip: persisted.zip || '',
              coordinate: persisted.coordinate || ''
            });
            const savedRadio = sameDayModeInputs.find((r) => r.value === 'saved');
            if (savedRadio) savedRadio.checked = true;
            updateSameDayPanels();
          } else if (persisted.method === 'same_day' && persisted.address1) {
            const addr = {
              address1: persisted.address1 || '',
              address2: persisted.address2 || '',
              city: persisted.city || '',
              state: persisted.state || '',
              zip: persisted.zip || ''
            };
            setAddressFields(sameDayAddressFields, addr);
            setSameDayAddressState(addr);
            const addrRadio = sameDayModeInputs.find((r) => r.value === 'address');
            if (addrRadio) addrRadio.checked = true;
            updateSameDayPanels();
          } else if (persisted.method === 'standard' && persisted.address1) {
            const addr = {
              address1: persisted.address1 || '',
              address2: persisted.address2 || '',
              city: persisted.city || '',
              state: persisted.state || '',
              zip: persisted.zip || ''
            };
            setAddressFields(standardFields, addr);
            setStandardState(addr);
          }
        }

        const fallback = {
          address1: defaultAddress.address || '',
          address2: defaultAddress.address2 || '',
          city: defaultAddress.city || '',
          state: defaultAddress.state || '',
          zip: defaultAddress.zip || ''
        };
        if (!shippingState.standard.ready && isAddressComplete(fallback)) {
          if (!standardFields.address1 || !standardFields.address1.value) {
            setAddressFields(standardFields, fallback);
          }
          setStandardState(fallback);
        }
        if (!isAddressComplete(shippingState.sameDay.address) && isAddressComplete(fallback)) {
          if (!sameDayAddressFields.address1 || !sameDayAddressFields.address1.value) {
            setAddressFields(sameDayAddressFields, fallback);
          }
          setSameDayAddressState(fallback);
        }
        updateShippingCostLabels();
        updateSummaryTotals();
      }

      if (addGroupButton) {
        addGroupButton.addEventListener('click', () => {
          if (!isSignedIn) {
            showMessage('Sign in to use accounting codes.');
            return;
          }
          groups.push({ location: '', code1: '', code2: '' });
          saveGroups();
          renderGroups();
          renderItems();
        });
      }

      async function initializeClientAccounting() {
        await loadAccountingStructure(activeClientId);
        await hydrateAccounting();
      }

      initializeClientAccounting();

      initShippingState();
      renderSavedLocations(shippingState.sameDay.saved.path || '');

      if (standardToggle) {
        standardToggle.addEventListener('click', () => toggleDetail(standardDetail));
      }
      if (sameDayToggle) {
        sameDayToggle.addEventListener('click', () => toggleDetail(sameDayDetail));
      }

      if (standardCostEl) {
        standardCostEl.addEventListener('click', () => {
          if (standardCostEl.classList.contains('is-prompt')) {
            toggleDetail(standardDetail);
          }
        });
      }
      if (sameDayCostEl) {
        sameDayCostEl.addEventListener('click', () => {
          if (sameDayCostEl.classList.contains('is-prompt')) {
            toggleDetail(sameDayDetail);
          }
        });
      }

      function updateUserShipping(address) {
        return fetch('/api/user_shipping.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({
            shippingAddress1: address.address1,
            shippingAddress2: address.address2,
            shippingCity: address.city,
            shippingState: address.state,
            shippingPostcode: address.zip
          })
        }).then(async (resp) => {
          const data = await resp.json().catch(() => ({}));
          if (!resp.ok) {
            throw new Error(data.error || 'Unable to update shipping address.');
          }
          return data;
        });
      }

      if (standardUseOnce) {
        standardUseOnce.addEventListener('change', async () => {
          if (!standardUseOnce.checked) return;
          if (standardUseUpdate) standardUseUpdate.checked = false;
          clearInlineError(standardError);
          const address = readAddress(standardFields);
          if (!isAddressComplete(address)) {
            showInlineError(standardError, 'Please complete all required address fields.');
            standardUseOnce.checked = false;
            return;
          }
          applyStandardAddress(address, 'address');
          if (standardDetail) standardDetail.setAttribute('hidden', '');
          standardUseOnce.checked = false;
        });
      }

      if (standardUseUpdate) {
        standardUseUpdate.addEventListener('change', async () => {
          if (!standardUseUpdate.checked) return;
          if (standardUseOnce) standardUseOnce.checked = false;
          clearInlineError(standardError);
          const address = readAddress(standardFields);
          if (!isAddressComplete(address)) {
            showInlineError(standardError, 'Please complete all required address fields.');
            standardUseUpdate.checked = false;
            return;
          }
          try {
            await updateUserShipping(address);
            applyStandardAddress(address, 'address');
            if (standardDetail) standardDetail.setAttribute('hidden', '');
          } catch (err) {
            showInlineError(standardError, err.message || 'Unable to update shipping address.');
          }
          standardUseUpdate.checked = false;
        });
      }

      if (standardUpdateBtn) {
        standardUpdateBtn.addEventListener('click', () => {
          clearInlineError(standardError);
          const address = readAddress(standardFields);
          if (!isAddressComplete(address)) {
            showInlineError(standardError, 'Please complete all required address fields.');
            return;
          }
          applyStandardAddress(address, 'address');
          if (standardDetail) standardDetail.setAttribute('hidden', '');
        });
      }

      function updateSameDayPanels() {
        const mode = selectedSameDayMode();
        sameDayBlocks.forEach((block) => {
          const panel = block.querySelector('.shipping-mode-panel');
          if (!panel) return;
          const isActive = block.dataset.mode === mode;
          if (isActive) {
            panel.removeAttribute('hidden');
          } else {
            panel.setAttribute('hidden', '');
          }
        });
        refreshSameDayReady();
        updateShippingCostLabels();
        updateSummaryTotals();
        queueTaxQuote();
      }

      sameDayModeInputs.forEach((input) => {
        input.addEventListener('change', updateSameDayPanels);
      });

      if (sameDayUseOnce) {
        sameDayUseOnce.addEventListener('change', async () => {
          if (!sameDayUseOnce.checked) return;
          if (sameDayUseUpdate) sameDayUseUpdate.checked = false;
          if (sameDayUseSave) sameDayUseSave.checked = false;
          clearInlineError(sameDayAddressError);
          const address = readAddress(sameDayAddressFields);
          if (!isAddressComplete(address)) {
            showInlineError(sameDayAddressError, 'Please complete all required address fields.');
            sameDayUseOnce.checked = false;
            return;
          }
          applySameDayAddress(address, 'address');
          if (sameDayDetail) sameDayDetail.setAttribute('hidden', '');
          sameDayUseOnce.checked = false;
        });
      }

      if (sameDayUseUpdate) {
        sameDayUseUpdate.addEventListener('change', async () => {
          if (!sameDayUseUpdate.checked) return;
          if (sameDayUseOnce) sameDayUseOnce.checked = false;
          clearInlineError(sameDayAddressError);
          const address = readAddress(sameDayAddressFields);
          if (!isAddressComplete(address)) {
            showInlineError(sameDayAddressError, 'Please complete all required address fields.');
            sameDayUseUpdate.checked = false;
            return;
          }
          try {
            await updateUserShipping(address);
            applySameDayAddress(address, 'address');
            if (sameDayDetail) sameDayDetail.setAttribute('hidden', '');
          } catch (err) {
            showInlineError(sameDayAddressError, err.message || 'Unable to update shipping address.');
          }
          sameDayUseUpdate.checked = false;
        });
      }

      if (sameDayUseSave) {
        sameDayUseSave.addEventListener('change', () => {
          if (!sameDayUseSave.checked) return;
          if (sameDayUseOnce) sameDayUseOnce.checked = false;
          showInlineError(sameDayAddressError, '', signInHtml);
          sameDayUseSave.checked = false;
        });
      }

      if (sameDayCoordUpdate) {
        sameDayCoordUpdate.addEventListener('click', () => {
          clearInlineError(sameDayCoordError);
          const zip = trimValue(sameDayCoordZip && sameDayCoordZip.value);
          const coord = trimValue(sameDayCoord && sameDayCoord.value);
          if (!zip || !coord) {
            showInlineError(sameDayCoordError, 'Please enter both ZIP code and coordinates.');
            return;
          }
          applySameDayCoords(zip, coord);
          if (sameDayDetail) sameDayDetail.setAttribute('hidden', '');
        });
      }

      if (sameDaySavedSelect) {
        sameDaySavedSelect.addEventListener('change', () => {
          const value = sameDaySavedSelect.value || '';
          renderSavedLocations(value);
          if (value && !isAtDeepestLevel('location', value) && typeof sameDaySavedSelect.showPicker === 'function') {
            try { sameDaySavedSelect.showPicker(); } catch (e) { /* ignore */ }
          }
        });
      }

      if (sameDaySavedUpdate) {
        sameDaySavedUpdate.addEventListener('click', () => {
          clearInlineError(sameDaySavedError);
          if (!sameDaySavedSelect || sameDaySavedSelect.disabled) {
            showInlineError(sameDaySavedError, '', signInHtml);
            return;
          }
          const pathValue = sameDaySavedSelect.value || '';
          const node = findNodeByPath('location', pathValue);
          if (!node) {
            showInlineError(sameDaySavedError, 'Select a saved location.');
            return;
          }
          const zip = trimValue(node.zip || '');
          const coordinate = trimValue(node.coordinate || '');
          if (!zip || !coordinate) {
            showInlineError(sameDaySavedError, 'Selected location is missing ZIP code or coordinates.');
            return;
          }
          applySameDaySaved({
            path: pathValue,
            label: node.label || 'Saved Location',
            zip: zip,
            coordinate: coordinate
          });
          if (sameDayDetail) sameDayDetail.setAttribute('hidden', '');
        });
      }

      function updateShippingOptionSelected() {
        document.querySelectorAll('.shipping-option').forEach((option) => {
          const radio = option.querySelector('input[type="radio"]');
          if (radio && radio.checked) {
            option.classList.add('is-selected');
          } else {
            option.classList.remove('is-selected');
          }
        });
      }

      shippingInputs.forEach((input) => {
        input.addEventListener('change', () => {
          const method = selectedShippingMethod();
          collapseOtherShippingDetails(method);
          updateShippingOptionSelected();
          updateSummaryTotals();
          updateShippingCostLabels();
          queueTaxQuote();
          persistSelectedMethod();
        });
      });

      updateSameDayPanels();
      updateShippingCostLabels();
      updateSummaryTotals();
      updateShippingOptionSelected();
      queueTaxQuote();

      if (checkoutButton) {
        checkoutButton.addEventListener('click', (event) => {
          const hasMismatch = itemEls.some((itemEl) => itemEl.classList.contains('is-mismatch'));
          if (hasMismatch) {
            event.preventDefault();
            showMessage('Group quantities must match item quantities before checkout.');
            return;
          }

          // Validate requireSub settings
          const requireSubErrors = validateRequireSub();
          if (requireSubErrors.length > 0) {
            event.preventDefault();
            showMessage(requireSubErrors.join('. '));
            return;
          }

          clearMessage();
        });
      }
    })();
  </script>
</body>
</html>
