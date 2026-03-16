<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_require_auth();
$pdo = opd_db();
$paymentMethods = site_get_payment_methods($user['id']);
$autoApproveHelpText = trim((string) (site_get_setting_value('auto_approve_help_text') ?? ''));
function render_payment_method_options(array $paymentMethods, ?string $selectedId = null): string
{
    $options = '<option value="">Select a payment method</option>';
    foreach ($paymentMethods as $method) {
        $id = (string) ($method['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $label = site_format_payment_method_label($method);
        $selected = ($selectedId !== null && $id === $selectedId) ? ' selected' : '';
        $options .= sprintf(
            '<option value="%s"%s>%s</option>',
            htmlspecialchars($id, ENT_QUOTES),
            $selected,
            htmlspecialchars($label, ENT_QUOTES)
        );
    }
    return $options;
}
$message = '';
$messageClass = 'notice';
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
        $autoApprove = !empty($_POST['auto_approve']);
        $paymentMethodId = trim((string) ($_POST['payment_method_id'] ?? ''));
        $validPaymentMethods = array_column($paymentMethods, 'id');
        if ($paymentMethodId === '' || !in_array($paymentMethodId, $validPaymentMethods, true)) {
            $paymentMethodId = null;
        }

        $phoneRaw = trim((string) ($_POST['phone'] ?? ''));
        $normalizedPhone = $phoneRaw !== '' ? opd_normalize_us_phone($phoneRaw) : null;
        $termsAgree = !empty($_POST['terms_agree']);

        $vendorEmail = trim((string) ($_POST['email'] ?? ''));
        $matchedUser = $vendorEmail !== '' ? site_find_user_by_email($vendorEmail) : null;
        $linkedUserId = '';
        $vendorStatus = $_POST['status'] ?? 'active';
        if ($matchedUser && ($matchedUser['id'] ?? '') !== $user['id']) {
            $linkedUserId = (string) $matchedUser['id'];
            $vendorStatus = 'requested';
        }

        $phoneStmt = $pdo->prepare('SELECT cellPhone FROM users WHERE id = ? LIMIT 1');
        $phoneStmt->execute([$user['id']]);
        $phoneRow = $phoneStmt->fetch();
        $userCellPhone = opd_normalize_us_phone(trim((string) ($phoneRow['cellPhone'] ?? '')));
        if ($userCellPhone === null) {
            $message = 'You must have a cell phone number on your account to add a vendor. Update your profile in Account Settings.';
            $messageClass = 'notice is-error';
        } elseif ($normalizedPhone === null) {
            $message = 'Vendor phone must be a valid 10-digit US number.';
            $messageClass = 'notice is-error';
        } elseif (!$termsAgree) {
            $message = 'You must agree to the ' . opd_site_name() . ' terms.';
            $messageClass = 'notice is-error';
        } elseif ($action === 'invite' && !$smsConsent) {
            $message = 'SMS consent is required to send an invite.';
            $messageClass = 'notice is-error';
        } else {
            if ($linkedUserId === '' || !site_vendor_exists_for_user_linked($user['id'], $linkedUserId, $vendorEmail)) {
                site_simple_create('vendors', $user['id'], [
                    'name' => $_POST['name'] ?? '',
                    'contact' => $_POST['contact'] ?? '',
                    'email' => $vendorEmail,
                    'linkedUserId' => $linkedUserId !== '' ? $linkedUserId : null,
                    'phone' => $normalizedPhone,
                    'status' => $vendorStatus,
                    'purchaseLimitOrder' => $limitOrder,
                    'purchaseLimitDay' => $limitDay,
                    'purchaseLimitMonth' => $limitMonth,
                    'limitNone' => $limitNone ? 1 : 0,
                    'autoApprove' => $autoApprove ? 1 : 0,
                    'paymentMethodId' => $paymentMethodId,
                    'smsConsent' => $smsConsent ? 1 : 0
                ]);
            }
            $requesterEmail = $user['email'] ?? '';
            if ($linkedUserId !== '' && $requesterEmail !== '') {
                if (!site_client_exists_for_user_linked($linkedUserId, $user['id'], $requesterEmail)) {
                    site_simple_create('clients', $linkedUserId, [
                        'name' => $user['name'] ?? '',
                        'email' => $requesterEmail,
                        'linkedUserId' => $user['id'],
                        'phone' => '',
                        'status' => 'pending',
                        'notes' => ''
                    ]);
                }
            }
            if ($action === 'invite') {
                $template = site_get_setting_value('vendor_invite_sms');
                $baseUrl = site_get_base_url();
                if ($template === null || trim($template) === '') {
                    $message = 'Vendor added, but no vendor invite SMS template is set in System Settings.';
                    $messageClass = 'notice is-error';
                } elseif ($baseUrl === '') {
                    $message = 'Vendor added, but invite link could not be generated.';
                    $messageClass = 'notice is-error';
                } else {
                    $profileStmt = $pdo->prepare('SELECT name, companyName FROM users WHERE id = ? LIMIT 1');
                    $profileStmt->execute([$user['id']]);
                    $profileRow = $profileStmt->fetch() ?: [];
                    $vendorName = trim((string) ($_POST['name'] ?? ''));
                    $vendorContact = trim((string) ($_POST['contact'] ?? ''));
                    $recipientName = $vendorContact !== '' ? $vendorContact : ($vendorName !== '' ? $vendorName : 'there');
                    $context = [
                        'inviter' => $profileRow['name'] ?? $user['name'] ?? '',
                        'company' => $profileRow['companyName'] ?? $user['name'] ?? '',
                        'recipient' => $recipientName,
                    ];
                    $link = $baseUrl . '/dashboard-clients.php';
                    $smsText = site_build_invite_message($template, $link, $context);
                    $rateKey = 'vendor_invite:' . ($user['id'] ?? 'user') . ':' . $normalizedPhone;
                    $sendResult = site_send_invite_sms($normalizedPhone, $smsText, $rateKey);
                    if ($sendResult['ok']) {
                        $message = 'Vendor added. Invite sent.';
                    } else {
                        $message = 'Vendor added, but invite failed: ' . ($sendResult['error'] ?? 'SMS failed.');
                        $messageClass = 'notice is-error';
                    }
                }
            } else {
                $message = 'Vendor added.';
            }
        }
    }
    if ($action === 'update') {
        $vendorId = $_POST['id'] ?? '';
        if (is_string($vendorId) && $vendorId !== '') {
            $limitNone = !empty($_POST['limit_none']);
            $limitOrderRaw = trim((string) ($_POST['limit_order'] ?? ''));
            $limitDayRaw = trim((string) ($_POST['limit_day'] ?? ''));
            $limitMonthRaw = trim((string) ($_POST['limit_month'] ?? ''));
            $limitOrder = $limitNone || $limitOrderRaw === '' ? null : (is_numeric($limitOrderRaw) ? (float) $limitOrderRaw : null);
            $limitDay = $limitNone || $limitDayRaw === '' ? null : (is_numeric($limitDayRaw) ? (float) $limitDayRaw : null);
            $limitMonth = $limitNone || $limitMonthRaw === '' ? null : (is_numeric($limitMonthRaw) ? (float) $limitMonthRaw : null);
            $autoApprove = !empty($_POST['auto_approve']);

            $paymentMethodId = trim((string) ($_POST['payment_method_id'] ?? ''));
            $validPaymentMethods = array_column($paymentMethods, 'id');
            if ($paymentMethodId === '' || !in_array($paymentMethodId, $validPaymentMethods, true)) {
                $paymentMethodId = null;
            }

            $stmt = $pdo->prepare(
                'UPDATE vendors SET purchaseLimitOrder = ?, purchaseLimitDay = ?, purchaseLimitMonth = ?, limitNone = ?, autoApprove = ?, paymentMethodId = ?, updatedAt = ? WHERE id = ? AND userId = ?'
            );
            $stmt->execute([
                $limitOrder,
                $limitDay,
                $limitMonth,
                $limitNone ? 1 : 0,
                $autoApprove ? 1 : 0,
                $paymentMethodId,
                gmdate('Y-m-d H:i:s'),
                $vendorId,
                $user['id']
            ]);
            $message = 'Vendor updated.';
        }
    }
    if ($action === 'delete') {
        site_simple_delete('vendors', $_POST['id'] ?? '');
        $message = 'Vendor removed.';
    }
    if ($action === 'accept' || $action === 'decline') {
        $vendorId = $_POST['id'] ?? '';
        if (is_string($vendorId) && $vendorId !== '') {
            $status = $action === 'accept' ? 'active' : 'declined';
            $limitNone = !empty($_POST['limit_none']);
            $limitOrderRaw = trim((string) ($_POST['limit_order'] ?? ''));
            $limitDayRaw = trim((string) ($_POST['limit_day'] ?? ''));
            $limitMonthRaw = trim((string) ($_POST['limit_month'] ?? ''));
            $limitOrder = $limitNone || $limitOrderRaw === '' ? null : (is_numeric($limitOrderRaw) ? (float) $limitOrderRaw : null);
            $limitDay = $limitNone || $limitDayRaw === '' ? null : (is_numeric($limitDayRaw) ? (float) $limitDayRaw : null);
            $limitMonth = $limitNone || $limitMonthRaw === '' ? null : (is_numeric($limitMonthRaw) ? (float) $limitMonthRaw : null);
            $autoApprove = !empty($_POST['auto_approve']);
            $hasLimitFields = array_key_exists('limit_order', $_POST)
                || array_key_exists('limit_day', $_POST)
                || array_key_exists('limit_month', $_POST)
                || array_key_exists('limit_none', $_POST)
                || array_key_exists('auto_approve', $_POST)
                || array_key_exists('payment_method_id', $_POST);
            $paymentMethodId = trim((string) ($_POST['payment_method_id'] ?? ''));
            $validPaymentMethods = array_column($paymentMethods, 'id');
            if ($paymentMethodId === '' || !in_array($paymentMethodId, $validPaymentMethods, true)) {
                $paymentMethodId = null;
            }
            if ($action === 'accept' && $hasLimitFields) {
                $stmt = $pdo->prepare(
                    'UPDATE vendors SET status = ?, purchaseLimitOrder = ?, purchaseLimitDay = ?, purchaseLimitMonth = ?, limitNone = ?, autoApprove = ?, paymentMethodId = ?, updatedAt = ? WHERE id = ? AND userId = ?'
                );
                $stmt->execute([
                    $status,
                    $limitOrder,
                    $limitDay,
                    $limitMonth,
                    $limitNone ? 1 : 0,
                    $autoApprove ? 1 : 0,
                    $paymentMethodId,
                    gmdate('Y-m-d H:i:s'),
                    $vendorId,
                    $user['id']
                ]);
            } else {
                $stmt = $pdo->prepare('UPDATE vendors SET status = ?, updatedAt = ? WHERE id = ? AND userId = ?');
                $stmt->execute([$status, gmdate('Y-m-d H:i:s'), $vendorId, $user['id']]);
            }

            $vendorStmt = $pdo->prepare('SELECT linkedUserId, email FROM vendors WHERE id = ? AND userId = ? LIMIT 1');
            $vendorStmt->execute([$vendorId, $user['id']]);
            $vendorRow = $vendorStmt->fetch();
            $inviterId = trim((string) ($vendorRow['linkedUserId'] ?? ''));
            if ($inviterId === '' && !empty($vendorRow['email'])) {
                $inviter = site_find_user_by_email((string) $vendorRow['email']);
                $inviterId = $inviter['id'] ?? '';
            }
            if ($inviterId !== '') {
                $updateClient = $pdo->prepare(
                    'UPDATE clients SET status = ?, updatedAt = ? WHERE userId = ? AND (linkedUserId = ? OR LOWER(email) = LOWER(?))'
                );
                $updateClient->execute([
                    $status,
                    gmdate('Y-m-d H:i:s'),
                    $inviterId,
                    $user['id'],
                    $user['email'] ?? ''
                ]);
            }

            $message = $action === 'accept' ? 'Vendor accepted.' : 'Vendor declined.';
        }
    }
}

