<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/invoice_service.php';

$user = site_require_auth();
$message = '';
$messageIsError = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'approve_order' || $action === 'decline_order') {
        $orderId = trim((string) ($_POST['order_id'] ?? ''));
        if ($orderId !== '') {
            $pdo = opd_db();
            $check = $pdo->prepare(
                "SELECT id FROM orders WHERE id = ? AND clientUserId = ? AND approvalStatus = 'Waiting' LIMIT 1"
            );
            $check->execute([$orderId, $user['id']]);
            if ($check->fetch()) {
                $newStatus = $action === 'approve_order' ? 'Approved' : 'Declined';
                $pdo->prepare('UPDATE orders SET approvalStatus = ?, updatedAt = ? WHERE id = ?')
                    ->execute([$newStatus, gmdate('Y-m-d H:i:s'), $orderId]);
                $message = $action === 'approve_order' ? 'Order approved.' : 'Order declined.';
            }
        }
    }
    if ($action === 'reorder') {
        $postedProductId = trim((string) ($_POST['productId'] ?? ''));
        $postedVariantId = trim((string) ($_POST['variantId'] ?? ''));
        $quantity = (int) ($_POST['quantity'] ?? 1);
        $arrivalDate = $_POST['arrivalDate'] ?? null;
        if ($postedProductId === '') {
            $message = 'Select a valid product.';
            $messageIsError = true;
        } else {
            $product = site_get_product($postedProductId);
            if (!$product) {
                $message = 'Product not found.';
                $messageIsError = true;
            } else {
                $variantId = $postedVariantId !== '' ? $postedVariantId : null;
                if ($variantId) {
                    $variants = site_get_product_variants($postedProductId);
                    $variantIds = array_map(fn($row) => (string) ($row['id'] ?? ''), $variants);
                    if (!in_array($variantId, $variantIds, true)) {
                        $variantId = null;
                    }
                }
                $quantity = max(1, $quantity);
                $addedItemId = site_add_to_cart($postedProductId, $quantity, $variantId ?: null, is_string($arrivalDate) ? $arrivalDate : null);
                if ($addedItemId === null || $addedItemId === '') {
                    $message = 'This product is no longer available.';
                    $messageIsError = true;
                } else {
                    $message = 'Added to cart.';
                }
            }
        }
    }
}
$orders = site_get_orders_for_user($user['id']);
$paymentMethods = [];
$vendorNames = [];
$clientNames = [];
$csrf = site_csrf_token();

// Load invoice data for all user orders
$invoicesByOrder = [];
opd_ensure_invoice_tables();
if ($orders) {
    $invPdo = opd_db();
    $orderIdsForInv = array_filter(array_map(fn($o) => $o['id'] ?? null, $orders));
    if ($orderIdsForInv) {
        $placeholders = implode(',', array_fill(0, count($orderIdsForInv), '?'));
        $invStmt = $invPdo->prepare("SELECT id, orderId, invoiceNumber, status, pdfPath FROM invoices WHERE orderId IN ({$placeholders})");
        $invStmt->execute(array_values($orderIdsForInv));
        foreach ($invStmt->fetchAll() as $inv) {
            $invoicesByOrder[$inv['orderId']] = $inv;
        }
    }
}

