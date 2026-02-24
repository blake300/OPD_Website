<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

$user = site_require_auth();
$pdo = opd_db();
$message = '';
$messageClass = 'notice';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    site_require_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create' || $action === 'invite') {
        $clientEmail = trim((string) ($_POST['email'] ?? ''));
        $matchedUser = $clientEmail !== '' ? site_find_user_by_email($clientEmail) : null;
        $linkedUserId = '';
        $clientStatus = $_POST['status'] ?? 'active';
        if ($matchedUser && ($matchedUser['id'] ?? '') !== $user['id']) {
            $linkedUserId = (string) $matchedUser['id'];
            $clientStatus = 'requested';
        }

        $phoneRaw = trim((string) ($_POST['phone'] ?? ''));
        $normalizedPhone = $phoneRaw !== '' ? opd_normalize_us_phone($phoneRaw) : null;
        $smsConsent = !empty($_POST['sms_consent']);
        $termsAgree = !empty($_POST['terms_agree']);

        if ($normalizedPhone === null) {
            $message = 'Client phone must be a valid 10-digit US number.';
            $messageClass = 'notice is-error';
        } elseif (!$termsAgree) {
            $message = 'You must agree to the Oil Patch Depot terms.';
            $messageClass = 'notice is-error';
        } elseif ($action === 'invite' && !$smsConsent) {
            $message = 'SMS consent is required to send an invite.';
            $messageClass = 'notice is-error';
        } else {
            if ($linkedUserId === '' || !site_client_exists_for_user_linked($user['id'], $linkedUserId, $clientEmail)) {
                site_simple_create('clients', $user['id'], [
                    'name' => $_POST['name'] ?? '',
                    'email' => $clientEmail,
                    'linkedUserId' => $linkedUserId !== '' ? $linkedUserId : null,
                    'phone' => $normalizedPhone,
                    'status' => $clientStatus,
                    'notes' => $_POST['notes'] ?? ''
                ]);
            }
            if ($linkedUserId !== '') {
            $vendorName = trim((string) ($user['name'] ?? ''));
            $vendorContact = $vendorName;
            $vendorEmail = trim((string) ($user['email'] ?? ''));
            $vendorPhone = '';
            $profileStmt = $pdo->prepare('SELECT name, email, companyName, cellPhone FROM users WHERE id = ? LIMIT 1');
            $profileStmt->execute([$user['id']]);
            $profile = $profileStmt->fetch();
            if ($profile) {
                $companyName = trim((string) ($profile['companyName'] ?? ''));
                $userName = trim((string) ($profile['name'] ?? ''));
                if ($companyName !== '') {
                    $vendorName = $companyName;
                } elseif ($userName !== '') {
                    $vendorName = $userName;
                }
                if ($userName !== '') {
                    $vendorContact = $userName;
                }
                $email = trim((string) ($profile['email'] ?? ''));
                if ($email !== '') {
                    $vendorEmail = $email;
                }
                $vendorPhone = trim((string) ($profile['cellPhone'] ?? ''));
            }

                if (!site_vendor_exists_for_user_linked($linkedUserId, $user['id'], $user['email'] ?? '')) {
                    site_simple_create('vendors', $linkedUserId, [
                        'name' => $vendorName,
                        'contact' => $vendorContact,
                        'email' => $vendorEmail,
                        'linkedUserId' => $user['id'],
                        'phone' => $vendorPhone,
                        'status' => 'pending',
                        'purchaseLimitOrder' => null,
                        'purchaseLimitDay' => null,
                        'purchaseLimitMonth' => null,
                        'limitNone' => 0,
                        'paymentMethodId' => null,
                        'smsConsent' => 0
                    ]);
                }
            }
            if ($action === 'invite') {
                $template = site_get_setting_value('client_invite_sms');
                $baseUrl = site_get_base_url();
                if ($template === null || trim($template) === '') {
                    $message = 'Client added, but no client invite SMS template is set in System Settings.';
                    $messageClass = 'notice is-error';
                } elseif ($baseUrl === '') {
                    $message = 'Client added, but invite link could not be generated.';
                    $messageClass = 'notice is-error';
                } else {
                    $profileStmt = $pdo->prepare('SELECT name, companyName FROM users WHERE id = ? LIMIT 1');
                    $profileStmt->execute([$user['id']]);
                    $profileRow = $profileStmt->fetch() ?: [];
                    $clientName = trim((string) ($_POST['name'] ?? ''));
                    $recipientName = $clientName !== '' ? $clientName : 'there';
                    $context = [
                        'inviter' => $profileRow['name'] ?? $user['name'] ?? '',
                        'company' => $profileRow['companyName'] ?? $user['name'] ?? '',
                        'recipient' => $recipientName,
                    ];
                    $link = $baseUrl . '/dashboard-vendors.php';
                    $smsText = site_build_invite_message($template, $link, $context);
                    $rateKey = 'client_invite:' . ($user['id'] ?? 'user') . ':' . $normalizedPhone;
                    $sendResult = site_send_invite_sms($normalizedPhone, $smsText, $rateKey);
                    if ($sendResult['ok']) {
                        $message = 'Client added. Invite sent.';
                    } else {
                        $message = 'Client added, but invite failed: ' . ($sendResult['error'] ?? 'SMS failed.');
                        $messageClass = 'notice is-error';
                    }
                }
            } else {
                $message = 'Client added.';
            }
        }
    }
    if ($action === 'update') {
        $clientId = $_POST['id'] ?? '';
        if (is_string($clientId) && $clientId !== '') {
            $updateName = trim((string) ($_POST['name'] ?? ''));
            $updateEmail = trim((string) ($_POST['email'] ?? ''));
            $updatePhone = trim((string) ($_POST['phone'] ?? ''));
            $normalizedPhone = $updatePhone !== '' ? opd_normalize_us_phone($updatePhone) : null;
            $updateNotes = trim((string) ($_POST['notes'] ?? ''));

            if ($normalizedPhone === null && $updatePhone !== '') {
                $message = 'Phone must be a valid 10-digit US number.';
                $messageClass = 'notice is-error';
            } else {
                $stmt = $pdo->prepare(
                    'UPDATE clients SET name = ?, email = ?, phone = ?, notes = ?, updatedAt = ? WHERE id = ? AND userId = ?'
                );
                $stmt->execute([
                    $updateName,
                    $updateEmail,
                    $normalizedPhone ?? '',
                    $updateNotes,
                    gmdate('Y-m-d H:i:s'),
                    $clientId,
                    $user['id']
                ]);
                $message = 'Client updated.';
            }
        }
    }
    if ($action === 'delete') {
        site_simple_delete('clients', $_POST['id'] ?? '');
        $message = 'Client removed.';
    }
    if ($action === 'accept' || $action === 'decline') {
        $clientId = $_POST['id'] ?? '';
        if (is_string($clientId) && $clientId !== '') {
            $status = $action === 'accept' ? 'active' : 'declined';
            $stmt = $pdo->prepare('UPDATE clients SET status = ?, updatedAt = ? WHERE id = ? AND userId = ?');
            $stmt->execute([$status, gmdate('Y-m-d H:i:s'), $clientId, $user['id']]);

            $client = site_get_client_record($user['id'], $clientId);
            if ($client) {
                $inviterId = trim((string) ($client['linkedUserId'] ?? ''));
                if ($inviterId === '' && !empty($client['email'])) {
                    $inviter = site_find_user_by_email((string) $client['email']);
                    $inviterId = $inviter['id'] ?? '';
                }
                if ($inviterId !== '') {
                    $updateVendor = $pdo->prepare(
                        'UPDATE vendors SET status = ?, updatedAt = ? WHERE userId = ? AND (linkedUserId = ? OR LOWER(email) = LOWER(?))'
                    );
                    $updateVendor->execute([
                        $status,
                        gmdate('Y-m-d H:i:s'),
                        $inviterId,
                        $user['id'],
                        $user['email'] ?? ''
                    ]);
                }
            }

            $message = $action === 'accept' ? 'Client accepted.' : 'Client declined.';
        }
    }
}