$vendorStmt = $pdo->prepare(
    'SELECT v.*, u.name AS linkedName, u.email AS linkedEmail, u.companyName AS linkedCompanyName, u.cellPhone AS linkedCellPhone
     FROM vendors v
     LEFT JOIN users u ON v.linkedUserId = u.id
     WHERE v.userId = ?
     ORDER BY v.updatedAt DESC'
);
$vendorStmt->execute([$user['id']]);
$vendors = $vendorStmt->fetchAll();
$pendingVendors = [];
$activeVendors = [];
$declinedVendors = [];
foreach ($vendors as $vendor) {
    $status = strtolower(trim((string) ($vendor['status'] ?? '')));
    if ($status === 'declined') {
        $declinedVendors[] = $vendor;
    } elseif ($status === 'pending') {
        $pendingVendors[] = $vendor;
    } else {
        $activeVendors[] = $vendor;
    }
}
$primaryVendors = array_merge($pendingVendors, $activeVendors);
$isFormError = ($message !== '' && $messageClass === 'notice is-error' && in_array($action, ['create', 'invite'], true));
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Vendors - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260315c" />
  <style>
    .vendor-section-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .vendor-section-header h2 { margin: 0; }
    .vendor-add-toggle { display: none; }
    @media (max-width: 900px) {
      .vendor-add-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: #fdf3df;
        border: 1px solid #e3c97a;
        border-radius: 6px;
        color: #94640b;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
      }
      .vendor-add-toggle[aria-expanded="true"] .vendor-add-toggle-chevron {
        transform: rotate(180deg);
      }
      .vendor-add-toggle-chevron { transition: transform 0.2s; flex-shrink: 0; }
      .vendor-add-form { display: none; margin-top: 12px; }
      .vendor-add-form.is-open { display: block; }
    }
    .vendor-table { width: 100%; border-collapse: collapse; }
    .vendor-table th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--muted); padding: 8px 9px; white-space: nowrap; }
    .vendor-table td { padding: 10px 9px; vertical-align: middle; }
    .vendor-table .vendor-main-row td { border-top: 1px solid #eee; }
    .vendor-table .vendor-main-row td:first-child { width: 25%; }
    .vendor-form { margin: 0; }
    .vendor-name { font-weight: 600; font-size: 14px; }
    .vendor-field-label { font-size: 11px; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .vendor-table input[type="number"] {
      width: 100%;
      max-width: 200px;
      min-width: 130px;
    }
    .vendor-table select {
      width: 100%;
      max-width: 200px;
    }
    .vendor-table .limit-toggle {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 13px;
      white-space: nowrap;
    }
    .vendor-table tbody tr:nth-child(4n+3),
    .vendor-table tbody tr:nth-child(4n+4) {
      background: #f7f8fa;
    }
    .vendor-sub-row td { padding-top: 0; padding-bottom: 16px; }
    .vendor-sub-grid {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      padding: 4px 0 0;
    }
    .vendor-sub-left {
      flex-shrink: 1;
      min-width: 0;
      max-width: 240px;
      word-break: break-word;
      white-space: normal;
    }
    .vendor-sub-right {
      display: flex;
      gap: 20px;
      align-items: flex-start;
      flex-shrink: 0;
      flex-wrap: nowrap;
    }
    .vendor-sub-select {
      width: 100%;
      max-width: none;
      font-size: 13px;
      padding: 6px 8px;
    }
    .vendor-sub-label {
      display: block;
      font-size: 11px;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      margin-bottom: 2px;
    }
    .vendor-sub-value { font-size: 13px; font-weight: 500; }
    .table-action-buttons { display: flex; gap: 6px; white-space: nowrap; }
    .help-icon {
      width: 18px;
      height: 18px;
      border-radius: 50%;
      border: 1px solid #cbd5f5;
      background: #fff;
      color: #4b5563;
      font-size: 12px;
      line-height: 1;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-left: 6px;
      cursor: pointer;
      padding: 0;
    }
    .help-icon.is-open {
      background: #f1f5f9;
      border-color: #94a3b8;
      color: #111827;
    }
    .help-text {
      margin-top: 6px;
      font-size: 12px;
      color: #4b5563;
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      padding: 8px 10px;
      border-radius: 6px;
    }
    @media (max-width: 900px) {
      .vendor-sub-grid { flex-wrap: wrap; }
      .vendor-sub-right { flex-wrap: wrap; gap: 12px; }
      .vendor-table input[type="number"] { min-width: 100px; }
    }
    @media (max-width: 600px) {
      .vendor-sub-grid { flex-direction: column; }
      .vendor-sub-left { max-width: none; }
      .vendor-sub-right { flex-direction: column; gap: 8px; }
    }
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel">
          <div class="vendor-section-header">
            <h2>Vendors</h2>
            <button class="vendor-add-toggle" type="button" aria-expanded="false" aria-controls="vendor-add-form">
              Add Vendor
              <svg class="vendor-add-toggle-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="6 9 12 15 18 9"></polyline>
              </svg>
            </button>
          </div>
          <?php if ($message): ?>
            <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
          <?php endif; ?>
          <div id="vendor-add-form" class="vendor-add-form<?php echo $isFormError ? ' is-open' : ''; ?>">
          <form method="POST" class="form-grid cols-2">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <div>
              <label for="name">Vendor name</label>
              <input id="name" name="name" required value="<?php echo $isFormError ? htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES) : ''; ?>" />
            </div>
            <div>
              <label for="contact">Contact</label>
              <input id="contact" name="contact" value="<?php echo $isFormError ? htmlspecialchars($_POST['contact'] ?? '', ENT_QUOTES) : ''; ?>" />
            </div>
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" value="<?php echo $isFormError ? htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES) : ''; ?>" />
            </div>
            <div>
              <label for="phone">Phone</label>
              <input id="phone" name="phone" inputmode="numeric" pattern="[0-9]{10}" placeholder="Phone number" required value="<?php echo $isFormError ? htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES) : ''; ?>" />
              <small class="field-help">Format: 10-digit US number (e.g. 5551234567)</small>
            </div>
            <div>
              <label for="status">Status</label>
              <input id="status" name="status" value="<?php echo $isFormError ? htmlspecialchars($_POST['status'] ?? 'active', ENT_QUOTES) : 'active'; ?>" />
            </div>
            <div>
              <label for="limit_month">Purchase limit per month</label>
              <input id="limit_month" name="limit_month" type="number" step="0.01" min="0" data-limit="1" value="<?php echo $isFormError ? htmlspecialchars($_POST['limit_month'] ?? '', ENT_QUOTES) : ''; ?>" />
              <small class="field-help">Dollar amount (USD)</small>
            </div>
            <label class="checkbox-row span-2" for="limit_none">
              <input id="limit_none" name="limit_none" type="checkbox" value="1" <?php echo $isFormError && !empty($_POST['limit_none']) ? 'checked' : ''; ?> />
              No purchase limit
            </label>
            <label class="checkbox-row span-2" for="auto_approve">
              <input id="auto_approve" name="auto_approve" type="checkbox" value="1" <?php echo $isFormError ? (!empty($_POST['auto_approve']) ? 'checked' : '') : 'checked'; ?> />
              Auto Approve
              <button class="help-icon" type="button" data-help-target="auto-approve-help" aria-expanded="false" aria-label="Auto approve help">?</button>
            </label>
            <small class="field-help span-2">When enabled, vendor orders are approved automatically without manual review.</small>
            <div id="auto-approve-help" class="help-text span-2" hidden><?php echo htmlspecialchars($autoApproveHelpText, ENT_QUOTES); ?></div>
            <div class="span-2">
              <label for="payment_method_id">Payment method</label>
              <?php if ($paymentMethods): ?>
                <select id="payment_method_id" name="payment_method_id">
                  <?php echo render_payment_method_options($paymentMethods, $isFormError ? ($_POST['payment_method_id'] ?? null) : null); ?>
                </select>
              <?php else: ?>
                <div class="text-danger">No payment methods found</div>
              <?php endif; ?>
            </div>
            <label class="checkbox-row span-2" for="sms_consent">
              <input id="sms_consent" name="sms_consent" type="checkbox" value="1" <?php echo $isFormError && !empty($_POST['sms_consent']) ? 'checked' : ''; ?> />
              I confirm I have obtained my vendor's consent to receive SMS messages from OilPatchDepot.
            </label>
            <label class="checkbox-row span-2" for="terms_agree">
              <input id="terms_agree" name="terms_agree" type="checkbox" value="1" required <?php echo $isFormError && !empty($_POST['terms_agree']) ? 'checked' : ''; ?> />
              Agree to <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?> Terms
            </label>
            <div class="span-2 form-actions">
              <button class="btn" type="submit" name="action" value="invite">Send Invite</button>
            </div>
          </form>
          </div>
        </section>

        <section class="panel">
          <h2>Vendor list</h2>
          <?php if (!$primaryVendors): ?>
            <div class="notice">No vendors saved.</div>
          <?php else: ?>
            <div class="table-wrap">
            <table class="table vendor-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Per Month</th>
                  <th>No Limit</th>
                  <th>Auto Approve</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($primaryVendors as $vendor): ?>
                  <?php $status = strtolower(trim((string) ($vendor['status'] ?? ''))); ?>
                  <?php
                    $displayName = trim((string) ($vendor['contact'] ?? ''));
                    if ($displayName === '') {
                        $displayName = trim((string) ($vendor['linkedName'] ?? ''));
                    }
                    if ($displayName === '') {
                        $displayName = trim((string) ($vendor['name'] ?? ''));
                    }
                    $displayEmail = trim((string) ($vendor['email'] ?? ''));
                    if ($displayEmail === '') {
                        $displayEmail = trim((string) ($vendor['linkedEmail'] ?? ''));
                    }
                    $displayCompany = trim((string) ($vendor['linkedCompanyName'] ?? ''));
                    if ($displayCompany === '') {
                        $displayCompany = trim((string) ($vendor['name'] ?? ''));
                    }
                    $displayPhone = trim((string) ($vendor['linkedCellPhone'] ?? ''));
                    if ($displayPhone === '') {
                        $displayPhone = trim((string) ($vendor['phone'] ?? ''));
                    }
                    $autoApproveValue = $vendor['autoApprove'] ?? null;
                    $autoApproveChecked = $autoApproveValue === null || $autoApproveValue === '' || (int) $autoApproveValue === 1;
                    $autoApproveHelpId = 'auto-approve-help-' . (string) ($vendor['id'] ?? '');
                  ?>
                  <?php $formId = 'vendor-form-' . (string) ($vendor['id'] ?? ''); ?>
                  <tr class="vendor-main-row">
                    <td>
                      <form id="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>" method="POST" class="vendor-form">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>" />
                      </form>
                      <div class="vendor-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES); ?></div>
                    </td>
                    <td>
                      <label class="vendor-field-label" for="limit_month_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>">Per month</label>
                      <input
                        form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>"
                        id="limit_month_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>"
                        name="limit_month"
                        type="number"
                        step="0.01"
                        min="0"
                        value="<?php echo htmlspecialchars((string) ($vendor['purchaseLimitMonth'] ?? ''), ENT_QUOTES); ?>"
                      />
                      <small class="field-help">Dollar amount (USD)</small>
                    </td>
                    <td>
                      <label class="limit-toggle" for="limit_none_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>">
                        <input
                          form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>"
                          id="limit_none_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>"
                          name="limit_none"
                          type="checkbox"
                          value="1"
                          <?php echo !empty($vendor['limitNone']) ? 'checked' : ''; ?>
                        />
                        No purchase limit
                      </label>
                    </td>
                    <td class="auto-approve-cell">
                      <label class="limit-toggle" for="auto_approve_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>">
                        <input
                          form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>"
                          id="auto_approve_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>"
                          name="auto_approve"
                          type="checkbox"
                          value="1"
                          <?php echo $autoApproveChecked ? 'checked' : ''; ?>
                        />
                        Auto Approve
                        <button class="help-icon" type="button" data-help-target="<?php echo htmlspecialchars($autoApproveHelpId, ENT_QUOTES); ?>" aria-expanded="false" aria-label="Auto approve help">?</button>
                      </label>
                      <div id="<?php echo htmlspecialchars($autoApproveHelpId, ENT_QUOTES); ?>" class="help-text" hidden><?php echo htmlspecialchars($autoApproveHelpText, ENT_QUOTES); ?></div>
                    </td>
                    <td>
                      <div class="table-action-buttons">
                        <?php if ($status === 'pending'): ?>
                          <button class="btn" form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>" type="submit" name="action" value="accept">Accept</button>
                          <button class="btn-outline" form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>" type="submit" name="action" value="decline" onclick="return confirm('Are you sure you want to decline this vendor? This cannot be undone.')">Decline</button>
                        <?php else: ?>
                          <button class="btn" form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>" type="submit" name="action" value="update">Update</button>
                          <button class="btn-outline" form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>" type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to remove this vendor? This cannot be undone.')">Remove</button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                  <tr class="vendor-sub-row">
                    <td colspan="5">
                      <div class="vendor-sub-grid">
                        <div class="vendor-sub-left">
                          <span class="vendor-sub-label">Email</span>
                          <span class="vendor-sub-value"><?php echo htmlspecialchars($displayEmail, ENT_QUOTES); ?></span>
                        </div>
                        <div class="vendor-sub-right">
                          <div>
                            <span class="vendor-sub-label">Company</span>
                            <span class="vendor-sub-value"><?php echo htmlspecialchars($displayCompany, ENT_QUOTES); ?></span>
                          </div>
                          <div>
                            <span class="vendor-sub-label">Cell Phone</span>
                            <span class="vendor-sub-value"><?php echo htmlspecialchars($displayPhone, ENT_QUOTES); ?></span>
                          </div>
                          <div>
                            <span class="vendor-sub-label">Status</span>
                            <span class="vendor-sub-value"><?php echo htmlspecialchars($vendor['status'] ?? '', ENT_QUOTES); ?></span>
                          </div>
                          <div>
                            <span class="vendor-sub-label">Payment Method</span>
                            <?php if ($paymentMethods): ?>
                              <select
                                form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>"
                                id="payment_method_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>"
                                name="payment_method_id"
                                class="vendor-sub-select"
                              >
                                <?php echo render_payment_method_options($paymentMethods, $vendor['paymentMethodId'] ?? null); ?>
                              </select>
                            <?php else: ?>
                              <div class="text-danger">No payment methods found</div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          <?php endif; ?>
        </section>

        <?php if ($declinedVendors): ?>
          <section class="panel">
            <h2>Declined Vendors (<?php echo count($declinedVendors); ?>)</h2>
            <div class="table-wrap">
            <table class="table vendor-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Per Month</th>
                  <th>No Limit</th>
                  <th>Auto Approve</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($declinedVendors as $vendor): ?>
                  <?php
                    $displayName = trim((string) ($vendor['contact'] ?? ''));
                    if ($displayName === '') {
                        $displayName = trim((string) ($vendor['linkedName'] ?? ''));
                    }
                    if ($displayName === '') {
                        $displayName = trim((string) ($vendor['name'] ?? ''));
                    }
                    $displayEmail = trim((string) ($vendor['email'] ?? ''));
                    if ($displayEmail === '') {
                        $displayEmail = trim((string) ($vendor['linkedEmail'] ?? ''));
                    }
                    $displayCompany = trim((string) ($vendor['linkedCompanyName'] ?? ''));
                    if ($displayCompany === '') {
                        $displayCompany = trim((string) ($vendor['name'] ?? ''));
                    }
                    $displayPhone = trim((string) ($vendor['linkedCellPhone'] ?? ''));
                    if ($displayPhone === '') {
                        $displayPhone = trim((string) ($vendor['phone'] ?? ''));
                    }
                    $autoApproveValue = $vendor['autoApprove'] ?? null;
                    $autoApproveChecked = $autoApproveValue === null || $autoApproveValue === '' || (int) $autoApproveValue === 1;
                    $autoApproveHelpId = 'auto-approve-help-declined-' . (string) ($vendor['id'] ?? '');
                  ?>
                  <?php $formId = 'vendor-declined-form-' . (string) ($vendor['id'] ?? ''); ?>
                  <tr class="vendor-main-row">
                    <td>
                      <form id="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>" method="POST" class="vendor-form">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>" />
                      </form>
                      <div class="vendor-name"><?php echo htmlspecialchars($displayName, ENT_QUOTES); ?></div>
                    </td>
                    <td>
                      <label class="vendor-field-label" for="limit_month_declined_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>">Per month</label>
                      <input
                        form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>"
                        id="limit_month_declined_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>"
                        name="limit_month"
                        type="number"
                        step="0.01"
                        min="0"
                        value="<?php echo htmlspecialchars((string) ($vendor['purchaseLimitMonth'] ?? ''), ENT_QUOTES); ?>"
                      />
                      <small class="field-help">Dollar amount (USD)</small>
                    </td>
                    <td>
                      <label class="limit-toggle" for="limit_none_declined_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>">
                        <input
                          form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>"
                          id="limit_none_declined_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>"
                          name="limit_none"
                          type="checkbox"
                          value="1"
                          <?php echo !empty($vendor['limitNone']) ? 'checked' : ''; ?>
                        />
                        No purchase limit
                      </label>
                    </td>
                    <td class="auto-approve-cell">
                      <label class="limit-toggle" for="auto_approve_declined_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>">
                        <input
                          form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>"
                          id="auto_approve_declined_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>"
                          name="auto_approve"
                          type="checkbox"
                          value="1"
                          <?php echo $autoApproveChecked ? 'checked' : ''; ?>
                        />
                        Auto Approve
                        <button class="help-icon" type="button" data-help-target="<?php echo htmlspecialchars($autoApproveHelpId, ENT_QUOTES); ?>" aria-expanded="false" aria-label="Auto approve help">?</button>
                      </label>
                      <div id="<?php echo htmlspecialchars($autoApproveHelpId, ENT_QUOTES); ?>" class="help-text" hidden><?php echo htmlspecialchars($autoApproveHelpText, ENT_QUOTES); ?></div>
                    </td>
                    <td>
                      <div class="table-action-buttons">
                        <button class="btn" form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>" type="submit" name="action" value="accept">Accept</button>
                      </div>
                    </td>
                  </tr>
                  <tr class="vendor-sub-row">
                    <td colspan="5">
                      <div class="vendor-sub-grid">
                        <div class="vendor-sub-left">
                          <span class="vendor-sub-label">Email</span>
                          <span class="vendor-sub-value"><?php echo htmlspecialchars($displayEmail, ENT_QUOTES); ?></span>
                        </div>
                        <div class="vendor-sub-right">
                          <div>
                            <span class="vendor-sub-label">Company</span>
                            <span class="vendor-sub-value"><?php echo htmlspecialchars($displayCompany, ENT_QUOTES); ?></span>
                          </div>
                          <div>
                            <span class="vendor-sub-label">Cell Phone</span>
                            <span class="vendor-sub-value"><?php echo htmlspecialchars($displayPhone, ENT_QUOTES); ?></span>
                          </div>
                          <div>
                            <span class="vendor-sub-label">Status</span>
                            <span class="vendor-sub-value"><?php echo htmlspecialchars($vendor['status'] ?? '', ENT_QUOTES); ?></span>
                          </div>
                          <div>
                            <span class="vendor-sub-label">Payment Method</span>
                            <?php if ($paymentMethods): ?>
                              <select
                                form="<?php echo htmlspecialchars($formId, ENT_QUOTES); ?>"
                                id="payment_method_declined_<?php echo htmlspecialchars($vendor['id'], ENT_QUOTES); ?>"
                                name="payment_method_id"
                                class="vendor-sub-select"
                              >
                                <?php echo render_payment_method_options($paymentMethods, $vendor['paymentMethodId'] ?? null); ?>
                              </select>
                            <?php else: ?>
                              <div class="text-danger">No payment methods found</div>
                            <?php endif; ?>
                          </div>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          </section>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
  <script>
    const vendorAddToggle = document.querySelector('.vendor-add-toggle');
    const vendorAddForm = document.getElementById('vendor-add-form');
    if (vendorAddToggle && vendorAddForm) {
      vendorAddToggle.addEventListener('click', function () {
        const isOpen = vendorAddForm.classList.toggle('is-open');
        vendorAddToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      });
    }
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
    const helpButtons = Array.from(document.querySelectorAll('[data-help-target]'));
    helpButtons.forEach((button) => {
      const targetId = button.getAttribute('data-help-target');
      if (!targetId) return;
      const target = document.getElementById(targetId);
      if (!target) return;
      button.addEventListener('mouseenter', () => {
        target.removeAttribute('hidden');
        button.classList.add('is-open');
        button.setAttribute('aria-expanded', 'true');
      });
    });
    document.addEventListener('click', (e) => {
      helpButtons.forEach((button) => {
        const targetId = button.getAttribute('data-help-target');
        if (!targetId) return;
        const target = document.getElementById(targetId);
        if (!target || target.hasAttribute('hidden')) return;
        if (!button.contains(e.target) && !target.contains(e.target)) {
          target.setAttribute('hidden', '');
          button.classList.remove('is-open');
          button.setAttribute('aria-expanded', 'false');
        }
      });
    });
  </script>
</body>
</html>
