<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_require_auth();
$paymentMethods = site_get_payment_methods($user['id']);
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'invite') {
        $limitNone = !empty($_POST['limit_none']);
        $limitOrderRaw = trim((string) ($_POST['limit_order'] ?? ''));
        $limitDayRaw = trim((string) ($_POST['limit_day'] ?? ''));
        $limitMonthRaw = trim((string) ($_POST['limit_month'] ?? ''));
        $limitOrder = $limitNone || $limitOrderRaw === '' ? null : (is_numeric($limitOrderRaw) ? (float) $limitOrderRaw : null);
        $limitDay = $limitNone || $limitDayRaw === '' ? null : (is_numeric($limitDayRaw) ? (float) $limitDayRaw : null);
        $limitMonth = $limitNone || $limitMonthRaw === '' ? null : (is_numeric($limitMonthRaw) ? (float) $limitMonthRaw : null);
        $smsConsent = !empty($_POST['sms_consent']);
        $paymentMethodId = trim((string) ($_POST['payment_method_id'] ?? ''));
        $validPaymentMethods = array_column($paymentMethods, 'id');
        if ($paymentMethodId === '' || !in_array($paymentMethodId, $validPaymentMethods, true)) {
            $paymentMethodId = null;
        }

        if ($action === 'invite' && !$smsConsent) {
            $message = 'SMS consent is required to send an invite.';
        } else {
            site_simple_create('vendors', $user['id'], [
                'name' => $_POST['name'] ?? '',
                'contact' => $_POST['contact'] ?? '',
                'email' => $_POST['email'] ?? '',
                'phone' => $_POST['phone'] ?? '',
                'status' => $_POST['status'] ?? 'active',
                'purchaseLimitOrder' => $limitOrder,
                'purchaseLimitDay' => $limitDay,
                'purchaseLimitMonth' => $limitMonth,
                'limitNone' => $limitNone ? 1 : 0,
                'paymentMethodId' => $paymentMethodId,
                'smsConsent' => $smsConsent ? 1 : 0
            ]);
            $message = $action === 'invite'
                ? 'Vendor added. Invite queued (SMS integration pending).'
                : 'Vendor added.';
        }
    }
    if ($action === 'delete') {
        site_simple_delete('vendors', $_POST['id'] ?? '');
        $message = 'Vendor removed.';
    }
}

$vendors = site_simple_list('vendors', $user['id']);
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Vendors - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel">
          <h2>Vendors</h2>
          <?php if ($message): ?>
            <div class="notice"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
          <?php endif; ?>
          <form method="POST" class="form-grid cols-2">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <div>
              <label for="name">Vendor name</label>
              <input id="name" name="name" required />
            </div>
            <div>
              <label for="contact">Contact</label>
              <input id="contact" name="contact" />
            </div>
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" />
            </div>
            <div>
              <label for="phone">Phone</label>
              <input id="phone" name="phone" />
            </div>
            <div>
              <label for="status">Status</label>
              <input id="status" name="status" value="active" />
            </div>
            <div>
              <label for="limit_order">Purchase limit per order</label>
              <input id="limit_order" name="limit_order" type="number" step="0.01" min="0" data-limit="1" />
            </div>
            <div>
              <label for="limit_day">Purchase limit per day</label>
              <input id="limit_day" name="limit_day" type="number" step="0.01" min="0" data-limit="1" />
            </div>
            <div>
              <label for="limit_month">Purchase limit per month</label>
              <input id="limit_month" name="limit_month" type="number" step="0.01" min="0" data-limit="1" />
            </div>
            <label class="checkbox-row span-2" for="limit_none">
              <input id="limit_none" name="limit_none" type="checkbox" value="1" />
              No purchase limit
            </label>
            <div class="span-2">
              <label for="payment_method_id">Payment method</label>
              <?php if ($paymentMethods): ?>
                <select id="payment_method_id" name="payment_method_id">
                  <option value="">Select a payment method</option>
                  <?php foreach ($paymentMethods as $method): ?>
                    <?php
                      $label = trim((string) ($method['label'] ?? ''));
                      if ($label === '') {
                          $parts = [];
                          if (!empty($method['type'])) {
                              $parts[] = $method['type'];
                          }
                          if (!empty($method['last4'])) {
                              $parts[] = 'ending ' . $method['last4'];
                          }
                          if (!empty($method['expMonth']) && !empty($method['expYear'])) {
                              $parts[] = 'exp ' . sprintf('%02d/%d', (int) $method['expMonth'], (int) $method['expYear']);
                          }
                          $label = $parts ? implode(' - ', $parts) : 'Saved payment method';
                      }
                    ?>
                    <option value="<?php echo htmlspecialchars($method['id'] ?? '', ENT_QUOTES); ?>">
                      <?php echo htmlspecialchars($label, ENT_QUOTES); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php else: ?>
                <div class="text-danger">No payment methods found</div>
              <?php endif; ?>
            </div>
            <label class="checkbox-row span-2" for="sms_consent">
              <input id="sms_consent" name="sms_consent" type="checkbox" value="1" />
              I confirm I have obtained my vendor's consent to receive SMS messages from OilPatchDepot.
            </label>
            <div class="span-2 form-actions">
              <button class="btn" type="submit" name="action" value="create">Add vendor</button>
              <button class="btn-outline" type="submit" name="action" value="invite">Send Invite</button>
            </div>
          </form>
        </section>

        <section class="panel">
          <h2>Vendor list</h2>
          <?php if (!$vendors): ?>
            <div class="notice">No vendors saved.</div>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Contact</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($vendors as $vendor): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($vendor['name'] ?? '', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($vendor['contact'] ?? '', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($vendor['status'] ?? '', ENT_QUOTES); ?></td>
                    <td>
                      <form method="POST">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="action" value="delete" />
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>" />
                        <button class="btn-outline" type="submit">Remove</button>
                      </form>
                    </td>
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
  <script>
    const limitNone = document.getElementById('limit_none');
    const limitFields = Array.from(document.querySelectorAll('[data-limit]'));
    const toggleLimits = () => {
      const disable = limitNone && limitNone.checked;
      limitFields.forEach((field) => {
        field.disabled = disable;
        if (disable) {
          field.value = '';
        }
      });
    };
    if (limitNone) {
      limitNone.addEventListener('change', toggleLimits);
      toggleLimits();
    }
  </script>
</body>
</html>
