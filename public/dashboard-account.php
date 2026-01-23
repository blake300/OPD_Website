<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/store.php';

$user = site_require_auth();
$pdo = opd_db();
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$userRecord = $stmt->fetch() ?: $user;
$message = '';
$messageClass = 'notice';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $action = $_POST['action'] ?? 'profile';
    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $cellPhone = trim($_POST['cell_phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip = trim($_POST['zip'] ?? '');
        if ($name === '' || $email === '') {
            $message = 'Name and email are required.';
            $messageClass = 'notice is-error';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET name = ?, email = ?, companyName = ?, cellPhone = ?, address = ?, city = ?, state = ?, zip = ?, updatedAt = ? WHERE id = ?'
            );
            $stmt->execute([
                $name,
                $email,
                $companyName !== '' ? $companyName : null,
                $cellPhone !== '' ? $cellPhone : null,
                $address !== '' ? $address : null,
                $city !== '' ? $city : null,
                $state !== '' ? $state : null,
                $zip !== '' ? $zip : null,
                gmdate('Y-m-d H:i:s'),
                $user['id']
            ]);
            $_SESSION['site_user']['name'] = $name;
            $_SESSION['site_user']['email'] = $email;
            $message = 'Account updated.';
        }
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if ($newPassword === '' || $confirm === '') {
            $message = 'New password and confirmation are required.';
            $messageClass = 'notice is-error';
        } elseif ($newPassword !== $confirm) {
            $message = 'New password and confirmation do not match.';
            $messageClass = 'notice is-error';
        } elseif (empty($userRecord['passwordHash']) || !password_verify($current, (string) $userRecord['passwordHash'])) {
            $message = 'Current password is incorrect.';
            $messageClass = 'notice is-error';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET passwordHash = ?, updatedAt = ? WHERE id = ?');
            $stmt->execute([$hash, gmdate('Y-m-d H:i:s'), $user['id']]);
            $message = 'Password updated.';
        }
    } elseif ($action === 'payment_method') {
        $label = trim($_POST['pm_label'] ?? '');
        $type = trim($_POST['pm_type'] ?? '');
        $last4 = trim($_POST['pm_last4'] ?? '');
        $expMonthRaw = trim($_POST['pm_exp_month'] ?? '');
        $expYearRaw = trim($_POST['pm_exp_year'] ?? '');
        $expMonth = $expMonthRaw === '' ? null : (int) $expMonthRaw;
        $expYear = $expYearRaw === '' ? null : (int) $expYearRaw;

        if ($label === '') {
            $message = 'Payment method label is required.';
            $messageClass = 'notice is-error';
        } elseif ($last4 !== '' && !preg_match('/^\d{4}$/', $last4)) {
            $message = 'Last 4 must be exactly four digits.';
            $messageClass = 'notice is-error';
        } elseif ($expMonth !== null && ($expMonth < 1 || $expMonth > 12)) {
            $message = 'Expiration month must be between 1 and 12.';
            $messageClass = 'notice is-error';
        } else {
            $now = gmdate('Y-m-d H:i:s');
            $stmt = $pdo->prepare(
                'INSERT INTO payment_methods (id, userId, label, type, last4, expMonth, expYear, createdAt, updatedAt)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                'pm-' . random_int(1000, 99999),
                $user['id'],
                $label,
                $type !== '' ? $type : null,
                $last4 !== '' ? $last4 : null,
                $expMonth,
                $expYear,
                $now,
                $now
            ]);
            $message = 'Payment method added.';
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$userRecord = $stmt->fetch() ?: $user;
$paymentMethods = site_get_payment_methods($user['id']);
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Account - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <?php if ($message): ?>
          <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES); ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
          </div>
        <?php endif; ?>
        <section class="panel" style="max-width:840px;">
          <h2>Account profile</h2>
          <p class="meta">Keep your purchasing profile up to date.</p>
          <form method="POST" class="form-grid cols-2">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <input type="hidden" name="action" value="profile" />
            <div>
              <label for="name">Name</label>
              <input id="name" name="name" value="<?php echo htmlspecialchars($userRecord['name'] ?? '', ENT_QUOTES); ?>" required />
            </div>
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($userRecord['email'] ?? '', ENT_QUOTES); ?>" required />
            </div>
            <div>
              <label for="company_name">Company name</label>
              <input id="company_name" name="company_name" value="<?php echo htmlspecialchars($userRecord['companyName'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="cell_phone">Cell phone</label>
              <input id="cell_phone" name="cell_phone" type="tel" value="<?php echo htmlspecialchars($userRecord['cellPhone'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div class="span-2">
              <label for="address">Address</label>
              <input id="address" name="address" autocomplete="street-address" value="<?php echo htmlspecialchars($userRecord['address'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div class="span-2">
              <label for="city">City</label>
              <input id="city" name="city" autocomplete="address-level2" value="<?php echo htmlspecialchars($userRecord['city'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="state">State</label>
              <input id="state" name="state" autocomplete="address-level1" value="<?php echo htmlspecialchars($userRecord['state'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="zip">Zip</label>
              <input id="zip" name="zip" autocomplete="postal-code" value="<?php echo htmlspecialchars($userRecord['zip'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div class="span-2">
              <button class="btn" type="submit">Save changes</button>
            </div>
          </form>
        </section>

        <section class="panel" style="max-width:640px;">
          <h2>Change password</h2>
          <form method="POST" class="form-grid">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <input type="hidden" name="action" value="password" />
            <div>
              <label for="current_password">Current password</label>
              <input id="current_password" name="current_password" type="password" autocomplete="current-password" />
            </div>
            <div>
              <label for="new_password">New password</label>
              <input id="new_password" name="new_password" type="password" autocomplete="new-password" />
            </div>
            <div>
              <label for="confirm_password">Confirm new password</label>
              <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password" />
            </div>
            <button class="btn" type="submit">Update password</button>
          </form>
        </section>

        <section class="panel">
          <h2>Payment methods</h2>
          <p class="meta">Save a payment method for faster checkout and vendor setup.</p>
          <?php if (!$paymentMethods): ?>
            <div class="notice">No payment methods saved.</div>
          <?php else: ?>
            <table class="table">
              <thead>
                <tr>
                  <th>Label</th>
                  <th>Type</th>
                  <th>Last 4</th>
                  <th>Expires</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($paymentMethods as $method): ?>
                  <tr>
                    <td><?php echo htmlspecialchars($method['label'] ?? '', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($method['type'] ?? '', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($method['last4'] ?? '', ENT_QUOTES); ?></td>
                    <td>
                      <?php
                        $expMonth = $method['expMonth'] ?? null;
                        $expYear = $method['expYear'] ?? null;
                        if ($expMonth && $expYear) {
                            echo htmlspecialchars(sprintf('%02d/%d', (int) $expMonth, (int) $expYear), ENT_QUOTES);
                        }
                      ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>

          <form method="POST" class="form-grid cols-2" style="margin-top:16px;">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <input type="hidden" name="action" value="payment_method" />
            <div class="span-2">
              <label for="pm_label">Payment method label</label>
              <input id="pm_label" name="pm_label" placeholder="Company Visa, ACH - Main" required />
            </div>
            <div>
              <label for="pm_type">Type</label>
              <input id="pm_type" name="pm_type" placeholder="Card, ACH, Wire" />
            </div>
            <div>
              <label for="pm_last4">Last 4</label>
              <input id="pm_last4" name="pm_last4" inputmode="numeric" maxlength="4" />
            </div>
            <div>
              <label for="pm_exp_month">Exp month</label>
              <input id="pm_exp_month" name="pm_exp_month" type="number" min="1" max="12" placeholder="MM" />
            </div>
            <div>
              <label for="pm_exp_year">Exp year</label>
              <input id="pm_exp_year" name="pm_exp_year" type="number" min="2024" max="2100" placeholder="YYYY" />
            </div>
            <div class="span-2">
              <button class="btn" type="submit">Add payment method</button>
            </div>
          </form>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
