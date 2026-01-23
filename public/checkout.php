<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

function cart_accounting_mismatch(array $items, array $payload): bool
{
    $assignments = $payload['assignments'] ?? [];
    if (!is_array($assignments)) {
        return false;
    }
    foreach ($items as $item) {
        $key = (string) ($item['key'] ?? '');
        $itemQty = (int) ($item['quantity'] ?? 0);
        $itemAssignments = $assignments[$key] ?? [];
        if (!is_array($itemAssignments) || count($itemAssignments) < 2) {
            continue;
        }
        $sum = 0;
        foreach ($itemAssignments as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $sum += (int) ($entry['qty'] ?? 0);
        }
        if ($sum !== $itemQty) {
            return true;
        }
    }
    return false;
}

$user = site_require_auth();
$items = site_cart_items();
$total = site_cart_total($items);
$clientId = $_GET['clientId'] ?? '';
$clientId = is_string($clientId) && $clientId !== '' ? $clientId : null;
$cartAccounting = site_get_cart_accounting_for_user($user['id'], $clientId);
$accountingPayload = $cartAccounting ? [
    'clientId' => $clientId,
    'groups' => $cartAccounting['groups'] ?? [],
    'assignments' => $cartAccounting['assignments'] ?? [],
] : null;
$accountingPayloadJson = $accountingPayload ? json_encode($accountingPayload) : '';
$message = '';
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    if (!empty($_POST['accounting_payload'])) {
        $decoded = json_decode($_POST['accounting_payload'], true);
        if (is_array($decoded)) {
            $accountingPayload = $decoded;
            $accountingPayloadJson = $_POST['accounting_payload'];
        }
    }
    if ($accountingPayload && cart_accounting_mismatch($items, $accountingPayload)) {
        $message = 'Group quantities must match item quantities before checkout.';
    } else {
        $result = site_place_order([
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address1' => $_POST['address1'] ?? '',
        'address2' => $_POST['address2'] ?? '',
        'city' => $_POST['city'] ?? '',
        'state' => $_POST['state'] ?? '',
        'postal' => $_POST['postal'] ?? '',
        'country' => $_POST['country'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'accounting' => $accountingPayload,
    ]);
        if (!empty($result['error'])) {
            $message = $result['error'];
        } else {
            $success = $result;
        }
    }
}

$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Checkout - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel">
      <div class="section-title">
        <div>
          <h2>Checkout</h2>
          <p class="meta">Confirm shipment details. Payment will be arranged offline.</p>
        </div>
        <div class="price">$<?php echo number_format($total, 2); ?></div>
      </div>

      <?php if ($message): ?>
        <div class="notice"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="notice">Order placed. Confirmation: <?php echo htmlspecialchars($success['orderNumber'], ENT_QUOTES); ?></div>
        <a class="btn" href="/dashboard-orders.php">View orders</a>
      <?php elseif (!$items): ?>
        <div class="notice">Your cart is empty.</div>
      <?php else: ?>
        <form method="POST" class="form-grid cols-2">
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
          <input type="hidden" name="accounting_payload" value="<?php echo htmlspecialchars($accountingPayloadJson, ENT_QUOTES); ?>" />
          <?php if ($accountingPayload && !empty($accountingPayload['groups'])): ?>
            <div style="grid-column: 1 / -1;">
              <div class="notice">Accounting groups will be attached to this order.</div>
            </div>
          <?php endif; ?>
          <div>
            <label for="name">Full name</label>
            <input id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? '', ENT_QUOTES); ?>" required />
          </div>
          <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES); ?>" required />
          </div>
          <div>
            <label for="phone">Phone</label>
            <input id="phone" name="phone" />
          </div>
          <div>
            <label for="country">Country</label>
            <input id="country" name="country" value="USA" />
          </div>
          <div>
            <label for="address1">Address line 1</label>
            <input id="address1" name="address1" required />
          </div>
          <div>
            <label for="address2">Address line 2</label>
            <input id="address2" name="address2" />
          </div>
          <div>
            <label for="city">City</label>
            <input id="city" name="city" required />
          </div>
          <div>
            <label for="state">State</label>
            <input id="state" name="state" required />
          </div>
          <div>
            <label for="postal">Postal code</label>
            <input id="postal" name="postal" required />
          </div>
          <div>
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes"></textarea>
          </div>
          <div style="grid-column: 1 / -1;">
            <button class="btn" type="submit">Place order</button>
          </div>
        </form>
      <?php endif; ?>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
