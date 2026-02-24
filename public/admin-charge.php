<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db_conn.php';
require_once __DIR__ . '/../src/stripe_service.php';
require_once __DIR__ . '/../src/api_helpers.php';

$user = opd_require_role(['admin', 'manager']);
$pdo = opd_db();
$csrf = opd_csrf_token();

$message = '';
$messageClass = 'form-message';
$email = trim((string) ($_GET['email'] ?? ($_POST['email'] ?? '')));
$targetUser = null;
$paymentMethods = [];

if ($email !== '') {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $targetUser = $stmt->fetch();
    if ($targetUser) {
        $pmStmt = $pdo->prepare(
            'SELECT * FROM payment_methods WHERE userId = ? AND stripePaymentMethodId IS NOT NULL ORDER BY updatedAt DESC'
        );
        $pmStmt->execute([$targetUser['id']]);
        $paymentMethods = $pmStmt->fetchAll();
    } else {
        $message = 'No user found for that email.';
        $messageClass = 'form-message is-error';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    opd_require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'charge') {
        $userId = trim((string) ($_POST['user_id'] ?? ''));
        $paymentMethodId = trim((string) ($_POST['payment_method_id'] ?? ''));
        $amountRaw = trim((string) ($_POST['amount'] ?? ''));
        $currency = strtolower(trim((string) ($_POST['currency'] ?? 'usd')));
        $orderId = trim((string) ($_POST['order_id'] ?? ''));
        $chargeReference = trim((string) ($_POST['charge_reference'] ?? ''));

        if ($userId === '' || $paymentMethodId === '') {
            $message = 'Select a user and payment method.';
            $messageClass = 'form-message is-error';
        } elseif ($chargeReference === '') {
            $message = 'Charge reference is required for idempotency.';
            $messageClass = 'form-message is-error';
        } elseif (!is_numeric($amountRaw) || (float) $amountRaw <= 0) {
            $message = 'Enter a valid amount.';
            $messageClass = 'form-message is-error';
        } elseif (!preg_match('/^[a-z]{3}$/', $currency)) {
            $message = 'Currency must be a 3-letter code.';
            $messageClass = 'form-message is-error';
        } else {
            $pmStmt = $pdo->prepare(
                'SELECT * FROM payment_methods WHERE id = ? AND userId = ? AND stripePaymentMethodId IS NOT NULL LIMIT 1'
            );
            $pmStmt->execute([$paymentMethodId, $userId]);
            $pm = $pmStmt->fetch();
            if (!$pm) {
                $message = 'Payment method not found.';
                $messageClass = 'form-message is-error';
            } else {
                $userStmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
                $userStmt->execute([$userId]);
                $chargeUser = $userStmt->fetch();
                if (!$chargeUser) {
                    $message = 'User not found.';
                    $messageClass = 'form-message is-error';
                } else {
                    $customerId = stripe_get_or_create_customer($chargeUser);
                    if (!$customerId) {
                        $message = 'Stripe customer missing or unavailable.';
                        $messageClass = 'form-message is-error';
                    } else {
                        $amountCents = (int) round(((float) $amountRaw) * 100);
                        $idempotencyKey = preg_replace('/[^A-Za-z0-9_-]/', '-', $chargeReference);
                        $idempotencyKey = 'manual-charge-' . $idempotencyKey;
                        $metadata = [
                            'orderId' => $orderId,
                            'userId' => $userId,
                            'localPaymentMethodId' => $paymentMethodId,
                            'chargeReference' => $chargeReference,
                            'initiatedBy' => $user['email'] ?? '',
                        ];
                        $charge = stripe_create_payment_intent(
                            $customerId,
                            (string) ($pm['stripePaymentMethodId'] ?? ''),
                            $amountCents,
                            $currency,
                            $metadata,
                            $idempotencyKey
                        );

                        if (!$charge['ok']) {
                            $message = $charge['error'] ?? 'Stripe charge failed.';
                            $messageClass = 'form-message is-error';
                        } else {
                            $intent = $charge['data'] ?? [];
                            $status = (string) ($intent['status'] ?? 'unknown');
                            $externalId = (string) ($intent['id'] ?? '');
                            $now = gmdate('Y-m-d H:i:s');
                            try {
                                $insert = $pdo->prepare(
                                    'INSERT INTO payments (id, orderId, method, externalId, amount, status, capturedAt, updatedAt)
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                                );
                                $insert->execute([
                                    opd_generate_id('pay'),
                                    $orderId !== '' ? $orderId : null,
                                    'stripe',
                                    $externalId !== '' ? $externalId : null,
                                    $amountCents / 100,
                                    $status,
                                    $now,
                                    $now,
                                ]);
                            } catch (Throwable $e) {
                                stripe_log('Manual charge: failed to insert payment', ['error' => $e->getMessage()]);
                            }

                            if ($orderId !== '') {
                                try {
                                    $update = $pdo->prepare('UPDATE orders SET paymentStatus = ?, updatedAt = ? WHERE id = ?');
                                    $update->execute([$status === 'succeeded' ? 'paid' : $status, $now, $orderId]);
                                } catch (Throwable $e) {
                                    stripe_log('Manual charge: failed to update order', ['error' => $e->getMessage()]);
                                }
                            }

                            $message = $status === 'succeeded'
                                ? 'Charge succeeded.'
                                : 'Charge created with status: ' . $status;
                            $messageClass = 'form-message is-success';
                        }
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Manual Stripe Charge - OPD Admin</title>
  <link rel="stylesheet" href="/assets/css/admin.css" />
</head>
<body>
  <header class="top-bar">
    <div class="brand">
      <span class="brand-badge">OPD</span>
      <div>
        <div class="brand-title">Admin Command</div>
        <div class="brand-sub">Manual Stripe Charge</div>
      </div>
    </div>
    <div class="top-actions">
      <span class="user-pill"><?php echo htmlspecialchars($user['name'] ?? 'User', ENT_QUOTES); ?></span>
      <a class="ghost-btn" href="/admin.php">Back to Admin</a>
      <a class="ghost-btn" href="/admin-logout.php">Logout</a>
    </div>
  </header>

  <main class="layout">
    <section class="panel" style="grid-column: 1 / -1;">
      <div class="panel-header">
        <div>
          <div class="eyebrow">Stripe</div>
          <h2>Manual Card Recharge</h2>
          <p>Charge a saved card without asking the customer to re-enter details.</p>
        </div>
      </div>
      <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES); ?>">
        <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
      </div>

      <form class="form" method="GET">
        <div class="field">
          <label for="email">Customer email</label>
          <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>" required />
        </div>
        <div class="form-actions">
          <button class="primary-btn" type="submit">Find customer</button>
        </div>
      </form>

      <?php if ($targetUser && $paymentMethods): ?>
        <form class="form" method="POST" action="/admin-charge.php?email=<?php echo urlencode($email); ?>">
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
          <input type="hidden" name="action" value="charge" />
          <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($targetUser['id'], ENT_QUOTES); ?>" />
          <input type="hidden" name="email" value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>" />
          <div class="field">
            <label for="payment_method_id">Saved card</label>
            <select id="payment_method_id" name="payment_method_id" required>
              <?php foreach ($paymentMethods as $method): ?>
                <?php
                  $label = trim((string) ($method['label'] ?? ''));
                  if ($label === '') {
                      $parts = [];
                      if (!empty($method['brand'])) {
                          $parts[] = $method['brand'];
                      }
                      if (!empty($method['last4'])) {
                          $parts[] = 'ending ' . $method['last4'];
                      }
                      $label = $parts ? implode(' ', $parts) : 'Saved card';
                  }
                ?>
                <option value="<?php echo htmlspecialchars($method['id'], ENT_QUOTES); ?>">
                  <?php echo htmlspecialchars($label, ENT_QUOTES); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="field">
            <label for="amount">Amount (USD)</label>
            <input id="amount" name="amount" type="number" step="0.01" min="0.50" required />
          </div>
          <div class="field">
            <label for="currency">Currency</label>
            <input id="currency" name="currency" value="usd" />
          </div>
          <div class="field">
            <label for="order_id">Order ID (optional)</label>
            <input id="order_id" name="order_id" />
          </div>
          <div class="field">
            <label for="charge_reference">Charge reference (required)</label>
            <input id="charge_reference" name="charge_reference" placeholder="invoice-1234" required />
          </div>
          <div class="form-actions">
            <button class="primary-btn" type="submit">Run charge</button>
          </div>
        </form>
      <?php elseif ($targetUser): ?>
        <div class="form-message is-error">No Stripe cards saved for this customer.</div>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