if ($orders) {
    $pdo = opd_db();
    $orderIds = array_values(array_filter(array_map(fn($row) => $row['id'] ?? null, $orders)));
    if ($orderIds) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $tableCheck = $pdo->prepare(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('payments')"
        );
        $tableCheck->execute();
        $tables = array_map('strtolower', $tableCheck->fetchAll(PDO::FETCH_COLUMN));

        if (in_array('payments', $tables, true)) {
            $payStmt = $pdo->prepare(
                "SELECT * FROM payments WHERE orderId IN ({$placeholders}) ORDER BY capturedAt DESC, updatedAt DESC"
            );
            $payStmt->execute($orderIds);
            foreach ($payStmt->fetchAll() as $payment) {
                $orderId = (string) ($payment['orderId'] ?? '');
                if ($orderId !== '' && !isset($paymentMethods[$orderId])) {
                    $paymentMethods[$orderId] = trim((string) ($payment['method'] ?? ''));
                }
            }
        }
    }

    $clientIds = [];
    $vendorUserIds = [];
    foreach ($orders as $order) {
        $orderUserId = (string) ($order['userId'] ?? '');
        $orderClientUserId = (string) ($order['clientUserId'] ?? '');
        $orderClientId = (string) ($order['clientId'] ?? '');
        if ($orderUserId === $user['id'] && $orderClientId !== '') {
            $clientIds[] = $orderClientId;
        }
        if ($orderUserId !== '' && $orderUserId !== $user['id'] && $orderClientUserId === $user['id']) {
            $vendorUserIds[] = $orderUserId;
        }
    }

    $clientIds = array_values(array_unique($clientIds));
    if ($clientIds) {
        $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
        $clientStmt = $pdo->prepare(
            "SELECT id, name, email FROM clients WHERE userId = ? AND id IN ({$placeholders})"
        );
        $clientStmt->execute(array_merge([$user['id']], $clientIds));
        foreach ($clientStmt->fetchAll() as $client) {
            $id = (string) ($client['id'] ?? '');
            $name = trim((string) ($client['name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($client['email'] ?? ''));
            }
            if ($id !== '' && $name !== '') {
                $clientNames[$id] = $name;
            }
        }
    }

    $vendorUserIds = array_values(array_unique($vendorUserIds));
    if ($vendorUserIds) {
        $placeholders = implode(',', array_fill(0, count($vendorUserIds), '?'));
        $vendorStmt = $pdo->prepare(
            "SELECT linkedUserId, name, contact, email FROM vendors WHERE userId = ? AND linkedUserId IN ({$placeholders})"
        );
        $vendorStmt->execute(array_merge([$user['id']], $vendorUserIds));
        foreach ($vendorStmt->fetchAll() as $vendor) {
            $linkedUserId = (string) ($vendor['linkedUserId'] ?? '');
            $name = trim((string) ($vendor['name'] ?? ''));
            if ($name === '') {
                $name = trim((string) ($vendor['contact'] ?? ''));
            }
            if ($name === '') {
                $name = trim((string) ($vendor['email'] ?? ''));
            }
            if ($linkedUserId !== '' && $name !== '') {
                $vendorNames[$linkedUserId] = $name;
            }
        }

        $missingVendorIds = array_values(array_diff($vendorUserIds, array_keys($vendorNames)));
        if ($missingVendorIds) {
            $placeholders = implode(',', array_fill(0, count($missingVendorIds), '?'));
            $userStmt = $pdo->prepare("SELECT id, name, email FROM users WHERE id IN ({$placeholders})");
            $userStmt->execute($missingVendorIds);
            foreach ($userStmt->fetchAll() as $vendorUser) {
                $id = (string) ($vendorUser['id'] ?? '');
                $name = trim((string) ($vendorUser['name'] ?? ''));
                if ($name === '') {
                    $name = trim((string) ($vendorUser['email'] ?? ''));
                }
                if ($id !== '' && $name !== '') {
                    $vendorNames[$id] = $name;
                }
            }
        }
    }

    // Collect accounting data per order for filtering
    $accountingByOrder = [];
    if ($orderIds) {
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $accStmt = $pdo->prepare(
            "SELECT orderId, groupsJson FROM order_accounting WHERE orderId IN ({$placeholders})"
        );
        $accStmt->execute($orderIds);
        foreach ($accStmt->fetchAll() as $accRow) {
            $oid = (string) ($accRow['orderId'] ?? '');
            $groups = json_decode($accRow['groupsJson'] ?? '[]', true);
            if (!is_array($groups)) {
                $groups = [];
            }
            $locs = [];
            $c1s = [];
            $c2s = [];
            foreach ($groups as $g) {
                $locPath = trim((string) ($g['location'] ?? ''));
                $c1Path = trim((string) ($g['code1'] ?? ''));
                $c2Path = trim((string) ($g['code2'] ?? ''));
                if ($locPath !== '') {
                    $locs[$locPath] = true;
                }
                if ($c1Path !== '') {
                    $c1s[$c1Path] = true;
                }
                if ($c2Path !== '') {
                    $c2s[$c2Path] = true;
                }
            }
            $accountingByOrder[$oid] = [
                'locations' => array_keys($locs),
                'code1s' => array_keys($c1s),
                'code2s' => array_keys($c2s),
            ];
        }
    }

    // Collect accounting structure for filter option lists
    $accountingStructure = site_get_accounting_structure($user['id']);
    $filterLocations = [];
    $filterCode1s = [];
    $filterCode2s = [];
    $collectLabels = function (array $nodes, string $prefix = '') use (&$collectLabels, &$result) {
        foreach ($nodes as $node) {
            $label = trim((string) ($node['label'] ?? ''));
            if ($label === '') continue;
            $path = $prefix !== '' ? $prefix . ' > ' . $label : $label;
            $result[] = $path;
            if (!empty($node['children']) && is_array($node['children'])) {
                $collectLabels($node['children'], $path);
            }
        }
    };
    $result = [];
    $collectLabels($accountingStructure['location'] ?? []);
    $filterLocations = $result;
    $result = [];
    $collectLabels($accountingStructure['code1'] ?? []);
    $filterCode1s = $result;
    $result = [];
    $collectLabels($accountingStructure['code2'] ?? []);
    $filterCode2s = $result;
}

// Collect unique vendor/client names for filter dropdowns
$uniqueVendorNames = array_values(array_unique(array_values($vendorNames)));
sort($uniqueVendorNames);
$uniqueClientNames = array_values(array_unique(array_values($clientNames)));
sort($uniqueClientNames);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Orders - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260326d" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel">
          <h2>Order history</h2>

          <div class="order-filters">
            <select id="filter-time" class="order-filter-select">
              <option value="all" selected>All Time</option>
              <option value="30">Last 30 Days</option>
              <option value="90">Last 3 Months</option>
              <option value="year"><?php echo date('Y'); ?></option>
              <option value="lastyear"><?php echo (int) date('Y') - 1; ?></option>
              <option value="custom">Custom</option>
            </select>
            <div class="filter-custom-dates" id="filter-custom-dates" style="display:none;">
              <input type="date" id="filter-date-from" class="order-filter-date" />
              <span>to</span>
              <input type="date" id="filter-date-to" class="order-filter-date" />
            </div>
            <div id="date-filter-error" class="notice is-error" style="display:none;" role="alert"></div>

            <div class="filter-multi" data-filter="vendor">
              <button type="button" class="filter-multi-btn">All Vendors</button>
              <div class="filter-multi-dropdown" hidden>
                <?php foreach ($uniqueVendorNames as $vn): ?>
                  <label><input type="checkbox" value="<?php echo htmlspecialchars($vn, ENT_QUOTES); ?>" /> <?php echo htmlspecialchars($vn, ENT_QUOTES); ?></label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="filter-multi" data-filter="client">
              <button type="button" class="filter-multi-btn">All Clients</button>
              <div class="filter-multi-dropdown" hidden>
                <?php foreach ($uniqueClientNames as $cn): ?>
                  <label><input type="checkbox" value="<?php echo htmlspecialchars($cn, ENT_QUOTES); ?>" /> <?php echo htmlspecialchars($cn, ENT_QUOTES); ?></label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="filter-multi" data-filter="location">
              <button type="button" class="filter-multi-btn">All Locations</button>
              <div class="filter-multi-dropdown" hidden>
                <?php foreach ($filterLocations as $fl): ?>
                  <label><input type="checkbox" value="<?php echo htmlspecialchars($fl, ENT_QUOTES); ?>" /> <?php echo htmlspecialchars($fl, ENT_QUOTES); ?></label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="filter-multi" data-filter="code1">
              <button type="button" class="filter-multi-btn">All Code 1</button>
              <div class="filter-multi-dropdown" hidden>
                <?php foreach ($filterCode1s as $fc1): ?>
                  <label><input type="checkbox" value="<?php echo htmlspecialchars($fc1, ENT_QUOTES); ?>" /> <?php echo htmlspecialchars($fc1, ENT_QUOTES); ?></label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="filter-multi" data-filter="code2">
              <button type="button" class="filter-multi-btn">All Code 2</button>
              <div class="filter-multi-dropdown" hidden>
                <?php foreach ($filterCode2s as $fc2): ?>
                  <label><input type="checkbox" value="<?php echo htmlspecialchars($fc2, ENT_QUOTES); ?>" /> <?php echo htmlspecialchars($fc2, ENT_QUOTES); ?></label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <?php if ($message): ?>
            <div class="notice <?php echo $messageIsError ? 'is-error' : ''; ?>">
              <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
            </div>
          <?php endif; ?>
          <?php if (!$orders): ?>
            <div class="notice">No orders yet.</div>
          <?php else: ?>
            <div class="table-wrap">
              <table class="table">
                <thead>
                  <tr>
                    <th></th>
                    <th>Order</th>
                    <th>Payment Method</th>
                    <th>Vendor Name</th>
                    <th>Client Name</th>
                    <th>Total</th>
                    <th>Date</th>
                    <th>Approval</th>
                    <th>Invoice</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($orders as $order): ?>
                    <?php
                    $orderId = (string) ($order['id'] ?? '');
                    $orderUserId = (string) ($order['userId'] ?? '');
                    $orderClientUserId = (string) ($order['clientUserId'] ?? '');
                    $orderClientId = (string) ($order['clientId'] ?? '');
                    $paymentMethod = trim((string) ($paymentMethods[$orderId] ?? ''));
                    $vendorName = 'None';
                    if ($orderUserId !== '' && $orderUserId !== $user['id'] && $orderClientUserId === $user['id']) {
                        $vendorName = $vendorNames[$orderUserId] ?? 'None';
                    }
                    $clientName = 'None';
                    if ($orderUserId === $user['id'] && $orderClientId !== '') {
                        $clientName = $clientNames[$orderClientId] ?? 'None';
                    }
                    $createdAt = trim((string) ($order['createdAt'] ?? ''));
                    $createdDate = $createdAt !== '' ? substr($createdAt, 0, 10) : '';
                    $detailsId = 'order-details-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $orderId);
                    $orderAccounting = $accountingByOrder[$orderId] ?? ['locations' => [], 'code1s' => [], 'code2s' => []];
                    ?>
                    <tr data-order-row data-order-id="<?php echo htmlspecialchars($orderId, ENT_QUOTES); ?>"
                      data-date="<?php echo htmlspecialchars($createdDate, ENT_QUOTES); ?>"
                      data-vendor="<?php echo htmlspecialchars($vendorName, ENT_QUOTES); ?>"
                      data-client="<?php echo htmlspecialchars($clientName, ENT_QUOTES); ?>"
                      data-locations="<?php echo htmlspecialchars(implode('|', $orderAccounting['locations']), ENT_QUOTES); ?>"
                      data-code1s="<?php echo htmlspecialchars(implode('|', $orderAccounting['code1s']), ENT_QUOTES); ?>"
                      data-code2s="<?php echo htmlspecialchars(implode('|', $orderAccounting['code2s']), ENT_QUOTES); ?>"
                    >
                      <td>
                        <button
                          type="button"
                          class="order-toggle"
                          data-order-toggle
                          aria-expanded="false"
                          aria-label="Expand order details"
                          aria-controls="<?php echo htmlspecialchars($detailsId, ENT_QUOTES); ?>"
                        >
                          +
                        </button>
                      </td>
                      <td><?php echo htmlspecialchars($order['number'] ?? $orderId, ENT_QUOTES); ?></td>
                      <td><?php echo htmlspecialchars($paymentMethod !== '' ? $paymentMethod : 'None', ENT_QUOTES); ?></td>
                      <td><?php echo htmlspecialchars($vendorName, ENT_QUOTES); ?></td>
                      <td><?php echo htmlspecialchars($clientName, ENT_QUOTES); ?></td>
                      <td>$<?php echo number_format((float) ($order['total'] ?? 0), 2); ?></td>
                      <td><?php echo htmlspecialchars($createdDate, ENT_QUOTES); ?></td>
                      <td>
                        <?php
                        $approvalStatus = (string) ($order['approvalStatus'] ?? 'Not required');
                        $isClientView = ($orderClientUserId === $user['id'] && $orderUserId !== $user['id']);
                        ?>
                        <?php if ($isClientView && $approvalStatus === 'Waiting'): ?>
                          <div class="approval-actions">
                            <form method="POST" style="display:inline;">
                              <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                              <input type="hidden" name="action" value="approve_order" />
                              <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId, ENT_QUOTES); ?>" />
                              <button class="btn btn-sm" type="submit">Approve</button>
                            </form>
                            <form method="POST" style="display:inline;">
                              <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                              <input type="hidden" name="action" value="decline_order" />
                              <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($orderId, ENT_QUOTES); ?>" />
                              <button class="btn-outline btn-sm" type="submit">Decline</button>
                            </form>
                          </div>
                        <?php elseif ($approvalStatus === 'Approved'): ?>
                          <span class="badge badge-success">Approved</span>
                        <?php elseif ($approvalStatus === 'Auto Approved'): ?>
                          <span class="badge badge-success">Auto Approved</span>
                        <?php elseif ($approvalStatus === 'Declined'): ?>
                          <span class="badge badge-danger">Declined</span>
                        <?php elseif ($approvalStatus === 'Waiting'): ?>
                          <span class="badge badge-warning">Waiting</span>
                        <?php else: ?>
                          <span class="badge badge-muted">&mdash;</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php
                        $orderInvoice = $invoicesByOrder[$orderId] ?? null;
                        if ($orderInvoice):
                        ?>
                          <a href="/api/invoices.php?download=1&id=<?php echo urlencode($orderInvoice['id']); ?>" target="_blank" class="btn-outline btn-sm" title="Download Invoice <?php echo htmlspecialchars($orderInvoice['invoiceNumber'], ENT_QUOTES); ?>">Invoice</a>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <tr class="order-details-row" data-order-details-row data-order-id="<?php echo htmlspecialchars($orderId, ENT_QUOTES); ?>" hidden>
                      <td colspan="9">
                        <div id="<?php echo htmlspecialchars($detailsId, ENT_QUOTES); ?>" class="order-details-box" data-order-details-box>
                          <div class="order-details-loading">Click on an order row above to view details and manage the order.</div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
  <script nonce="<?php echo opd_csp_nonce(); ?>">
    (function () {
      var orderRows = document.querySelectorAll('[data-order-row]')
      if (!orderRows.length) {
        return
      }
      var csrfToken = <?php echo json_encode($csrf); ?>;
      var orderCache = {}
      var saveTimers = {}

      function parseJsonResponse(resp) {
        return resp.json().catch(function () {
          return {}
        }).then(function (data) {
          if (!resp.ok) {
            var err = new Error(data.error || 'Request failed')
            err.status = resp.status
            throw err
          }
          return data
        })
      }

      function roundMoney(value) {
        return Math.round((Number(value) || 0) * 100) / 100
      }

      function formatMoney(value) {
        return '$' + roundMoney(value).toFixed(2)
      }

      function distributeByAmount(amounts, totalAmount, totalValue) {
        var results = []
        if (!totalAmount || !totalValue) {
          for (var i = 0; i < amounts.length; i += 1) {
            results.push(0)
          }
          return results
        }
        for (var j = 0; j < amounts.length; j += 1) {
          results.push(roundMoney((amounts[j] / totalAmount) * totalValue))
        }
        var sum = 0
        var maxIndex = -1
        var maxAmount = -1
        for (var k = 0; k < amounts.length; k += 1) {
          sum += results[k]
          if (amounts[k] > maxAmount) {
            maxAmount = amounts[k]
            maxIndex = k
          }
        }
        var diff = roundMoney(totalValue - sum)
        if (maxIndex >= 0 && diff !== 0) {
          results[maxIndex] = roundMoney(results[maxIndex] + diff)
        }
        return results
      }

      function computeItemTotals(items, orderTax, orderShipping) {
        var amounts = []
        var totalAmount = 0
        for (var i = 0; i < items.length; i += 1) {
          var amount = roundMoney(Number(items[i].price || 0) * Number(items[i].quantity || 0))
          amounts.push(amount)
          totalAmount += amount
        }
        var taxes = distributeByAmount(amounts, totalAmount, orderTax)
        var shipping = distributeByAmount(amounts, totalAmount, orderShipping)
        var totals = {}
        for (var j = 0; j < items.length; j += 1) {
          var itemId = items[j].id
          var itemAmount = amounts[j]
          var itemTax = taxes[j]
          var itemShipping = shipping[j]
          totals[itemId] = {
            amount: itemAmount,
            tax: itemTax,
            shipping: itemShipping,
            total: roundMoney(itemAmount + itemTax + itemShipping)
          }
        }
        return totals
      }

      function computeSplitTotals(itemAmount, itemTax, itemShipping, splits, price) {
        var amounts = []
        var totalAmount = itemAmount
        for (var i = 0; i < splits.length; i += 1) {
          var qty = Number(splits[i].qty || 0)
          amounts.push(roundMoney(qty * price))
        }
        var taxes = distributeByAmount(amounts, totalAmount, itemTax)
        var shipping = distributeByAmount(amounts, totalAmount, itemShipping)
        var totals = []
        for (var j = 0; j < splits.length; j += 1) {
          var amount = amounts[j]
          var tax = taxes[j]
          var ship = shipping[j]
          totals.push({
            amount: amount,
            tax: tax,
            shipping: ship,
            total: roundMoney(amount + tax + ship)
          })
        }
        return totals
      }

      function normalizeNodeLabel(value) {
        return String(value || '').trim()
      }

      function parseAccountingPath(value) {
        if (Array.isArray(value)) {
          return value.map(function (part) { return normalizeNodeLabel(part) }).filter(Boolean)
        }
        if (typeof value === 'string') {
          var trimmed = value.trim()
          if (!trimmed) {
            return []
          }
          return trimmed.split(' > ').map(function (part) { return normalizeNodeLabel(part) }).filter(Boolean)
        }
        return []
      }

      function joinAccountingPath(parts) {
        return parts.filter(Boolean).join(' > ')
      }

      function findChildNode(nodes, label) {
        var target = normalizeNodeLabel(label)
        if (!target) {
          return null
        }
        for (var i = 0; i < (nodes || []).length; i += 1) {
          if (normalizeNodeLabel(nodes[i].label) === target) {
            return nodes[i]
          }
        }
        return null
      }

      function getNodesAtLevel(structure, category, pathParts, level) {
        var nodes = Array.isArray(structure[category]) ? structure[category] : []
        for (var i = 0; i < level; i += 1) {
          var parentLabel = pathParts[i]
          if (!parentLabel) {
            return []
          }
          var parent = findChildNode(nodes, parentLabel)
          if (!parent || !Array.isArray(parent.children)) {
            return []
          }
          nodes = parent.children
        }
        return nodes
      }

      function isAtDeepestLevel(structure, category, pathValue) {
        if (!pathValue) return true
        var pathParts = parseAccountingPath(pathValue)
        if (!pathParts.length) return true
        var nodes = Array.isArray(structure[category]) ? structure[category] : []
        for (var i = 0; i < pathParts.length; i += 1) {
          var node = findChildNode(nodes, pathParts[i])
          if (!node) return true
          if (i === pathParts.length - 1) {
            return !Array.isArray(node.children) || node.children.length === 0
          }
          nodes = node.children || []
        }
        return true
      }

      function buildCascadingOptions(structure, category, nodes, pathParts, level, fragment) {
        var indent = ''
        for (var i = 0; i < level; i++) { indent += '\u00A0\u00A0' }
        for (var j = 0; j < nodes.length; j += 1) {
          var node = nodes[j]
          var label = normalizeNodeLabel(node.label)
          if (!label) continue

          var option = document.createElement('option')
          var currentPath = pathParts.slice(0, level).concat([label])
          option.value = joinAccountingPath(currentPath)
          option.textContent = indent + label
          option.dataset.level = String(level)

          var fullPath = joinAccountingPath(pathParts)
          var optionPath = joinAccountingPath(currentPath)
          if (fullPath === optionPath || fullPath.indexOf(optionPath + ' > ') === 0) {
            option.selected = fullPath === optionPath
          }

          fragment.appendChild(option)

          // If this node is in the current path, show its children
          if (pathParts[level] === label && Array.isArray(node.children) && node.children.length > 0) {
            buildCascadingOptions(structure, category, node.children, pathParts, level + 1, fragment)
          }
        }
      }

      function rebuildCascadingSelect(select, structure, category, pathValue) {
        if (!select) return
        select.innerHTML = ''

        var blank = document.createElement('option')
        blank.value = ''
        blank.textContent = 'Select'
        select.appendChild(blank)

        var pathParts = parseAccountingPath(pathValue || '')
        var rootNodes = Array.isArray(structure[category]) ? structure[category] : []
        var fragment = document.createDocumentFragment()
        buildCascadingOptions(structure, category, rootNodes, pathParts, 0, fragment)
        select.appendChild(fragment)

        var currentValue = joinAccountingPath(pathParts)
        if (currentValue) {
          select.value = currentValue
        }
      }

      function collapseCascadingSelect(select) {
        if (!select) return
        if (select.classList.contains('is-expanded')) {
          select.size = 1
          select.classList.remove('is-expanded')
        }
      }

      function expandCascadingSelect(select) {
        if (!select) return
        if (typeof select.showPicker === 'function') {
          try { select.showPicker() } catch (e) { /* ignore */ }
          return
        }
        var optionCount = select.options ? select.options.length : 0
        if (optionCount > 1) {
          select.size = Math.min(optionCount, 8)
          select.classList.add('is-expanded')
          select.focus()
        }
      }

      function buildAccountingField(structure, label, category, groupIndex, state, orderId) {
        var field = document.createElement('div')
        field.className = 'order-split-field'

        var title = document.createElement('label')
        title.textContent = label
        field.appendChild(title)

        var currentValue = (state.groups[groupIndex] || {})[category] || ''
        var nodes = Array.isArray(structure[category]) ? structure[category] : []

        if (typeof AccordionDropdown !== 'undefined') {
          AccordionDropdown.create(field, nodes, {
            value: currentValue,
            placeholder: 'Select ' + label.toLowerCase() + '...',
            onChange: function (path) {
              var group = state.groups[groupIndex] || {}
              group[category] = path
              state.groups[groupIndex] = group
              queueSave(orderId)
            }
          })
        } else {
          var select = document.createElement('select')
          select.className = 'accounting-cascading-select'
          select.dataset.category = category
          rebuildCascadingSelect(select, structure, category, currentValue)
          select.addEventListener('change', function (event) {
            var group = state.groups[groupIndex] || {}
            group[category] = event.target.value
            state.groups[groupIndex] = group
            queueSave(orderId)
            rebuildCascadingSelect(select, structure, category, event.target.value)
            if (event.target.value && !isAtDeepestLevel(structure, category, event.target.value)) {
              setTimeout(function () { expandCascadingSelect(select) }, 50)
            } else {
              collapseCascadingSelect(select)
            }
          })
          select.addEventListener('blur', function () { collapseCascadingSelect(select) })
          field.appendChild(select)
        }

        return field
      }

      function normalizeOrderData(orderId, data, container) {
        var rawGroups = data.accounting && Array.isArray(data.accounting.groups) ? data.accounting.groups : []
        var rawAssignments = data.accounting && data.accounting.assignments ? data.accounting.assignments : {}
        var items = Array.isArray(data.items) ? data.items : []
        var newGroups = []
        var newAssignments = {}
        for (var i = 0; i < items.length; i += 1) {
          var item = items[i]
          var itemId = item.id
          var splits = Array.isArray(rawAssignments[itemId]) ? rawAssignments[itemId] : []
          if (!splits.length) {
            splits = [{ groupIndex: 0, qty: item.quantity || 1 }]
          }
          var normalizedSplits = []
          for (var j = 0; j < splits.length; j += 1) {
            var split = splits[j] || {}
            var source = rawGroups[split.groupIndex] || {}
            var newIndex = newGroups.length
            newGroups.push({
              location: source.location || '',
              code1: source.code1 || '',
              code2: source.code2 || ''
            })
            var qty = parseInt(split.qty || '0', 10)
            if (isNaN(qty)) {
              qty = 0
            }
            normalizedSplits.push({ groupIndex: newIndex, qty: qty })
          }
          newAssignments[itemId] = normalizedSplits
        }
        orderCache[orderId] = {
          order: data.order || {},
          items: items,
          groups: newGroups,
          assignments: newAssignments,
          structure: data.accountingStructure || {},
          container: container
        }
      }

      function queueSave(orderId) {
        if (saveTimers[orderId]) {
          clearTimeout(saveTimers[orderId])
        }
        saveTimers[orderId] = setTimeout(function () {
          saveOrderAccounting(orderId)
        }, 500)
      }

      function saveOrderAccounting(orderId) {
        var state = orderCache[orderId]
        if (!state) {
          return
        }
        fetch('/api/order_details.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
          },
          body: JSON.stringify({
            orderId: orderId,
            groups: state.groups,
            assignments: state.assignments
          })
        }).then(parseJsonResponse).catch(function () {
          // Silent fail; user can retry edits.
        })
      }

      function renderOrderDetails(orderId) {
        var state = orderCache[orderId]
        if (!state || !state.container) {
          return
        }
        var container = state.container
        container.innerHTML = ''

        var items = state.items || []
        if (!items.length) {
          var empty = document.createElement('div')
          empty.className = 'order-details-loading'
          empty.textContent = 'No items in this order.'
          container.appendChild(empty)
          return
        }

        var order = state.order || {}
        var orderTax = Number(order.tax || 0)
        var orderShipping = Number(order.shipping || 0)
        var itemTotals = computeItemTotals(items, orderTax, orderShipping)

        var itemsWrap = document.createElement('div')
        itemsWrap.className = 'order-items'

        for (var i = 0; i < items.length; i += 1) {
          var item = items[i]
          var itemId = item.id
          var itemPrice = Number(item.price || 0)
          var itemQty = Number(item.quantity || 0)
          var totals = itemTotals[itemId] || { amount: 0, tax: 0, shipping: 0, total: 0 }
          var splits = state.assignments[itemId] || []
          var splitTotals = computeSplitTotals(totals.amount, totals.tax, totals.shipping, splits, itemPrice)

          var card = document.createElement('div')
          card.className = 'order-item-card'

          var row = document.createElement('div')
          row.className = 'order-item-row'

          var media = document.createElement('div')
          media.className = 'order-item-media'
          if (item.imageUrl) {
            var img = document.createElement('img')
            img.src = item.imageUrl
            img.alt = item.name || 'Product'
            media.appendChild(img)
          } else {
            var placeholder = document.createElement('div')
            placeholder.className = 'image-placeholder'
            placeholder.textContent = 'No image'
            media.appendChild(placeholder)
          }
          row.appendChild(media)

          var info = document.createElement('div')
          info.className = 'order-item-info'
          if (item.variantName) {
            var prodName = document.createElement('div')
            prodName.className = 'order-item-product-name'
            prodName.textContent = item.productName || 'Product'
            info.appendChild(prodName)
            var name = document.createElement('div')
            name.className = 'order-item-name'
            name.textContent = item.variantName
            info.appendChild(name)
          } else {
            var name = document.createElement('div')
            name.className = 'order-item-name'
            name.textContent = item.productName || item.name || 'Product'
            info.appendChild(name)
          }
          var meta = document.createElement('div')
          meta.className = 'order-item-meta'
          meta.textContent = 'Price ' + formatMoney(itemPrice)
          info.appendChild(meta)
          row.appendChild(info)

          var qtyWrap = document.createElement('div')
          qtyWrap.className = 'order-item-qty'
          var qtyLabel = document.createElement('label')
          qtyLabel.textContent = 'Qty'
          qtyWrap.appendChild(qtyLabel)
          var qtyInput = document.createElement('input')
          qtyInput.type = 'number'
          qtyInput.readOnly = true
          qtyInput.value = itemQty
          qtyWrap.appendChild(qtyInput)
          row.appendChild(qtyWrap)

          var actions = document.createElement('div')
          actions.className = 'order-item-actions'
          var form = document.createElement('form')
          form.method = 'POST'
          form.action = '/dashboard-orders.php'
          var csrfField = document.createElement('input')
          csrfField.type = 'hidden'
          csrfField.name = '_csrf'
          csrfField.value = csrfToken
          form.appendChild(csrfField)
          var actionField = document.createElement('input')
          actionField.type = 'hidden'
          actionField.name = 'action'
          actionField.value = 'reorder'
          form.appendChild(actionField)
          var productField = document.createElement('input')
          productField.type = 'hidden'
          productField.name = 'productId'
          productField.value = item.productId || ''
          form.appendChild(productField)
          var variantField = document.createElement('input')
          variantField.type = 'hidden'
          variantField.name = 'variantId'
          variantField.value = item.variantId || ''
          form.appendChild(variantField)
          var qtyField = document.createElement('input')
          qtyField.type = 'hidden'
          qtyField.name = 'quantity'
          qtyField.value = itemQty
          form.appendChild(qtyField)
          if (item.arrivalDate) {
            var arrivalField = document.createElement('input')
            arrivalField.type = 'hidden'
            arrivalField.name = 'arrivalDate'
            arrivalField.value = item.arrivalDate
            form.appendChild(arrivalField)
          }
          var reorderBtn = document.createElement('button')
          reorderBtn.type = 'submit'
          reorderBtn.className = 'btn'
          reorderBtn.textContent = 'Reorder'
          form.appendChild(reorderBtn)
          actions.appendChild(form)
          row.appendChild(actions)

          card.appendChild(row)

          var splitList = document.createElement('div')
          splitList.className = 'order-split-list'
          for (var s = 0; s < splits.length; s += 1) {
            var split = splits[s]
            var splitRow = document.createElement('div')
            splitRow.className = 'order-split-row'

            var qtyFieldWrap = document.createElement('div')
            qtyFieldWrap.className = 'order-split-field'
            var qtyTitle = document.createElement('label')
            qtyTitle.textContent = 'Qty'
            qtyFieldWrap.appendChild(qtyTitle)
            var qtyInputSplit = document.createElement('input')
            qtyInputSplit.type = 'number'
            qtyInputSplit.className = 'split-qty-input'
            qtyInputSplit.min = '0'
            qtyInputSplit.value = split.qty
            qtyInputSplit.addEventListener('change', (function (orderId, itemId, splitIndex) {
              return function (event) {
                var nextQty = parseInt(event.target.value || '0', 10)
                if (!Number.isFinite(nextQty)) {
                  nextQty = 0
                }
                state.assignments[itemId][splitIndex].qty = nextQty
                queueSave(orderId)
                renderOrderDetails(orderId)
              }
            })(orderId, itemId, s))
            qtyFieldWrap.appendChild(qtyInputSplit)

            // Add Qty first, then accounting fields (Location, Code 1, Code 2)
            splitRow.appendChild(qtyFieldWrap)
            splitRow.appendChild(buildAccountingField(state.structure, 'Location', 'location', split.groupIndex, state, orderId))
            splitRow.appendChild(buildAccountingField(state.structure, 'Code 1', 'code1', split.groupIndex, state, orderId))
            splitRow.appendChild(buildAccountingField(state.structure, 'Code 2', 'code2', split.groupIndex, state, orderId))

            if (splits.length > 1) {
              var deleteBtn = document.createElement('button')
              deleteBtn.type = 'button'
              deleteBtn.className = 'order-split-delete'
              deleteBtn.textContent = '×'
              deleteBtn.title = 'Remove this split'
              deleteBtn.addEventListener('click', (function (orderId, itemId, splitIndex) {
                return function () {
                  state.assignments[itemId].splice(splitIndex, 1)
                  queueSave(orderId)
                  renderOrderDetails(orderId)
                }
              })(orderId, itemId, s))
              splitRow.appendChild(deleteBtn)
            }

            splitList.appendChild(splitRow)

            var subtotal = splitTotals[s] || { amount: 0, tax: 0, shipping: 0, total: 0 }
            var subtotalRow = document.createElement('div')
            subtotalRow.className = 'order-split-subtotal'

            // Add button column on the left (only on last split)
            var addCol = document.createElement('div')
            addCol.className = 'subtotal-add-col'
            if (s === splits.length - 1) {
              var addBtn = document.createElement('button')
              addBtn.type = 'button'
              addBtn.className = 'order-split-add'
              addBtn.textContent = '+'
              addBtn.title = 'Add Split'
              addBtn.addEventListener('click', (function (orderId, itemId) {
                return function () {
                  var newIndex = state.groups.length
                  state.groups.push({ location: '', code1: '', code2: '' })
                  if (!state.assignments[itemId]) {
                    state.assignments[itemId] = []
                  }
                  state.assignments[itemId].push({ groupIndex: newIndex, qty: 0 })
                  queueSave(orderId)
                  renderOrderDetails(orderId)
                }
              })(orderId, itemId))
              addCol.appendChild(addBtn)
            }
            subtotalRow.appendChild(addCol)

            // Subtotal values on the right
            var valuesWrap = document.createElement('div')
            valuesWrap.className = 'subtotal-values'
            valuesWrap.innerHTML =
              '<div class="subtotal-col"><div class="subtotal-label">Amount</div><div class="subtotal-value">' + formatMoney(subtotal.amount) + '</div></div>' +
              '<div class="subtotal-col"><div class="subtotal-label">Tax</div><div class="subtotal-value">' + formatMoney(subtotal.tax) + '</div></div>' +
              '<div class="subtotal-col"><div class="subtotal-label">Shipping</div><div class="subtotal-value">' + formatMoney(subtotal.shipping) + '</div></div>' +
              '<div class="subtotal-col"><div class="subtotal-label">Total</div><div class="subtotal-value">' + formatMoney(subtotal.total) + '</div></div>'
            subtotalRow.appendChild(valuesWrap)

            splitList.appendChild(subtotalRow)
          }

          card.appendChild(splitList)

          var totalSplitQty = 0
          for (var v = 0; v < splits.length; v += 1) {
            totalSplitQty += splits[v].qty
          }

          if (totalSplitQty !== itemQty) {
            var warning = document.createElement('div')
            warning.className = 'order-split-warning'
            var diff = itemQty - totalSplitQty
            var diffText = diff > 0 ? diff + ' units unaccounted for' : Math.abs(diff) + ' units over-allocated'
            warning.innerHTML = '<strong>⚠ Warning:</strong> Split quantities (' + totalSplitQty + ') do not match item quantity (' + itemQty + '). ' + diffText + '.'
            card.appendChild(warning)
          }

          var itemTotalsBox = document.createElement('div')
          itemTotalsBox.className = 'order-item-totals'
          itemTotalsBox.innerHTML =
            '<span class="item-total-label">Item Total:</span>' +
            '<span class="item-total-value">' + formatMoney(totals.amount) + '</span>' +
            '<span class="item-total-value">' + formatMoney(totals.tax) + '</span>' +
            '<span class="item-total-value">' + formatMoney(totals.shipping) + '</span>' +
            '<span class="item-total-value">' + formatMoney(totals.total) + '</span>'
          card.appendChild(itemTotalsBox)

          itemsWrap.appendChild(card)
        }

        container.appendChild(itemsWrap)

        var orderBox = document.createElement('div')
        orderBox.className = 'order-total-box'
        orderBox.innerHTML =
          '<span class="order-total-label">Order Total:</span>' +
          '<span class="order-total-value">' + formatMoney(order.amount || 0) + '</span>' +
          '<span class="order-total-value">' + formatMoney(order.tax || 0) + '</span>' +
          '<span class="order-total-value">' + formatMoney(order.shipping || 0) + '</span>' +
          '<span class="order-total-value">' + formatMoney(order.total || 0) + '</span>'
        container.appendChild(orderBox)
      }

      function toggleOrder(row) {
        console.log('toggleOrder called')
        var orderId = row.getAttribute('data-order-id') || ''
        console.log('Order ID:', orderId)
        if (!orderId) {
          console.log('No orderId, returning')
          return
        }
        var detailsRow = document.querySelector('[data-order-details-row][data-order-id="' + orderId + '"]')
        var toggle = row.querySelector('[data-order-toggle]')
        console.log('Found detailsRow:', !!detailsRow, 'toggle:', !!toggle)
        if (!detailsRow || !toggle) {
          console.log('Missing detailsRow or toggle, returning')
          return
        }
        var isHidden = detailsRow.style.display === 'none' || detailsRow.hasAttribute('hidden')
        console.log('isHidden:', isHidden, 'display:', detailsRow.style.display, 'hasHidden:', detailsRow.hasAttribute('hidden'))
        if (!isHidden) {
          console.log('Closing order details')
          detailsRow.style.display = 'none'
          detailsRow.setAttribute('hidden', '')
          toggle.setAttribute('aria-expanded', 'false')
          toggle.setAttribute('aria-label', 'Expand order details')
          toggle.textContent = '+'
          return
        }
        console.log('Opening order details')
        detailsRow.style.display = 'table-row'
        detailsRow.removeAttribute('hidden')
        toggle.setAttribute('aria-expanded', 'true')
        toggle.setAttribute('aria-label', 'Collapse order details')
        toggle.textContent = '−'

        var container = detailsRow.querySelector('[data-order-details-box]')
        if (!container) {
          console.log('No container found')
          return
        }
        console.log('Container found, checking cache')
        if (orderCache[orderId]) {
          console.log('Using cached data')
          orderCache[orderId].container = container
          renderOrderDetails(orderId)
          return
        }
        console.log('Fetching order details from API')
        container.innerHTML = '<div class="order-details-loading">Loading order...</div>'
        fetch('/api/order_details.php?orderId=' + encodeURIComponent(orderId))
          .then(parseJsonResponse)
          .then(function (data) {
            console.log('Order data received:', data)
            normalizeOrderData(orderId, data, container)
            renderOrderDetails(orderId)
          })
          .catch(function (err) {
            console.error('Error loading order details:', err)
            container.innerHTML = '<div class="order-details-loading">Unable to load order details.</div>'
          })
      }

      console.log('Setting up order toggle handlers, found', orderRows.length, 'order rows')
      for (var i = 0; i < orderRows.length; i += 1) {
        (function (row) {
          var toggle = row.querySelector('[data-order-toggle]')
          if (!toggle) {
            console.log('No toggle found for row')
            return
          }
          var orderId = row.getAttribute('data-order-id') || ''
          console.log('Setting up toggle for order:', orderId)
          var detailsRow = orderId
            ? document.querySelector('[data-order-details-row][data-order-id="' + orderId + '"]')
            : null
          if (detailsRow) {
            detailsRow.style.display = 'none'
            detailsRow.setAttribute('hidden', '')
          }
          toggle.addEventListener('click', function (event) {
            console.log('Toggle clicked for order:', orderId)
            event.preventDefault()
            toggleOrder(row)
          })
          console.log('Event listener attached for order:', orderId)
        })(orderRows[i])
      }
      console.log('Finished setting up order toggle handlers')

      // ---- Filter Logic ----
      var filterTime = document.getElementById('filter-time')
      var filterCustomDates = document.getElementById('filter-custom-dates')
      var filterDateFrom = document.getElementById('filter-date-from')
      var filterDateTo = document.getElementById('filter-date-to')
      var filterMultis = document.querySelectorAll('.filter-multi')

      var defaultLabels = {}
      filterMultis.forEach(function (fm) {
        var key = fm.getAttribute('data-filter')
        var btn = fm.querySelector('.filter-multi-btn')
        if (key && btn) {
          defaultLabels[key] = btn.textContent
        }
      })

      function getCheckedValues(filterKey) {
        var fm = document.querySelector('.filter-multi[data-filter="' + filterKey + '"]')
        if (!fm) return []
        var checked = fm.querySelectorAll('input[type="checkbox"]:checked')
        var values = []
        checked.forEach(function (cb) { values.push(cb.value) })
        return values
      }

      function updateMultiButtonText(fm) {
        var key = fm.getAttribute('data-filter')
        var btn = fm.querySelector('.filter-multi-btn')
        if (!btn || !key) return
        var checked = fm.querySelectorAll('input[type="checkbox"]:checked')
        if (checked.length === 0) {
          btn.textContent = defaultLabels[key] || 'All'
        } else if (checked.length <= 2) {
          var names = []
          checked.forEach(function (cb) { names.push(cb.value) })
          btn.textContent = names.join(', ')
        } else {
          btn.textContent = checked.length + ' selected'
        }
      }

      function applyFilters() {
        var timeValue = filterTime ? filterTime.value : 'all'
        var now = new Date()
        var dateFrom = null
        var dateTo = null

        if (timeValue === '30') {
          dateFrom = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 30)
        } else if (timeValue === '90') {
          dateFrom = new Date(now.getFullYear(), now.getMonth(), now.getDate() - 90)
        } else if (timeValue === 'year') {
          dateFrom = new Date(now.getFullYear(), 0, 1)
          dateTo = new Date(now.getFullYear(), 11, 31)
        } else if (timeValue === 'lastyear') {
          dateFrom = new Date(now.getFullYear() - 1, 0, 1)
          dateTo = new Date(now.getFullYear() - 1, 11, 31)
        } else if (timeValue === 'custom') {
          var fromVal = filterDateFrom ? filterDateFrom.value : ''
          var toVal = filterDateTo ? filterDateTo.value : ''
          var dateErrEl = document.getElementById('date-filter-error')
          if (fromVal && toVal && fromVal > toVal) {
            if (dateErrEl) {
              dateErrEl.textContent = 'Start date must be before end date.'
              dateErrEl.style.display = ''
            }
            return
          }
          if (dateErrEl) {
            dateErrEl.style.display = 'none'
          }
          if (fromVal) {
            dateFrom = new Date(fromVal + 'T00:00:00')
          }
          if (toVal) {
            dateTo = new Date(toVal + 'T23:59:59')
          }
        }

        var vendorValues = getCheckedValues('vendor')
        var clientValues = getCheckedValues('client')
        var locationValues = getCheckedValues('location')
        var code1Values = getCheckedValues('code1')
        var code2Values = getCheckedValues('code2')

        orderRows.forEach(function (row) {
          var orderId = row.getAttribute('data-order-id') || ''
          var rowDate = row.getAttribute('data-date') || ''
          var rowVendor = row.getAttribute('data-vendor') || ''
          var rowClient = row.getAttribute('data-client') || ''
          var rowLocations = row.getAttribute('data-locations') || ''
          var rowCode1s = row.getAttribute('data-code1s') || ''
          var rowCode2s = row.getAttribute('data-code2s') || ''

          var show = true

          // Time filter
          if (dateFrom || dateTo) {
            if (rowDate) {
              var d = new Date(rowDate + 'T00:00:00')
              if (dateFrom && d < dateFrom) show = false
              if (dateTo && d > dateTo) show = false
            } else {
              show = false
            }
          }

          // Vendor filter (OR within multi-select)
          if (show && vendorValues.length > 0) {
            if (vendorValues.indexOf(rowVendor) === -1) show = false
          }

          // Client filter
          if (show && clientValues.length > 0) {
            if (clientValues.indexOf(rowClient) === -1) show = false
          }

          // Location filter (OR: order matches if any of its locations match any checked location)
          if (show && locationValues.length > 0) {
            var orderLocs = rowLocations ? rowLocations.split('|') : []
            var locMatch = false
            for (var li = 0; li < locationValues.length; li++) {
              for (var oi = 0; oi < orderLocs.length; oi++) {
                if (orderLocs[oi] === locationValues[li] || orderLocs[oi].indexOf(locationValues[li]) === 0) {
                  locMatch = true
                  break
                }
              }
              if (locMatch) break
            }
            if (!locMatch) show = false
          }

          // Code 1 filter
          if (show && code1Values.length > 0) {
            var orderC1s = rowCode1s ? rowCode1s.split('|') : []
            var c1Match = false
            for (var c1i = 0; c1i < code1Values.length; c1i++) {
              for (var o1i = 0; o1i < orderC1s.length; o1i++) {
                if (orderC1s[o1i] === code1Values[c1i] || orderC1s[o1i].indexOf(code1Values[c1i]) === 0) {
                  c1Match = true
                  break
                }
              }
              if (c1Match) break
            }
            if (!c1Match) show = false
          }

          // Code 2 filter
          if (show && code2Values.length > 0) {
            var orderC2s = rowCode2s ? rowCode2s.split('|') : []
            var c2Match = false
            for (var c2i = 0; c2i < code2Values.length; c2i++) {
              for (var o2i = 0; o2i < orderC2s.length; o2i++) {
                if (orderC2s[o2i] === code2Values[c2i] || orderC2s[o2i].indexOf(code2Values[c2i]) === 0) {
                  c2Match = true
                  break
                }
              }
              if (c2Match) break
            }
            if (!c2Match) show = false
          }

          // Show/hide the order row and its details row
          row.style.display = show ? '' : 'none'
          var detailsRow = document.querySelector('[data-order-details-row][data-order-id="' + orderId + '"]')
          if (detailsRow) {
            if (!show) {
              detailsRow.style.display = 'none'
              detailsRow.setAttribute('hidden', '')
              var toggle = row.querySelector('[data-order-toggle]')
              if (toggle) {
                toggle.setAttribute('aria-expanded', 'false')
                toggle.setAttribute('aria-label', 'Expand order details')
                toggle.textContent = '+'
              }
            }
          }
        })
      }

      // Wire up time filter
      if (filterTime) {
        filterTime.addEventListener('change', function () {
          if (filterCustomDates) {
            filterCustomDates.style.display = filterTime.value === 'custom' ? 'inline-flex' : 'none'
          }
          applyFilters()
        })
      }
      if (filterDateFrom) {
        filterDateFrom.addEventListener('change', applyFilters)
      }
      if (filterDateTo) {
        filterDateTo.addEventListener('change', applyFilters)
      }

      // Wire up multi-select dropdowns
      filterMultis.forEach(function (fm) {
        var btn = fm.querySelector('.filter-multi-btn')
        var dropdown = fm.querySelector('.filter-multi-dropdown')
        if (!btn || !dropdown) return

        btn.addEventListener('click', function (e) {
          e.stopPropagation()
          var isOpen = !dropdown.hasAttribute('hidden')
          // Close all dropdowns first
          filterMultis.forEach(function (other) {
            var otherDd = other.querySelector('.filter-multi-dropdown')
            if (otherDd) otherDd.setAttribute('hidden', '')
          })
          if (!isOpen) {
            dropdown.removeAttribute('hidden')
          }
        })

        var checkboxes = dropdown.querySelectorAll('input[type="checkbox"]')
        checkboxes.forEach(function (cb) {
          cb.addEventListener('change', function () {
            updateMultiButtonText(fm)
            applyFilters()
          })
        })
      })

      // Close dropdowns when clicking outside
      document.addEventListener('click', function (e) {
        filterMultis.forEach(function (fm) {
          if (!fm.contains(e.target)) {
            var dd = fm.querySelector('.filter-multi-dropdown')
            if (dd) dd.setAttribute('hidden', '')
          }
        })
      })
    })()
  </script>
</body>
</html>
