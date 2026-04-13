<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/db_conn.php';
require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/stripe_service.php';
require_once __DIR__ . '/../src/email_service.php';

$user = site_require_auth();
opd_ensure_cc_email_column();
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
        $ccEmailRaw = trim($_POST['cc_email'] ?? '');
        $ccEmail = null;
        $ccEmailError = '';
        if ($ccEmailRaw !== '') {
            if (!filter_var($ccEmailRaw, FILTER_VALIDATE_EMAIL)) {
                $ccEmailError = 'CC Email must be a valid email address.';
            } else {
                $ccEmail = $ccEmailRaw;
            }
        }
        $companyName = trim($_POST['company_name'] ?? '');
        $cellPhoneRaw = trim($_POST['cell_phone'] ?? '');
        $cellPhone = $cellPhoneRaw !== '' ? opd_normalize_us_phone($cellPhoneRaw) : null;
        $address = trim($_POST['address'] ?? '');
        $address2 = trim($_POST['address2'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $state = trim($_POST['state'] ?? '');
        $zip = trim($_POST['zip'] ?? '');
        $nameParts = $name !== '' ? preg_split('/\s+/', $name, 2) : [];
        $billingFirstName = $nameParts[0] ?? '';
        $billingLastName = $nameParts[1] ?? '';
        $shippingSame = isset($_POST['shipping_same']) && $_POST['shipping_same'] === '1';
        $shippingPhone = null;
        $shippingPhoneError = '';
        if ($shippingSame) {
            $shippingFirstName = $billingFirstName;
            $shippingLastName = $billingLastName;
            $shippingCompany = $companyName;
            $shippingPhone = $cellPhone;
            $shippingAddress1 = $address;
            $shippingAddress2 = $address2;
            $shippingCity = $city;
            $shippingState = $state;
            $shippingPostcode = $zip;
        } else {
            $shippingFirstName = trim($_POST['shipping_first_name'] ?? '');
            $shippingLastName = trim($_POST['shipping_last_name'] ?? '');
            $shippingCompany = trim($_POST['shipping_company'] ?? '');
            $shippingPhoneRaw = trim($_POST['shipping_phone'] ?? '');
            $shippingPhone = $shippingPhoneRaw !== '' ? opd_normalize_us_phone($shippingPhoneRaw) : null;
            if ($shippingPhoneRaw !== '' && $shippingPhone === null) {
                $shippingPhoneError = 'Shipping phone must be a valid 10-digit US number.';
            }
            $shippingAddress1 = trim($_POST['shipping_address1'] ?? '');
            $shippingAddress2 = trim($_POST['shipping_address2'] ?? '');
            $shippingCity = trim($_POST['shipping_city'] ?? '');
            $shippingState = trim($_POST['shipping_state'] ?? '');
            $shippingPostcode = trim($_POST['shipping_postcode'] ?? '');
        }
        if ($name === '' || $email === '') {
            $message = 'Name and email are required.';
            $messageClass = 'notice is-error';
        } elseif ($cellPhone === null) {
            $message = 'Cell phone must be a valid 10-digit US number.';
            $messageClass = 'notice is-error';
        } elseif ($shippingPhoneError !== '') {
            $message = $shippingPhoneError;
            $messageClass = 'notice is-error';
        } elseif ($ccEmailError !== '') {
            $message = $ccEmailError;
            $messageClass = 'notice is-error';
        } else {
            $stmt = $pdo->prepare(
                'UPDATE users SET name = ?, lastName = ?, email = ?, ccEmail = ?, companyName = ?, cellPhone = ?, address = ?, address2 = ?, city = ?, state = ?, zip = ?, shippingFirstName = ?, shippingLastName = ?, shippingCompany = ?, shippingPhone = ?, shippingAddress1 = ?, shippingAddress2 = ?, shippingCity = ?, shippingState = ?, shippingPostcode = ?, updatedAt = ? WHERE id = ?'
            );
            $stmt->execute([
                $name,
                $billingLastName !== '' ? $billingLastName : null,
                $email,
                $ccEmail,
                $companyName !== '' ? $companyName : null,
                $cellPhone,
                $address !== '' ? $address : null,
                $address2 !== '' ? $address2 : null,
                $city !== '' ? $city : null,
                $state !== '' ? $state : null,
                $zip !== '' ? $zip : null,
                $shippingFirstName !== '' ? $shippingFirstName : null,
                $shippingLastName !== '' ? $shippingLastName : null,
                $shippingCompany !== '' ? $shippingCompany : null,
                $shippingPhone,
                $shippingAddress1 !== '' ? $shippingAddress1 : null,
                $shippingAddress2 !== '' ? $shippingAddress2 : null,
                $shippingCity !== '' ? $shippingCity : null,
                $shippingState !== '' ? $shippingState : null,
                $shippingPostcode !== '' ? $shippingPostcode : null,
                gmdate('Y-m-d H:i:s'),
                $user['id']
            ]);
            $linkedName = $name;
            $linkedEmail = $email;
            $linkedCompany = $companyName !== '' ? $companyName : $name;
            $linkedPhone = $cellPhone;
            $now = gmdate('Y-m-d H:i:s');
            $clientUpdate = $pdo->prepare(
                'UPDATE clients SET name = ?, email = ?, phone = ?, updatedAt = ? WHERE linkedUserId = ?'
            );
            $clientUpdate->execute([$linkedCompany, $linkedEmail, $linkedPhone, $now, $user['id']]);
            $vendorUpdate = $pdo->prepare(
                'UPDATE vendors SET name = ?, contact = ?, email = ?, phone = ?, updatedAt = ? WHERE linkedUserId = ?'
            );
            $vendorUpdate->execute([$linkedCompany, $linkedName, $linkedEmail, $linkedPhone, $now, $user['id']]);
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
    } elseif ($action === 'edit_payment_method') {
        $pmId = trim($_POST['pm_id'] ?? '');
        $label = trim($_POST['pm_label'] ?? '');
        $type = trim($_POST['pm_type'] ?? '');
        $last4 = trim($_POST['pm_last4'] ?? '');
        $expMonthRaw = trim($_POST['pm_exp_month'] ?? '');
        $expYearRaw = trim($_POST['pm_exp_year'] ?? '');
        $expMonth = $expMonthRaw === '' ? null : (int) $expMonthRaw;
        $expYear = $expYearRaw === '' ? null : (int) $expYearRaw;

        if ($pmId === '') {
            $message = 'Payment method not found.';
            $messageClass = 'notice is-error';
        } elseif ($label === '') {
            $message = 'Payment method label is required.';
            $messageClass = 'notice is-error';
        } elseif ($last4 !== '' && !preg_match('/^\d{4}$/', $last4)) {
            $message = 'Last 4 must be exactly four digits.';
            $messageClass = 'notice is-error';
        } elseif ($expMonth !== null && ($expMonth < 1 || $expMonth > 12)) {
            $message = 'Expiration month must be between 1 and 12.';
            $messageClass = 'notice is-error';
        } else {
            $updated = site_update_payment_method($user['id'], $pmId, [
                'label' => $label,
                'type' => $type !== '' ? $type : null,
                'last4' => $last4 !== '' ? $last4 : null,
                'expMonth' => $expMonth,
                'expYear' => $expYear,
            ]);
            $message = $updated ? 'Payment method updated.' : 'Payment method not found.';
            if (!$updated) {
                $messageClass = 'notice is-error';
            }
        }
    } elseif ($action === 'remove_payment_method') {
        $pmId = trim($_POST['pm_id'] ?? '');
        if ($pmId === '') {
            $message = 'Payment method not found.';
            $messageClass = 'notice is-error';
        } else {
            $deleted = site_delete_payment_method($user['id'], $pmId);
            if ($deleted) {
                $stripeId = trim((string) ($deleted['stripePaymentMethodId'] ?? ''));
                if ($stripeId !== '') {
                    stripe_detach_payment_method($stripeId);
                }
                $message = 'Payment method removed.';
            } else {
                $message = 'Payment method not found.';
                $messageClass = 'notice is-error';
            }
        }
    } elseif ($action === 'set_primary_payment_method') {
        $pmId = trim($_POST['pm_id'] ?? '');
        if ($pmId === '') {
            $message = 'Payment method not found.';
            $messageClass = 'notice is-error';
        } else {
            $set = site_set_primary_payment_method($user['id'], $pmId);
            $message = $set ? 'Primary payment method updated.' : 'Payment method not found.';
            if (!$set) {
                $messageClass = 'notice is-error';
            }
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
                'INSERT INTO payment_methods (id, userId, label, type, brand, last4, stripePaymentMethodId, expMonth, expYear, createdAt, updatedAt)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                opd_generate_id('pm'),
                $user['id'],
                $label,
                $type !== '' ? $type : null,
                null,
                $last4 !== '' ? $last4 : null,
                null,
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
$displayName = trim((string) ($userRecord['name'] ?? ''));
$nameParts = $displayName !== '' ? preg_split('/\s+/', $displayName, 2) : [];
$billingFirstName = $nameParts[0] ?? '';
$billingLastName = $nameParts[1] ?? '';
$shippingSameDefault = true;
$shippingCompare = [
    'shippingFirstName' => $billingFirstName,
    'shippingLastName' => $billingLastName,
    'shippingCompany' => (string) ($userRecord['companyName'] ?? ''),
    'shippingPhone' => (string) ($userRecord['cellPhone'] ?? ''),
    'shippingAddress1' => (string) ($userRecord['address'] ?? ''),
    'shippingAddress2' => (string) ($userRecord['address2'] ?? ''),
    'shippingCity' => (string) ($userRecord['city'] ?? ''),
    'shippingState' => (string) ($userRecord['state'] ?? ''),
    'shippingPostcode' => (string) ($userRecord['zip'] ?? '')
];
foreach ($shippingCompare as $field => $billingValue) {
    $stored = trim((string) ($userRecord[$field] ?? ''));
    if ($stored !== '' && $stored !== trim((string) $billingValue)) {
        $shippingSameDefault = false;
        break;
    }
}
$shippingDefaults = [
    'shippingFirstName' => trim((string) ($userRecord['shippingFirstName'] ?? '')) ?: $billingFirstName,
    'shippingLastName' => trim((string) ($userRecord['shippingLastName'] ?? '')) ?: $billingLastName,
    'shippingCompany' => trim((string) ($userRecord['shippingCompany'] ?? '')) ?: (string) ($userRecord['companyName'] ?? ''),
    'shippingPhone' => trim((string) ($userRecord['shippingPhone'] ?? '')) ?: (string) ($userRecord['cellPhone'] ?? ''),
    'shippingAddress1' => trim((string) ($userRecord['shippingAddress1'] ?? '')) ?: (string) ($userRecord['address'] ?? ''),
    'shippingAddress2' => trim((string) ($userRecord['shippingAddress2'] ?? '')) ?: (string) ($userRecord['address2'] ?? ''),
    'shippingCity' => trim((string) ($userRecord['shippingCity'] ?? '')) ?: (string) ($userRecord['city'] ?? ''),
    'shippingState' => trim((string) ($userRecord['shippingState'] ?? '')) ?: (string) ($userRecord['state'] ?? ''),
    'shippingPostcode' => trim((string) ($userRecord['shippingPostcode'] ?? '')) ?: (string) ($userRecord['zip'] ?? '')
];
$paymentMethods = site_get_payment_methods($user['id']);
$stripePublishableKey = stripe_publishable_key();
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Account - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260326d" />
  <style>
    .shipping-address-fields {
      transition: opacity 0.2s ease;
    }
    .shipping-address-fields.is-hidden {
      opacity: 0;
      pointer-events: none;
    }
    .field-help {
      display: block;
      font-size: 0.85em;
      color: #666;
      margin-top: 4px;
    }
  </style>
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
              <label for="cc_email">CC Email</label>
              <input id="cc_email" name="cc_email" type="email" value="<?php echo htmlspecialchars($userRecord['ccEmail'] ?? '', ENT_QUOTES); ?>" placeholder="optional" />
              <small class="field-help">Also receives a copy of all account emails.</small>
            </div>
            <div>
              <label for="company_name">Company name</label>
              <input id="company_name" name="company_name" value="<?php echo htmlspecialchars($userRecord['companyName'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div>
              <label for="cell_phone">Cell phone</label>
              <input
                id="cell_phone"
                name="cell_phone"
                type="tel"
                inputmode="numeric"
                pattern="[0-9]{10}"
                placeholder="Phone number"
                value="<?php echo htmlspecialchars($userRecord['cellPhone'] ?? '', ENT_QUOTES); ?>"
                required
              />
              <small class="field-help">10-digit US number (e.g. 5551234567)</small>
            </div>
            <div class="span-2">
              <label for="address">Address line 1</label>
              <input id="address" name="address" autocomplete="street-address" value="<?php echo htmlspecialchars($userRecord['address'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <div class="span-2">
              <label for="address2">Address line 2</label>
              <input id="address2" name="address2" value="<?php echo htmlspecialchars($userRecord['address2'] ?? '', ENT_QUOTES); ?>" />
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
              <label class="checkbox-row" for="shipping_same">
                <input
                  id="shipping_same"
                  name="shipping_same"
                  type="checkbox"
                  value="1"
                  <?php echo $shippingSameDefault ? 'checked' : ''; ?>
                />
                Shipping, same as User
              </label>
            </div>
            <noscript>
              <div class="notice" style="margin-top: 8px;">
                JavaScript is disabled. Shipping fields are shown below.
              </div>
              <style>
                #shipping-fields { display: block !important; }
              </style>
            </noscript>
            <div class="span-2 shipping-address-fields<?php echo $shippingSameDefault ? ' is-hidden' : ''; ?>" id="shipping-fields" style="<?php echo $shippingSameDefault ? 'display: none;' : ''; ?>">
              <div class="form-grid cols-2">
                <div>
                  <label for="shipping_first_name">First Name (Shipping)</label>
                  <input
                    id="shipping_first_name"
                    name="shipping_first_name"
                    value="<?php echo htmlspecialchars($shippingDefaults['shippingFirstName'] ?? '', ENT_QUOTES); ?>"
                  />
                </div>
                <div>
                  <label for="shipping_last_name">Last Name (Shipping)</label>
                  <input
                    id="shipping_last_name"
                    name="shipping_last_name"
                    value="<?php echo htmlspecialchars($shippingDefaults['shippingLastName'] ?? '', ENT_QUOTES); ?>"
                  />
                </div>
                <div>
                  <label for="shipping_phone">Phone (Shipping)</label>
                  <input
                    id="shipping_phone"
                    name="shipping_phone"
                    type="tel"
                    inputmode="numeric"
                    pattern="[0-9]{10}"
                    placeholder="Phone number"
                    value="<?php echo htmlspecialchars($shippingDefaults['shippingPhone'] ?? '', ENT_QUOTES); ?>"
                  />
                  <small class="field-help">10-digit US number (e.g. 5551234567)</small>
                </div>
                <div>
                  <label for="shipping_company">Company (Shipping)</label>
                  <input
                    id="shipping_company"
                    name="shipping_company"
                    value="<?php echo htmlspecialchars($shippingDefaults['shippingCompany'] ?? '', ENT_QUOTES); ?>"
                  />
                </div>
                <div class="span-2">
                  <label for="shipping_address1">Address line 1 (Shipping)</label>
                  <input
                    id="shipping_address1"
                    name="shipping_address1"
                    value="<?php echo htmlspecialchars($shippingDefaults['shippingAddress1'] ?? '', ENT_QUOTES); ?>"
                  />
                </div>
                <div class="span-2">
                  <label for="shipping_address2">Address line 2 (Shipping)</label>
                  <input
                    id="shipping_address2"
                    name="shipping_address2"
                    value="<?php echo htmlspecialchars($shippingDefaults['shippingAddress2'] ?? '', ENT_QUOTES); ?>"
                  />
                </div>
                <div class="span-2">
                  <label for="shipping_city">City (Shipping)</label>
                  <input
                    id="shipping_city"
                    name="shipping_city"
                    value="<?php echo htmlspecialchars($shippingDefaults['shippingCity'] ?? '', ENT_QUOTES); ?>"
                  />
                </div>
                <div>
                  <label for="shipping_state">State (Shipping)</label>
                  <input
                    id="shipping_state"
                    name="shipping_state"
                    value="<?php echo htmlspecialchars($shippingDefaults['shippingState'] ?? '', ENT_QUOTES); ?>"
                  />
                </div>
                <div>
                  <label for="shipping_postcode">Postcode (Shipping)</label>
                  <input
                    id="shipping_postcode"
                    name="shipping_postcode"
                    value="<?php echo htmlspecialchars($shippingDefaults['shippingPostcode'] ?? '', ENT_QUOTES); ?>"
                  />
                </div>
              </div>
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
          <p class="meta">Save a card with Stripe for manual recharges, or add non-card methods for reference.</p>
          <?php if (!$paymentMethods): ?>
            <div class="notice">No payment methods saved.</div>
          <?php else: ?>
            <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th>Label</th>
                  <th>Type</th>
                  <th>Last 4</th>
                  <th>Expires</th>
                  <th>Primary</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($paymentMethods as $method): ?>
                  <?php $pmId = $method['id'] ?? ''; $isPrimary = !empty($method['isPrimary']); ?>
                  <tr class="<?php echo $isPrimary ? 'pm-primary-row' : ''; ?>">
                    <td><?php echo htmlspecialchars($method['label'] ?? '', ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($method['brand'] ?? $method['type'] ?? '', ENT_QUOTES); ?></td>
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
                    <td>
                      <?php if ($isPrimary): ?>
                        <span class="pm-primary-badge">Primary</span>
                      <?php else: ?>
                        <form method="POST" style="display:inline;">
                          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                          <input type="hidden" name="action" value="set_primary_payment_method" />
                          <input type="hidden" name="pm_id" value="<?php echo htmlspecialchars($pmId, ENT_QUOTES); ?>" />
                          <button class="btn-link" type="submit">Set primary</button>
                        </form>
                      <?php endif; ?>
                    </td>
                    <td>
                      <div class="pm-actions">
                        <button type="button" class="btn-link" data-pm-edit="<?php echo htmlspecialchars($pmId, ENT_QUOTES); ?>"
                          data-pm-label="<?php echo htmlspecialchars($method['label'] ?? '', ENT_QUOTES); ?>"
                          data-pm-type="<?php echo htmlspecialchars($method['type'] ?? '', ENT_QUOTES); ?>"
                          data-pm-last4="<?php echo htmlspecialchars($method['last4'] ?? '', ENT_QUOTES); ?>"
                          data-pm-exp-month="<?php echo htmlspecialchars((string) ($method['expMonth'] ?? ''), ENT_QUOTES); ?>"
                          data-pm-exp-year="<?php echo htmlspecialchars((string) ($method['expYear'] ?? ''), ENT_QUOTES); ?>"
                        >Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this payment method? This cannot be undone.');">
                          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                          <input type="hidden" name="action" value="remove_payment_method" />
                          <input type="hidden" name="pm_id" value="<?php echo htmlspecialchars($pmId, ENT_QUOTES); ?>" />
                          <button class="btn-link btn-link-danger" type="submit">Remove</button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>

            <!-- Edit Payment Method Modal -->
            <div id="pm-edit-modal" class="modal-overlay" style="display:none;">
              <div class="modal-content" style="max-width:480px;">
                <h3>Edit payment method</h3>
                <form method="POST" class="form-grid cols-2" id="pm-edit-form">
                  <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                  <input type="hidden" name="action" value="edit_payment_method" />
                  <input type="hidden" name="pm_id" id="pm-edit-id" value="" />
                  <div class="span-2">
                    <label for="pm-edit-label">Label</label>
                    <input id="pm-edit-label" name="pm_label" required />
                  </div>
                  <div>
                    <label for="pm-edit-type">Type</label>
                    <input id="pm-edit-type" name="pm_type" />
                  </div>
                  <div>
                    <label for="pm-edit-last4">Last 4</label>
                    <input id="pm-edit-last4" name="pm_last4" inputmode="numeric" maxlength="4" />
                  </div>
                  <div>
                    <label for="pm-edit-exp-month">Exp month</label>
                    <input id="pm-edit-exp-month" name="pm_exp_month" type="number" min="1" max="12" placeholder="MM" />
                  </div>
                  <div>
                    <label for="pm-edit-exp-year">Exp year</label>
                    <input id="pm-edit-exp-year" name="pm_exp_year" type="number" min="2024" max="2100" placeholder="YYYY" />
                  </div>
                  <div class="span-2 pm-edit-actions">
                    <button class="btn" type="submit">Save</button>
                    <button class="btn-outline" type="button" id="pm-edit-cancel">Cancel</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endif; ?>

          <div class="payment-methods-grid" style="margin-top:16px;">
            <div>
              <h3>Add card (Stripe)</h3>
              <p class="meta">Cards are stored in Stripe and can be recharged without re-entry.</p>
              <?php if ($stripePublishableKey === ''): ?>
                <div class="notice is-error">Payment processing is temporarily unavailable. Please contact support.</div>
              <?php else: ?>
                <form id="stripe-card-form" class="form-grid cols-2">
                  <div class="span-2">
                    <label for="stripe-card-label">Label (optional)</label>
                    <input id="stripe-card-label" name="stripe-card-label" placeholder="Main operations card" />
                  </div>
                  <div class="span-2">
                    <label for="stripe-card-name">Cardholder name</label>
                    <input
                      id="stripe-card-name"
                      name="stripe-card-name"
                      required
                      autocomplete="cc-name"
                      value="<?php echo htmlspecialchars($userRecord['name'] ?? '', ENT_QUOTES); ?>"
                    />
                  </div>
                  <div class="span-2">
                    <label for="stripe-card-email">Cardholder email</label>
                    <input
                      id="stripe-card-email"
                      name="stripe-card-email"
                      type="email"
                      required
                      autocomplete="email"
                      value="<?php echo htmlspecialchars($userRecord['email'] ?? '', ENT_QUOTES); ?>"
                    />
                  </div>
                  <div class="span-2">
                    <label for="card-number-element">Card number</label>
                    <div id="card-number-element" class="stripe-card-element"></div>
                  </div>
                  <div>
                    <label for="card-expiry-element">Expiration</label>
                    <div id="card-expiry-element" class="stripe-card-element"></div>
                  </div>
                  <div>
                    <label for="card-cvc-element">CVC</label>
                    <div id="card-cvc-element" class="stripe-card-element"></div>
                  </div>
                  <div class="span-2">
                    <label for="stripe-card-zip">Billing ZIP</label>
                    <input id="stripe-card-zip" name="stripe-card-zip" inputmode="numeric" autocomplete="postal-code" />
                  </div>
                  <div class="span-2">
                    <div id="stripe-card-error" class="stripe-card-error" role="alert"></div>
                  </div>
                  <div class="span-2 stripe-card-actions">
                    <button class="btn" type="submit">Save card</button>
                    <span id="stripe-card-status" class="meta"></span>
                  </div>
                  <div class="span-2">
                    <div id="stripe-card-banner" class="notice">Loading card fields...</div>
                  </div>
                </form>
              <?php endif; ?>
            </div>

            <div>
              <h3>Add non-card method</h3>
              <p class="meta">Use this for ACH, wire, or internal payment references.</p>
              <form method="POST" class="form-grid cols-2">
                <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                <input type="hidden" name="action" value="payment_method" />
                <div class="span-2">
                  <label for="pm_label">Payment method label</label>
                  <input id="pm_label" name="pm_label" placeholder="ACH - Main, Wire - Primary" required />
                </div>
                <div>
                  <label for="pm_type">Type</label>
                  <input id="pm_type" name="pm_type" placeholder="ACH, Wire, Internal" />
                </div>
                <div>
                  <label for="pm_last4">Last 4 (optional)</label>
                  <input id="pm_last4" name="pm_last4" inputmode="numeric" maxlength="4" />
                </div>
                <div>
                  <label for="pm_exp_month">Exp month (optional)</label>
                  <input id="pm_exp_month" name="pm_exp_month" type="number" min="1" max="12" placeholder="MM" />
                </div>
                <div>
                  <label for="pm_exp_year">Exp year (optional)</label>
                  <input id="pm_exp_year" name="pm_exp_year" type="number" min="2024" max="2100" placeholder="YYYY" />
                </div>
                <div class="span-2">
                  <button class="btn" type="submit">Add payment method</button>
                </div>
              </form>
            </div>
          </div>
        </section>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>

  <?php if ($stripePublishableKey !== ''): ?>
    <script src="https://js.stripe.com/v3" nonce="<?php echo opd_csp_nonce(); ?>"></script>
    <script nonce="<?php echo opd_csp_nonce(); ?>">
      (function () {
        var form = document.getElementById('stripe-card-form');
        if (!form) {
          return;
        }
        var statusEl = document.getElementById('stripe-card-status');
        var bannerEl = document.getElementById('stripe-card-banner');
        var stripeKey = <?php echo json_encode($stripePublishableKey, JSON_HEX_TAG | JSON_HEX_AMP); ?>;

        var didInit = false;
        function showStripeLoadError(message) {
          var text = message || 'Stripe failed to load. Disable blockers or allow js.stripe.com.';
          if (bannerEl) {
            bannerEl.textContent = text;
            bannerEl.className = 'notice is-error';
            bannerEl.style.display = 'block';
          }
          if (statusEl) {
            statusEl.textContent = text;
          }
        }

        function waitForStripe(startedAt) {
          if (window.Stripe) {
            initStripe();
            return;
          }
          if ((new Date().getTime() - startedAt) > 8000) {
            showStripeLoadError('Stripe failed to load. Disable blockers or allow js.stripe.com.');
            return;
          }
          setTimeout(function () {
            waitForStripe(startedAt);
          }, 250);
        }

        function initStripe() {
          if (didInit) {
            return;
          }
          didInit = true;
          if (!window.Stripe || !stripeKey) {
            showStripeLoadError('Stripe failed to load. Disable blockers or allow js.stripe.com.');
            return;
          }

          var stripe = Stripe(stripeKey);
          var elements = stripe.elements();
          var elementStyle = {
            base: {
              color: '#111111',
              fontSize: '16px',
              lineHeight: '24px',
              fontFamily: 'Segoe UI, Tahoma, Geneva, Verdana, sans-serif',
              '::placeholder': { color: '#9aa0a6' }
            },
            invalid: { color: '#b42318' }
          };
          var cardNumber = elements.create('cardNumber', { style: elementStyle });
          var cardExpiry = elements.create('cardExpiry', { style: elementStyle });
          var cardCvc = elements.create('cardCvc', { style: elementStyle });
          cardNumber.mount('#card-number-element');
          cardExpiry.mount('#card-expiry-element');
          cardCvc.mount('#card-cvc-element');

          var cardNumberWrap = document.getElementById('card-number-element');
          var cardExpiryWrap = document.getElementById('card-expiry-element');
          var cardCvcWrap = document.getElementById('card-cvc-element');

          var errorEl = document.getElementById('stripe-card-error');
          var labelInput = document.getElementById('stripe-card-label');
          var nameInput = document.getElementById('stripe-card-name');
          var emailInput = document.getElementById('stripe-card-email');
          var zipInput = document.getElementById('stripe-card-zip');
          var csrfToken = <?php echo json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP); ?>;
          var submitBtn = form.querySelector('button[type="submit"]');
          var numberComplete = false;
          var expiryComplete = false;
          var cvcComplete = false;
          var cardErrors = { cardNumber: '', cardExpiry: '', cardCvc: '' };

          function setError(message) {
            if (errorEl) {
              errorEl.textContent = message || '';
            }
          }

          function handleCardChange(event) {
            var code = (event.error && event.error.code) ? (' (' + event.error.code + ')') : '';
            var message = event.error ? (event.error.message + code) : '';
            if (event.elementType === 'cardNumber') {
              numberComplete = !!event.complete;
              cardErrors.cardNumber = message;
            }
            if (event.elementType === 'cardExpiry') {
              expiryComplete = !!event.complete;
              cardErrors.cardExpiry = message;
            }
            if (event.elementType === 'cardCvc') {
              cvcComplete = !!event.complete;
              cardErrors.cardCvc = message;
            }
            var nextError = cardErrors.cardNumber || cardErrors.cardExpiry || cardErrors.cardCvc;
            setError(nextError);
          }

          var readiness = { number: false, expiry: false, cvc: false };
          function markReady(key) {
            readiness[key] = true;
            if (readiness.number && readiness.expiry && readiness.cvc && bannerEl) {
              bannerEl.style.display = 'none';
            }
          }

          cardNumber.on('ready', function () { markReady('number'); });
          cardExpiry.on('ready', function () { markReady('expiry'); });
          cardCvc.on('ready', function () { markReady('cvc'); });

          cardNumber.on('change', handleCardChange);
          cardExpiry.on('change', handleCardChange);
          cardCvc.on('change', handleCardChange);

          if (cardNumberWrap) {
            cardNumberWrap.addEventListener('click', function () {
              cardNumber.focus();
            });
          }
          if (cardExpiryWrap) {
            cardExpiryWrap.addEventListener('click', function () {
              cardExpiry.focus();
            });
          }
          if (cardCvcWrap) {
            cardCvcWrap.addEventListener('click', function () {
              cardCvc.focus();
            });
          }

          setTimeout(function () {
            var hasNumber = cardNumberWrap && cardNumberWrap.querySelector('iframe');
            var hasExpiry = cardExpiryWrap && cardExpiryWrap.querySelector('iframe');
            var hasCvc = cardCvcWrap && cardCvcWrap.querySelector('iframe');
            if (!hasNumber || !hasExpiry || !hasCvc) {
              var message = 'Stripe is blocked. Disable blockers or allow js.stripe.com to add a card.';
              setError(message);
              if (bannerEl) {
                bannerEl.textContent = message;
                bannerEl.className = 'notice is-error';
                bannerEl.style.display = 'block';
              }
            }
          }, 1200);

          form.addEventListener('submit', function (event) {
            event.preventDefault();
            setError('');
            var holderName = nameInput ? nameInput.value.trim() : '';
            var holderEmail = emailInput ? emailInput.value.trim() : '';
            if (!holderName) {
              setError('Cardholder name is required.');
              if (statusEl) {
                statusEl.textContent = '';
              }
              return;
            }
            if (!holderEmail) {
              setError('Cardholder email is required.');
              if (statusEl) {
                statusEl.textContent = '';
              }
              return;
            }
            if (!numberComplete || !expiryComplete || !cvcComplete) {
              setError(cardErrors.cardNumber || cardErrors.cardExpiry || cardErrors.cardCvc || 'Enter complete card details.');
              if (statusEl) {
                statusEl.textContent = '';
              }
              return;
            }
            if (statusEl) {
              statusEl.textContent = 'Saving card...';
            }
            if (submitBtn) {
              submitBtn.disabled = true;
            }
            var label = labelInput ? labelInput.value.trim() : '';

            fetch('/api/stripe_setup_intent.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
              },
              body: JSON.stringify({ label: label })
            })
              .then(function (setupResp) {
                return setupResp.json().catch(function () {
                  return {};
                }).then(function (setupData) {
                  if (!setupResp.ok || !setupData.clientSecret) {
                    throw new Error(setupData.error || 'Unable to start card setup.');
                  }
                  return setupData;
                });
              })
              .then(function (setupData) {
                var billingDetails = {
                  name: holderName,
                  email: holderEmail
                };
                var zip = zipInput ? zipInput.value.trim() : '';
                if (zip) {
                  billingDetails.address = { postal_code: zip };
                }
                return stripe.confirmCardSetup(setupData.clientSecret, {
                  payment_method: {
                    card: cardNumber,
                    billing_details: billingDetails
                  }
                });
              })
              .then(function (result) {
                if (result.error) {
                  var code = result.error.code ? (' (' + result.error.code + ')') : '';
                  throw new Error((result.error.message || 'Card setup failed.') + code);
                }
                var paymentMethodId = result.setupIntent && result.setupIntent.payment_method;
                if (!paymentMethodId) {
                  throw new Error('Card setup incomplete.');
                }
                return paymentMethodId;
              })
              .then(function (paymentMethodId) {
                return fetch('/api/stripe_payment_methods.php', {
                  method: 'POST',
                  headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                  },
                  body: JSON.stringify({ paymentMethodId: paymentMethodId, label: label })
                }).then(function (saveResp) {
                  return saveResp.json().catch(function () {
                    return {};
                  }).then(function (saveData) {
                    if (!saveResp.ok) {
                      throw new Error(saveData.error || 'Unable to save card.');
                    }
                    return saveData;
                  });
                });
              })
              .then(function () {
                if (statusEl) {
                  statusEl.textContent = 'Card saved. Refresh to see it in your list.';
                }
                if (submitBtn) {
                  submitBtn.disabled = false;
                }
                if (labelInput) {
                  labelInput.value = '';
                }
                if (zipInput) {
                  zipInput.value = '';
                }
                cardNumber.clear();
                cardExpiry.clear();
                cardCvc.clear();
              })
              .catch(function (err) {
                if (statusEl) {
                  statusEl.textContent = err && err.message ? err.message : 'Unable to save card.';
                }
                if (submitBtn) {
                  submitBtn.disabled = false;
                }
              });
          });
        }

        waitForStripe(new Date().getTime());
      })();
    </script>
  <?php endif; ?>
  <script nonce="<?php echo opd_csp_nonce(); ?>">
    (function () {
      var toggle = document.getElementById('shipping_same');
      var fields = document.getElementById('shipping-fields');
      if (!toggle || !fields) {
        return;
      }
      function syncShipping() {
        if (toggle.checked) {
          fields.classList.add('is-hidden');
          setTimeout(function() { fields.style.display = 'none'; }, 200);
        } else {
          fields.style.display = 'block';
          // Allow display:block to paint before removing opacity class
          requestAnimationFrame(function() {
            fields.classList.remove('is-hidden');
          });
        }
      }
      toggle.addEventListener('change', syncShipping);
      syncShipping();
    })();
  </script>
  <script nonce="<?php echo opd_csp_nonce(); ?>">
    (function () {
      var modal = document.getElementById('pm-edit-modal');
      var form = document.getElementById('pm-edit-form');
      var cancelBtn = document.getElementById('pm-edit-cancel');
      if (!modal || !form) return;

      document.querySelectorAll('[data-pm-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          document.getElementById('pm-edit-id').value = btn.getAttribute('data-pm-edit') || '';
          document.getElementById('pm-edit-label').value = btn.getAttribute('data-pm-label') || '';
          document.getElementById('pm-edit-type').value = btn.getAttribute('data-pm-type') || '';
          document.getElementById('pm-edit-last4').value = btn.getAttribute('data-pm-last4') || '';
          document.getElementById('pm-edit-exp-month').value = btn.getAttribute('data-pm-exp-month') || '';
          document.getElementById('pm-edit-exp-year').value = btn.getAttribute('data-pm-exp-year') || '';
          modal.style.display = 'flex';
        });
      });

      if (cancelBtn) {
        cancelBtn.addEventListener('click', function () {
          modal.style.display = 'none';
        });
      }

      modal.addEventListener('click', function (e) {
        if (e.target === modal) {
          modal.style.display = 'none';
        }
      });
    })();
  </script>
</body>
</html>