$clientStmt = $pdo->prepare(
    'SELECT c.*, u.name AS linkedName, u.email AS linkedEmail, u.companyName AS linkedCompanyName, u.cellPhone AS linkedCellPhone
     FROM clients c
     LEFT JOIN users u ON c.linkedUserId = u.id
     WHERE c.userId = ?
     ORDER BY c.updatedAt DESC'
);
$clientStmt->execute([$user['id']]);
$clients = $clientStmt->fetchAll();
$pendingClients = [];
$activeClients = [];
$declinedClients = [];
foreach ($clients as $client) {
    $status = strtolower(trim((string) ($client['status'] ?? '')));
    if ($status === 'declined') {
        $declinedClients[] = $client;
    } elseif ($status === 'pending') {
        $pendingClients[] = $client;
    } else {
        $activeClients[] = $client;
    }
}
$primaryClients = array_merge($pendingClients, $activeClients);
$csrf = site_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Clients - Oil Patch Depot</title>
  <link rel="stylesheet" href="/assets/css/site.css" />
  <style>
    .client-email-col { word-break: break-word; max-width: 200px; min-width: 100px; }
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page dashboard">
    <div class="dashboard-layout">
      <?php require __DIR__ . '/partials/dashboard-nav.php'; ?>

      <div class="dashboard-content">
        <section class="panel">
          <h2>Clients</h2>
          <?php if ($message): ?>
            <div class="<?php echo htmlspecialchars($messageClass, ENT_QUOTES); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
          <?php endif; ?>
          <form method="POST" class="form-grid cols-2">
            <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
            <div>
              <label for="name">Client name</label>
              <input id="name" name="name" required />
            </div>
            <div>
              <label for="email">Email</label>
              <input id="email" name="email" type="email" />
            </div>
            <div>
              <label for="phone">Phone</label>
              <input id="phone" name="phone" inputmode="numeric" pattern="[0-9]{10}" placeholder="Phone number" required />
              <small class="field-help">Format: 10-digit US number</small>
            </div>
            <div>
              <label for="status">Status</label>
              <input id="status" name="status" value="active" />
            </div>
            <label class="checkbox-row span-2" for="sms_consent">
              <input id="sms_consent" name="sms_consent" type="checkbox" value="1" />
              I confirm I have obtained my client's consent to receive SMS messages from OilPatchDepot.
            </label>
            <label class="checkbox-row span-2" for="terms_agree">
              <input id="terms_agree" name="terms_agree" type="checkbox" value="1" required />
              Agree to Oil Patch Depot Terms
            </label>
            <div style="grid-column: 1 / -1;">
              <label for="notes">Notes</label>
              <textarea id="notes" name="notes"></textarea>
            </div>
            <div class="form-actions span-2">
              <button class="btn" type="submit" name="action" value="create">Add client</button>
              <button class="btn-outline" type="submit" name="action" value="invite">Send Invite</button>
            </div>
          </form>
        </section>

        <section class="panel">
          <h2>Client list</h2>
          <?php if (!$primaryClients): ?>
            <div class="notice">No clients yet. Add a client above to enable client billing on orders.</div>
          <?php else: ?>
            <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th></th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Company</th>
                  <th>Cell Phone</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($primaryClients as $client): ?>
                  <?php $status = strtolower(trim((string) ($client['status'] ?? ''))); ?>
                  <?php
                    $displayName = trim((string) ($client['linkedName'] ?? ''));
                    if ($displayName === '') {
                        $displayName = trim((string) ($client['name'] ?? ''));
                    }
                    $displayEmail = trim((string) ($client['email'] ?? ''));
                    if ($displayEmail === '') {
                        $displayEmail = trim((string) ($client['linkedEmail'] ?? ''));
                    }
                    $displayCompany = trim((string) ($client['linkedCompanyName'] ?? ''));
                    if ($displayCompany === '') {
                        $displayCompany = trim((string) ($client['name'] ?? ''));
                    }
                    $displayPhone = trim((string) ($client['linkedCellPhone'] ?? ''));
                    if ($displayPhone === '') {
                        $displayPhone = trim((string) ($client['phone'] ?? ''));
                    }
                    $editName = trim((string) ($client['name'] ?? ''));
                    $editEmail = trim((string) ($client['email'] ?? ''));
                    $editPhone = trim((string) ($client['phone'] ?? ''));
                    $editNotes = trim((string) ($client['notes'] ?? ''));
                    $rowId = htmlspecialchars($client['id'], ENT_QUOTES);
                  ?>
                  <tr class="client-view-row" data-client-id="<?php echo $rowId; ?>">
                    <td>
                      <?php if ($status === 'pending'): ?>
                        <form method="POST" class="table-actions">
                          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                          <input type="hidden" name="id" value="<?php echo $rowId; ?>" />
                          <button class="btn" type="submit" name="action" value="accept">Accept</button>
                          <button class="btn-outline" type="submit" name="action" value="decline" onclick="return confirm('Are you sure you want to decline this client? This cannot be undone.')">Decline</button>
                        </form>
                      <?php else: ?>
                        <button class="btn-outline" type="button" data-edit-client="<?php echo $rowId; ?>">Edit</button>
                      <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($displayName, ENT_QUOTES); ?></td>
                    <td class="client-email-col"><?php echo htmlspecialchars($displayEmail, ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($displayCompany, ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($displayPhone, ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($client['status'] ?? '', ENT_QUOTES); ?></td>
                    <td>
                      <?php if ($status !== 'pending'): ?>
                        <form method="POST" class="table-actions">
                          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                          <input type="hidden" name="id" value="<?php echo $rowId; ?>" />
                          <button class="btn-outline" type="submit" name="action" value="delete" onclick="return confirm('Are you sure you want to remove this client? This cannot be undone.')">Remove</button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php if ($status !== 'pending'): ?>
                  <tr class="client-edit-row" data-client-id="<?php echo $rowId; ?>" hidden>
                    <td colspan="7">
                      <form method="POST" class="form-grid cols-2 client-edit-form">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="id" value="<?php echo $rowId; ?>" />
                        <div>
                          <label for="edit_name_<?php echo $rowId; ?>">Name</label>
                          <input id="edit_name_<?php echo $rowId; ?>" name="name" value="<?php echo htmlspecialchars($editName, ENT_QUOTES); ?>" required />
                        </div>
                        <div>
                          <label for="edit_email_<?php echo $rowId; ?>">Email</label>
                          <input id="edit_email_<?php echo $rowId; ?>" name="email" type="email" value="<?php echo htmlspecialchars($editEmail, ENT_QUOTES); ?>" />
                        </div>
                        <div>
                          <label for="edit_phone_<?php echo $rowId; ?>">Phone</label>
                          <input id="edit_phone_<?php echo $rowId; ?>" name="phone" inputmode="numeric" pattern="[0-9]{10}" placeholder="Phone number" value="<?php echo htmlspecialchars($editPhone, ENT_QUOTES); ?>" />
                        </div>
                        <div>
                          <label for="edit_notes_<?php echo $rowId; ?>">Notes</label>
                          <input id="edit_notes_<?php echo $rowId; ?>" name="notes" value="<?php echo htmlspecialchars($editNotes, ENT_QUOTES); ?>" />
                        </div>
                        <div class="form-actions span-2">
                          <button class="btn" type="submit" name="action" value="update">Save</button>
                          <button class="btn-outline" type="button" data-cancel-edit="<?php echo $rowId; ?>">Cancel</button>
                        </div>
                      </form>
                    </td>
                  </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
          <?php endif; ?>
        </section>

        <?php if ($declinedClients): ?>
          <section class="panel">
            <h2>Declined Clients</h2>
            <div class="table-wrap">
            <table class="table">
              <thead>
                <tr>
                  <th></th>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Company</th>
                  <th>Cell Phone</th>
                  <th>Status</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($declinedClients as $client): ?>
                  <?php
                    $displayName = trim((string) ($client['linkedName'] ?? ''));
                    if ($displayName === '') {
                        $displayName = trim((string) ($client['name'] ?? ''));
                    }
                    $displayEmail = trim((string) ($client['email'] ?? ''));
                    if ($displayEmail === '') {
                        $displayEmail = trim((string) ($client['linkedEmail'] ?? ''));
                    }
                    $displayCompany = trim((string) ($client['linkedCompanyName'] ?? ''));
                    if ($displayCompany === '') {
                        $displayCompany = trim((string) ($client['name'] ?? ''));
                    }
                    $displayPhone = trim((string) ($client['linkedCellPhone'] ?? ''));
                    if ($displayPhone === '') {
                        $displayPhone = trim((string) ($client['phone'] ?? ''));
                    }
                  ?>
                  <tr>
                    <td>
                      <form method="POST" class="table-actions">
                        <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($client['id'], ENT_QUOTES); ?>" />
                        <button class="btn" type="submit" name="action" value="accept">Accept</button>
                      </form>
                    </td>
                    <td><?php echo htmlspecialchars($displayName, ENT_QUOTES); ?></td>
                    <td class="client-email-col"><?php echo htmlspecialchars($displayEmail, ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($displayCompany, ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($displayPhone, ENT_QUOTES); ?></td>
                    <td><?php echo htmlspecialchars($client['status'] ?? '', ENT_QUOTES); ?></td>
                    <td></td>
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
    document.querySelectorAll('[data-edit-client]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var id = btn.getAttribute('data-edit-client');
        var viewRow = document.querySelector('.client-view-row[data-client-id="' + id + '"]');
        var editRow = document.querySelector('.client-edit-row[data-client-id="' + id + '"]');
        if (viewRow) viewRow.setAttribute('hidden', '');
        if (editRow) editRow.removeAttribute('hidden');
      });
    });
    document.querySelectorAll('[data-cancel-edit]').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var id = btn.getAttribute('data-cancel-edit');
        var viewRow = document.querySelector('.client-view-row[data-client-id="' + id + '"]');
        var editRow = document.querySelector('.client-edit-row[data-client-id="' + id + '"]');
        if (editRow) editRow.setAttribute('hidden', '');
        if (viewRow) viewRow.removeAttribute('hidden');
      });
    });
  </script>
</body>
</html>
