<?php
declare(strict_types=1);

// Error handling for production
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Fatal error handler
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
        echo '<h1>Service Temporarily Unavailable</h1>';
        echo '<p>We are experiencing technical difficulties. Please try again later.</p>';
        echo '<p><a href="/">Return to homepage</a></p>';
        echo '</body></html>';
        exit;
    }
});

// Helper function to safely check cart accounting mismatch
function cart_accounting_mismatch(array $items, array $payload): bool
{
    try {
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
    } catch (Throwable $e) {
        error_log('cart_accounting_mismatch error: ' . $e->getMessage());
        return false;
    }
}

try {
    // Load required dependencies
    require_once __DIR__ . '/../src/store.php';
    require_once __DIR__ . '/../src/site_auth.php';
    require_once __DIR__ . '/../src/stripe_service.php';
    require_once __DIR__ . '/../src/tax_rates.php';

    // Check for guest mode or authenticated user
    $isGuestMode = isset($_GET['guest']) && $_GET['guest'] === '1';
    $user = site_current_user();

    // If not guest mode and not logged in, redirect to login
    if (!$isGuestMode && !$user) {
        header('Location: /login.php?redirect=' . urlencode('/checkout.php'));
        exit;
    }

    // Initialize variables with safe defaults
    $items = [];
    $subtotal = 0.0;
    $profile = [];
    $clientId = null;
    $clientRecord = null;
    $clientPaymentMethod = null;
    $clientPaymentMethodLabel = '';
    $clientPaymentMethodId = '';
    $clientUserId = '';
    $cartAccounting = null;
    $accountingPayload = null;
    $accountingPayloadJson = '';
    $message = '';
    $success = null;
    $stripePublishableKey = '';
    $stripeEnabled = false;
    $tax = 0.0;
    $taxRate = 0.0;
    $totalWithTax = 0.0;
    $shippingMethod = 'pickup';
    $shippingCost = 0.0;

    // Get cart items
    $items = site_cart_items();
    $subtotal = site_cart_total($items);
    $isServiceOnlyCart = site_cart_has_only_service_items($items);
    $hasAnyServiceItem = site_cart_has_any_service_items($items);
    $taxableSubtotal = site_cart_taxable_total($items);
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

    // Service products require sign-in — block guest checkout
    if ($hasAnyServiceItem && !$user) {
        header('Location: /login.php?redirect=' . urlencode('/checkout.php') . '&service=1');
        exit;
    }

    if ($isServiceOnlyCart) {
        $shippingCost = 0.0;
        $shippingMethod = 'service';
    }

    // Get user profile (only if logged in)
    $pdo = opd_db();
    if ($user) {
        $profileStmt = $pdo->prepare('SELECT address, city, state, zip, cellPhone FROM users WHERE id = ? LIMIT 1');
        $profileStmt->execute([$user['id']]);
        $profile = $profileStmt->fetch() ?: [];
    }

    // Set default country if not in profile
    if (!isset($profile['country'])) {
        $profile['country'] = 'USA';
    }

    // Load saved payment methods for all signed-in users
    $userPaymentMethods = [];
    if ($user) {
        $userPaymentMethods = site_get_payment_methods($user['id']);
    }

    // Service products require a saved payment method
    $requireSavedPayment = false;
    if ($user && $hasAnyServiceItem) {
        $requireSavedPayment = true;
    }

    // Get client ID from query string (only for logged-in users)
    $clientIdParam = $_GET['clientId'] ?? '';
    $clientId = ($user && is_string($clientIdParam) && $clientIdParam !== '') ? $clientIdParam : null;

    if ($user && !$clientId) {
        try {
            $cartId = site_get_cart_id($user['id']);
            if ($cartId) {
                $clientId = site_get_latest_cart_accounting_client_id($cartId);
            }
        } catch (Throwable $e) {
            error_log('Error inferring cart client: ' . $e->getMessage());
        }
    }

    // Load client record if provided (only for logged-in users)
    if ($user && $clientId) {
        try {
            $clientRecord = site_get_client_record($user['id'], $clientId);
            if (!$clientRecord || !site_client_is_billable($clientRecord)) {
                $clientId = null;
                $clientRecord = null;
            }
        } catch (Throwable $e) {
            error_log('Error loading client record: ' . $e->getMessage());
            $clientId = null;
            $clientRecord = null;
        }
    }

    // Load client user and payment method
    if ($clientRecord) {
        try {
            $linkedUserId = trim((string) ($clientRecord['linkedUserId'] ?? ''));

            if ($linkedUserId === '') {
                $email = trim((string) ($clientRecord['email'] ?? ''));
                if ($email !== '') {
                    $linkedUser = site_find_user_by_email($email);
                    if ($linkedUser) {
                        $linkedUserId = (string) ($linkedUser['id'] ?? '');
                    }
                }
            }

            if ($linkedUserId === '') {
                $check = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
                $check->execute([$clientRecord['id'] ?? '']);
                if ($check->fetch()) {
                    $linkedUserId = (string) ($clientRecord['id'] ?? '');
                }
            }

            if ($linkedUserId !== '') {
                $clientUserId = $linkedUserId;
                $vendorRecord = site_find_vendor_record_for_client(
                    $clientUserId,
                    (string) ($user['id'] ?? ''),
                    (string) ($user['email'] ?? '')
                );

                if ($vendorRecord) {
                    $status = strtolower(trim((string) ($vendorRecord['status'] ?? '')));
                    $paymentMethodId = trim((string) ($vendorRecord['paymentMethodId'] ?? ''));

                    if ($status === 'active' && $paymentMethodId !== '') {
                        $clientPaymentMethod = site_get_payment_method($clientUserId, $paymentMethodId);
                        if ($clientPaymentMethod) {
                            $clientPaymentMethodId = (string) ($clientPaymentMethod['id'] ?? '');
                            $clientPaymentMethodLabel = site_format_payment_method_label($clientPaymentMethod);
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('Error loading client payment method: ' . $e->getMessage());
        }
    }

    // Check if invoice billing is allowed — either on the current user or the client's linked user
    $allowInvoice = false;
    if ($user) {
        try {
            $invCheck = $pdo->prepare('SELECT allowInvoice FROM users WHERE id = ? LIMIT 1');
            $invCheck->execute([$user['id']]);
            $invRow = $invCheck->fetch();
            $allowInvoice = $invRow && !empty($invRow['allowInvoice']);
        } catch (Throwable $e) {
            $allowInvoice = false;
        }
    }
    if (!$allowInvoice && $clientUserId) {
        try {
            $invCheck = $pdo->prepare('SELECT allowInvoice FROM users WHERE id = ? LIMIT 1');
            $invCheck->execute([$clientUserId]);
            $invRow = $invCheck->fetch();
            $allowInvoice = $invRow && !empty($invRow['allowInvoice']);
        } catch (Throwable $e) {
            $allowInvoice = false;
        }
    }

    // Load cart accounting (only for logged-in users)
    if ($user) {
        try {
            $cartAccounting = site_get_cart_accounting_for_user($user['id'], $clientId);
            $accountingPayload = $cartAccounting ? [
                'clientId' => $clientId,
                'groups' => $cartAccounting['groups'] ?? [],
                'assignments' => $cartAccounting['assignments'] ?? [],
            ] : null;
            $accountingPayloadJson = $accountingPayload ? json_encode($accountingPayload) : '';
        } catch (Throwable $e) {
            error_log('Error loading cart accounting: ' . $e->getMessage());
        }
    }
    if ($user && $clientId && !$accountingPayload) {
        $accountingPayload = [
            'clientId' => $clientId,
            'groups' => [],
            'assignments' => [],
        ];
        $accountingPayloadJson = json_encode($accountingPayload);
    }

    // Show saved payment methods for signed-in users without a client selected
    $showUserSavedMethods = $user && !$clientId && !$requireSavedPayment && !empty($userPaymentMethods);

    // Get Stripe configuration
    try {
        $stripePublishableKey = stripe_publishable_key();
        $stripeEnabled = $stripePublishableKey !== '';
    } catch (Throwable $e) {
        error_log('Error loading Stripe config: ' . $e->getMessage());
        $stripeEnabled = false;
    }

    // Process POST request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            site_require_csrf();

            // Decode accounting payload
            if (!empty($_POST['accounting_payload'])) {
                $decoded = json_decode($_POST['accounting_payload'], true);
                if (is_array($decoded)) {
                    $accountingPayload = $decoded;
                    $accountingPayloadJson = $_POST['accounting_payload'];
                }
            }
            if ($user && $clientId && is_array($accountingPayload)) {
                if (empty($accountingPayload['clientId'])) {
                    $accountingPayload['clientId'] = $clientId;
                    $accountingPayloadJson = json_encode($accountingPayload);
                }
            }
            if ($user && $clientId && !$accountingPayload) {
                $accountingPayload = [
                    'clientId' => $clientId,
                    'groups' => [],
                    'assignments' => [],
                ];
                $accountingPayloadJson = json_encode($accountingPayload);
            }

            $paymentIntentId = trim((string) ($_POST['payment_intent_id'] ?? ''));

            // Calculate tax (service items are not taxed)
            $taxableSubtotal = site_cart_taxable_total($items);
            $taxData = opd_calculate_ok_sales_tax(
                $taxableSubtotal,
                (string) ($_POST['state'] ?? ''),
                (string) ($_POST['postal'] ?? '')
            );
            $tax = (float) ($taxData['tax'] ?? 0.0);
            $taxRate = (float) ($taxData['ratePercent'] ?? 0.0);
            $shippingMethodInput = trim((string) ($_POST['shipping_method'] ?? ''));
            $shippingMethod = $shippingMethodInput !== '' ? $shippingMethodInput : 'pickup';
            $deliveryZip = trim((string) ($_POST['delivery_zip'] ?? $_POST['postal'] ?? ''));
            if ($isServiceOnlyCart) {
                $shippingCost = 0.0;
                $shippingMethod = 'service';
            } elseif ($shippingMethod === 'same_day') {
                $deliveryResult = site_get_same_day_delivery_cost($deliveryZip, $items);
                if ($deliveryResult['error']) {
                    $message = $deliveryResult['error'];
                }
                $shippingCost = $deliveryResult['cost'];
            } elseif ($shippingMethod === 'standard') {
                $stdResult = site_calculate_standard_shipping(
                    (string) ($_POST['state'] ?? ''),
                    $items
                );
                if ($stdResult['error']) {
                    $message = $stdResult['error'];
                }
                $shippingCost = $stdResult['cost'];
            } else {
                $shippingCost = 0.0;
            }
            $totalWithTax = $subtotal + $tax + $shippingCost;
            $expectedAmountCents = (int) round($totalWithTax * 100);
            $intentData = null;
            $payByInvoice = !empty($_POST['payment_method_type']) && $_POST['payment_method_type'] === 'invoice';

            // Validate service items require saved payment method
            if ($hasAnyServiceItem && $user && !site_get_payment_methods($user['id'])) {
                $message = 'Service items require a saved payment method. Please add one in your account dashboard.';
            } elseif ($accountingPayload && cart_accounting_mismatch($items, $accountingPayload)) {
                $message = 'Group quantities must match item quantities before checkout.';
            } elseif ($payByInvoice && $allowInvoice) {
                // Invoice payment — skip Stripe entirely
                $intentData = null;
            } elseif ($stripeEnabled || $requireSavedPayment || $showUserSavedMethods) {
                // Validate payment intent
                if ($paymentIntentId === '') {
                    $message = 'Card payment is required to place this order.';
                } else {
                    $intent = stripe_retrieve_payment_intent($paymentIntentId);
                    if (!$intent['ok']) {
                        $message = $intent['error'] ?? 'Unable to verify payment.';
                    } else {
                        $intentData = $intent['data'] ?? [];
                        $status = (string) ($intentData['status'] ?? '');
                        $amount = (int) ($intentData['amount_received'] ?? $intentData['amount'] ?? 0);
                        $currency = strtolower((string) ($intentData['currency'] ?? ''));
                        $meta = is_array($intentData['metadata'] ?? null) ? $intentData['metadata'] : [];
                        $intentUser = (string) ($meta['userId'] ?? '');

                        // Verify payment intent belongs to this user (or is a valid guest)
                        $isValidGuestPayment = $isGuestMode && $intentUser === 'guest';
                        $isValidUserPayment = $user && $intentUser === (string) ($user['id'] ?? '');

                        if ($status !== 'succeeded') {
                            $message = 'Payment has not completed yet. Please try again.';
                        } elseif (abs($amount - $expectedAmountCents) > 1) {
                            $message = 'Payment amount mismatch. Please try again.';
                        } elseif ($currency !== 'usd') {
                            $message = 'Payment currency mismatch. Please try again.';
                        } elseif (!$isValidGuestPayment && !$isValidUserPayment) {
                            $message = 'Payment verification failed.';
                        }
                    }
                }
            }

            // Place order if validation passed
            if ($message === '') {
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
                    'shippingFirstName' => $_POST['shippingFirstName'] ?? '',
                    'shippingLastName' => $_POST['shippingLastName'] ?? '',
                    'shippingPhone' => $_POST['shippingPhone'] ?? '',
                    'shipping_method' => $shippingMethod,
                    'delivery_zip' => $deliveryZip,
                    'accounting' => $accountingPayload,
                    'clientId' => $clientId,
                    'guest' => $isGuestMode,
                    'paymentSucceeded' => ($intentData !== null || $payByInvoice),
                ]);

                if (!empty($result['error'])) {
                    $message = $result['error'];
                } else {
                    // Record payment if Stripe was used
                    if (($stripeEnabled || $showUserSavedMethods) && $intentData) {
                        try {
                            $now = gmdate('Y-m-d H:i:s');
                            $amount = ((int) ($intentData['amount_received'] ?? $intentData['amount'] ?? 0)) / 100;
                            $status = (string) ($intentData['status'] ?? 'succeeded');
                            $externalId = (string) ($intentData['id'] ?? '');

                            if ($externalId !== '') {
                                $check = $pdo->prepare('SELECT id FROM payments WHERE externalId = ? LIMIT 1');
                                $check->execute([$externalId]);
                                $existing = $check->fetch();

                                if ($existing) {
                                    $update = $pdo->prepare(
                                        'UPDATE payments SET orderId = ?, amount = ?, status = ?, capturedAt = ?, updatedAt = ? WHERE id = ?'
                                    );
                                    $update->execute([
                                        $result['orderId'] ?? null,
                                        $amount,
                                        $status,
                                        $now,
                                        $now,
                                        $existing['id'],
                                    ]);
                                } else {
                                    $insert = $pdo->prepare(
                                        'INSERT INTO payments (id, orderId, method, externalId, amount, status, capturedAt, updatedAt)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
                                    );
                                    $insert->execute([
                                        opd_generate_id('pay'),
                                        $result['orderId'] ?? null,
                                        'stripe',
                                        $externalId,
                                        $amount,
                                        $status,
                                        $now,
                                        $now,
                                    ]);
                                }
                            }

                            if (!empty($result['orderId'])) {
                                $updateOrder = $pdo->prepare('UPDATE orders SET paymentStatus = ?, updatedAt = ? WHERE id = ?');
                                $updateOrder->execute(['paid', $now, $result['orderId']]);
                            }
                        } catch (Throwable $e) {
                            error_log('Error recording payment: ' . $e->getMessage());
                        }
                    }

                    // Handle invoice payment
                    if ($payByInvoice && $allowInvoice && !empty($result['orderId'])) {
                        try {
                            require_once __DIR__ . '/../src/invoice_service.php';
                            $now = gmdate('Y-m-d H:i:s');
                            // Create invoice record
                            $invoiceUserId = $clientUserId !== '' ? $clientUserId : (string) ($user['id'] ?? '');
                            $invResult = opd_create_invoice($result['orderId'], $invoiceUserId, $totalWithTax, 30);
                            $pdo->prepare('UPDATE orders SET paymentStatus = ?, updatedAt = ? WHERE id = ?')
                                ->execute(['Invoice Pending', $now, $result['orderId']]);
                            opd_generate_invoice_pdf($invResult['id']);
                            if (!opd_email_invoice($invResult['id'])) {
                                error_log('Invoice email not sent for order ' . $result['orderId'] . ' invoice ' . $invResult['id']);
                                $message = 'Order placed, but we could not email your invoice automatically. Please contact support if you do not receive it shortly.';
                            }
                        } catch (Throwable $e) {
                            error_log('Invoice creation error for order ' . $result['orderId'] . ': ' . $e->getMessage());
                            $message = 'Order placed, but we could not finish processing your invoice automatically. Please contact support if you do not receive it shortly.';
                        }
                    }

                    $success = $result;
                }
            }
        } catch (Throwable $e) {
            error_log('Checkout POST error: ' . $e->getMessage());
            $message = 'An error occurred during checkout. Please try again.';
        }
    }

    // Get CSRF token
    $csrf = site_csrf_token();

} catch (Throwable $e) {
    // Log error and show user-friendly message
    error_log('Checkout fatal error: ' . $e->getMessage());
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><title>Error</title></head><body>';
    echo '<h1>Service Unavailable</h1>';
    echo '<p>We are experiencing technical difficulties with checkout. Please try again later.</p>';
    echo '<p><a href="/">Return to homepage</a></p>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Checkout - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <link rel="stylesheet" href="/assets/css/site.css?v=20260315c" />
  <?php if ($stripeEnabled): ?>
    <script src="https://js.stripe.com/v3/"></script>
  <?php endif; ?>
  <style>
    .card-field {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .card-grid {
      display: grid;
      grid-template-columns: minmax(0, 2fr) minmax(0, 1fr) minmax(0, 1fr);
      gap: 12px;
    }
    .card-slot {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .card-label {
      font-size: 0.85rem;
      color: #666;
    }
    .card-error {
      color: #b42318;
      font-size: 0.9rem;
    }
    .saved-payment-method {
      border: 1px solid #e2e2e2;
      border-radius: 8px;
      background: #fff9f0;
      padding: 12px;
      display: flex;
      flex-direction: column;
      gap: 6px;
      font-size: 0.95rem;
    }
    .checkout-summary {
      display: flex;
      flex-direction: column;
      gap: 6px;
      min-width: 220px;
    }
    .checkout-summary .summary-line {
      font-size: 0.95rem;
    }
    .checkout-summary .summary-total {
      font-size: 1.05rem;
      border-top: 1px solid #e2e2e2;
      padding-top: 8px;
      margin-top: 4px;
    }
    .shipping-options {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 10px;
      padding: 8px 0;
    }
    .radio-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border: 1px solid #dee2e6;
      border-radius: 8px;
      cursor: pointer;
      transition: border-color 0.15s, background-color 0.15s;
    }
    .radio-row:hover {
      border-color: #adb5bd;
      background-color: #f8f9fa;
    }
    .radio-row:has(input:checked) {
      border-color: #1c212c;
      background-color: #f8f9fa;
    }
    .radio-row input[type="radio"] {
      margin: 0;
      width: 18px;
      height: 18px;
      accent-color: #1c212c;
      flex-shrink: 0;
    }
    .radio-label {
      flex: 1;
      font-size: 0.95rem;
    }
    .radio-cost {
      font-weight: 600;
      min-width: 60px;
      text-align: right;
      color: #1c212c;
    }
    @media (max-width: 768px) {
      .card-grid {
        grid-template-columns: 1fr;
      }
      .shipping-options {
        grid-template-columns: 1fr;
      }
    }
    .guest-account-offer {
      margin-top: 24px;
      padding: 20px;
      background: #f8f9fa;
      border-radius: 8px;
      border: 1px solid #e2e2e2;
    }
    .guest-account-offer h3 {
      margin: 0 0 8px;
    }
    .guest-account-offer p {
      margin: 0 0 16px;
      color: #666;
    }
    .notice-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
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
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page">
    <section class="panel">
      <div class="section-title">
        <div>
          <h2>Checkout</h2>
          <?php if ($stripeEnabled): ?>
            <p class="meta">Confirm shipment details and pay securely by card.</p>
          <?php else: ?>
            <p class="meta">Card payments are unavailable. Please contact support.</p>
          <?php endif; ?>
        </div>
        <div class="price">
          <div class="checkout-summary">
            <div class="summary-line">
              <span>Subtotal</span>
              <span id="checkout-subtotal" data-subtotal="<?php echo number_format($subtotal, 2, '.', ''); ?>" data-taxable-subtotal="<?php echo number_format($taxableSubtotal, 2, '.', ''); ?>">
                $<?php echo number_format($subtotal, 2); ?>
              </span>
            </div>
            <div class="summary-line">
              <span>Tax</span>
              <span id="checkout-tax">—</span>
            </div>
            <div class="summary-line">
              <span>Shipping</span>
              <span id="checkout-shipping" data-shipping="<?php echo number_format($shippingCost, 2, '.', ''); ?>">
                $<?php echo number_format($shippingCost, 2); ?>
              </span>
            </div>
            <div class="summary-line summary-total">
              <span>Total</span>
              <span id="checkout-total">$<?php echo number_format($subtotal + $shippingCost, 2); ?></span>
            </div>
          </div>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="notice" role="alert"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="notice notice-success">Order placed successfully! Confirmation: <?php echo htmlspecialchars($success['orderNumber'], ENT_QUOTES); ?></div>

        <?php if ($isGuestMode && !$user): ?>
          <!-- Guest checkout - offer account creation -->
          <div class="guest-account-offer" id="guest-account-offer">
            <h3>Create an Account</h3>
            <p>Save your information for faster checkout next time. Just add a password below!</p>
            <form id="guest-create-account-form" class="form-grid cols-2">
              <input type="hidden" name="_fts" value="<?php echo time(); ?>" />
              <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <label for="co_website_url">Leave blank</label>
                <input type="text" id="co_website_url" name="website_url" tabindex="-1" autocomplete="off" />
              </div>
              <input type="hidden" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES); ?>" />
              <input type="hidden" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>" />
              <input type="hidden" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? '', ENT_QUOTES); ?>" />
              <input type="hidden" name="address" value="<?php echo htmlspecialchars($_POST['address1'] ?? '', ENT_QUOTES); ?>" />
              <input type="hidden" name="city" value="<?php echo htmlspecialchars($_POST['city'] ?? '', ENT_QUOTES); ?>" />
              <input type="hidden" name="state" value="<?php echo htmlspecialchars($_POST['state'] ?? '', ENT_QUOTES); ?>" />
              <input type="hidden" name="zip" value="<?php echo htmlspecialchars($_POST['postal'] ?? '', ENT_QUOTES); ?>" />
              <div class="span-2">
                <label for="guest-password">Create Password</label>
                <input id="guest-password" name="password" type="password" required minlength="6" placeholder="At least 6 characters" />
              </div>
              <div class="span-2">
                <label for="guest-password-confirm">Confirm Password</label>
                <input id="guest-password-confirm" name="password_confirm" type="password" required minlength="6" />
              </div>
              <div class="span-2">
                <button type="submit" class="btn">Create Account</button>
                <a href="/" class="btn-outline" style="margin-left: 10px;">No thanks, continue shopping</a>
              </div>
              <div class="span-2" id="guest-account-message" style="display:none;"></div>
            </form>
          </div>
          <script nonce="<?php echo opd_csp_nonce(); ?>">
            (function() {
              const form = document.getElementById('guest-create-account-form');
              const messageEl = document.getElementById('guest-account-message');
              if (!form) return;

              form.addEventListener('submit', async (e) => {
                e.preventDefault();
                messageEl.style.display = 'none';

                const password = form.password.value;
                const confirmPassword = form.password_confirm.value;

                if (password !== confirmPassword) {
                  messageEl.textContent = 'Passwords do not match.';
                  messageEl.className = 'notice is-error';
                  messageEl.style.display = 'block';
                  return;
                }

                const payload = {
                  name: form.name.value,
                  email: form.email.value,
                  phone: form.phone.value,
                  address: form.address.value,
                  city: form.city.value,
                  state: form.state.value,
                  zip: form.zip.value,
                  password: password,
                  _fts: form._fts ? form._fts.value : '',
                  website_url: form.website_url ? form.website_url.value : ''
                };

                try {
                  const resp = await fetch('/api/guest_register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                  });
                  const data = await resp.json();
                  if (!resp.ok) {
                    throw new Error(data.error || 'Registration failed');
                  }
                  messageEl.textContent = 'Account created! Redirecting to your dashboard...';
                  messageEl.className = 'notice notice-success';
                  messageEl.style.display = 'block';
                  setTimeout(() => {
                    window.location.href = '/dashboard-orders.php';
                  }, 1500);
                } catch (err) {
                  messageEl.textContent = err.message;
                  messageEl.className = 'notice is-error';
                  messageEl.style.display = 'block';
                }
              });
            })();
          </script>
        <?php else: ?>
          <a class="btn" href="/dashboard-orders.php">View orders</a>
        <?php endif; ?>
      <?php elseif (!$items): ?>
        <div class="empty-cart">
          <div class="empty-cart-icon" aria-hidden="true">🛒</div>
          <h3>Your cart is empty</h3>
          <p>Browse our products and add items to get started.</p>
          <a href="/products.php" class="btn">Browse Products</a>
        </div>
      <?php else: ?>
        <form method="POST" class="form-grid cols-2" id="checkout-form">
          <input type="hidden" name="_csrf" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES); ?>" />
          <input type="hidden" name="accounting_payload" value="<?php echo htmlspecialchars($accountingPayloadJson, ENT_QUOTES); ?>" />
          <input type="hidden" name="payment_intent_id" id="payment_intent_id" value="" />
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
            <input id="phone" name="phone" value="<?php echo htmlspecialchars($profile['cellPhone'] ?? '', ENT_QUOTES); ?>" />
          </div>
          <div>
            <label for="country">Country</label>
            <input id="country" name="country" value="<?php echo htmlspecialchars($profile['country'] ?? 'USA', ENT_QUOTES); ?>" />
          </div>
          <div>
            <label for="address1">Address line 1</label>
            <input id="address1" name="address1" value="<?php echo htmlspecialchars($profile['address'] ?? '', ENT_QUOTES); ?>" required />
          </div>
          <div>
            <label for="address2">Address line 2</label>
            <input id="address2" name="address2" />
          </div>
          <div>
            <label for="city">City</label>
            <input id="city" name="city" value="<?php echo htmlspecialchars($profile['city'] ?? '', ENT_QUOTES); ?>" required />
          </div>
          <div>
            <label for="state">State</label>
            <input id="state" name="state" value="<?php echo htmlspecialchars($profile['state'] ?? '', ENT_QUOTES); ?>" required />
          </div>
          <div>
            <label for="postal">Postal code</label>
            <input id="postal" name="postal" value="<?php echo htmlspecialchars($profile['zip'] ?? '', ENT_QUOTES); ?>" required />
          </div>
          <div class="span-2" style="border-top:1px solid var(--stroke);padding-top:16px;margin-top:8px;">
            <label style="font-weight:600;margin-bottom:8px;display:block;">Ship to a different name/phone? (optional)</label>
          </div>
          <div>
            <label for="shippingFirstName">Shipping first name</label>
            <input id="shippingFirstName" name="shippingFirstName" value="<?php echo htmlspecialchars($profile['shippingFirstName'] ?? '', ENT_QUOTES); ?>" />
          </div>
          <div>
            <label for="shippingLastName">Shipping last name</label>
            <input id="shippingLastName" name="shippingLastName" value="<?php echo htmlspecialchars($profile['shippingLastName'] ?? '', ENT_QUOTES); ?>" />
          </div>
          <div>
            <label for="shippingPhone">Shipping phone</label>
            <input id="shippingPhone" name="shippingPhone" value="<?php echo htmlspecialchars($profile['shippingPhone'] ?? '', ENT_QUOTES); ?>" />
          </div>
          <?php if ($isServiceOnlyCart): ?>
          <input type="hidden" name="shipping_method" value="service" />
          <div class="span-2">
            <div class="notice-info">Service items — no shipping required.</div>
          </div>
          <?php else: ?>
          <div class="span-2">
            <label>Shipping method</label>
            <div class="shipping-options" id="checkout-shipping-options">
              <label class="radio-row">
                <input type="radio" name="shipping_method" value="pickup" data-cost="0" <?php echo $shippingMethod === 'pickup' ? 'checked' : ''; ?> />
                <span class="radio-label">Pickup</span>
                <span class="radio-cost">Free</span>
              </label>
              <label class="radio-row">
                <input type="radio" name="shipping_method" value="standard" data-cost="0" <?php echo $shippingMethod === 'standard' ? 'checked' : ''; ?> />
                <span class="radio-label">Standard delivery</span>
                <span class="radio-cost" id="checkout-standard-cost">Enter state for rate</span>
              </label>
              <div class="notice is-error" id="checkout-standard-error" role="alert" hidden></div>
              <label class="radio-row">
                <input type="radio" name="shipping_method" value="same_day" data-cost="0" <?php echo $shippingMethod === 'same_day' ? 'checked' : ''; ?> />
                <span class="radio-label">Same-day delivery</span>
                <span class="radio-cost" id="checkout-same-day-cost">Enter ZIP for rate</span>
              </label>
              <div class="notice is-error" id="checkout-delivery-zone-error" role="alert" hidden></div>
              <input type="hidden" name="delivery_zip" id="checkout-delivery-zip" value="" />
            </div>
          </div>
          <?php endif; ?>
          <?php if ($showUserSavedMethods): ?>
          <div class="span-2">
            <fieldset style="border:none;padding:0;margin:0;">
              <legend>Payment method</legend>
              <input type="hidden" name="payment_method_type" id="payment_method_type" value="card" />
              <div class="shipping-options" id="user-payment-options">
                <?php foreach ($userPaymentMethods as $idx => $pm): ?>
                  <label class="radio-row">
                    <input type="radio" name="saved_payment_choice" value="<?php echo htmlspecialchars($pm['id'] ?? '', ENT_QUOTES); ?>" <?php echo $idx === 0 ? 'checked' : ''; ?> />
                    <span class="radio-label"><?php echo htmlspecialchars(site_format_payment_method_label($pm), ENT_QUOTES); ?></span>
                    <?php if (!empty($pm['isPrimary'])): ?>
                      <span class="pm-primary-badge">Primary</span>
                    <?php endif; ?>
                  </label>
                <?php endforeach; ?>
                <label class="radio-row">
                  <input type="radio" name="saved_payment_choice" value="new_card" />
                  <span class="radio-label">Enter a new card</span>
                </label>
                <?php if ($allowInvoice): ?>
                <label class="radio-row">
                  <input type="radio" name="saved_payment_choice" value="invoice" />
                  <span class="radio-label">Pay by Invoice (Net 30)</span>
                </label>
                <?php endif; ?>
              </div>
            </fieldset>
          </div>
          <?php endif; ?>
          <div>
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes"></textarea>
          </div>
          <?php if ($requireSavedPayment): ?>
            <?php if ($userPaymentMethods): ?>
              <div class="span-2">
                <label>Payment method</label>
                <div class="saved-payment-method">
                  <strong>Your saved card</strong>
                  <span><?php echo htmlspecialchars(site_format_payment_method_label($userPaymentMethods[0]), ENT_QUOTES); ?></span>
                </div>
                <input type="hidden" id="user_saved_payment_method_id" value="<?php echo htmlspecialchars($userPaymentMethods[0]['id'] ?? '', ENT_QUOTES); ?>" />
                <?php if (count($userPaymentMethods) > 1): ?>
                  <div style="margin-top: 8px;">
                    <label for="select_payment_method">Or choose another saved card:</label>
                    <select id="select_payment_method">
                      <?php foreach ($userPaymentMethods as $pm): ?>
                        <option value="<?php echo htmlspecialchars($pm['id'] ?? '', ENT_QUOTES); ?>">
                          <?php echo htmlspecialchars(site_format_payment_method_label($pm), ENT_QUOTES); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                <?php endif; ?>
              </div>
              <div class="card-error" id="card-error" role="alert"></div>
            <?php else: ?>
              <div class="span-2">
                <div class="notice is-error">
                  Service items require a saved payment method. Please
                  <a href="/dashboard-account.php">add a payment method</a> in your account dashboard first.
                </div>
              </div>
              <div class="card-error" id="card-error" role="alert"></div>
            <?php endif; ?>
          <?php elseif ($stripeEnabled): ?>
            <?php if (!$showUserSavedMethods): ?>
              <input type="hidden" name="payment_method_type" id="payment_method_type" value="card" />
            <?php endif; ?>
            <?php if ($clientPaymentMethodId !== ''): ?>
              <div class="span-2">
                <label>Payment method</label>
                <div class="saved-payment-method">
                  <strong>Client saved card</strong>
                  <span><?php echo htmlspecialchars($clientPaymentMethodLabel, ENT_QUOTES); ?></span>
                </div>
                <input type="hidden" id="client_payment_method_id" value="<?php echo htmlspecialchars($clientPaymentMethodId, ENT_QUOTES); ?>" />
                <label class="checkbox-row" for="use_new_card">
                  <input id="use_new_card" name="use_new_card" type="checkbox" value="1" />
                  Use a different card for this order
                </label>
              </div>
            <?php elseif ($clientId): ?>
              <div class="span-2">
                <div class="notice">No saved payment method found for this client. Enter a card below.</div>
              </div>
            <?php endif; ?>
            <?php if ($allowInvoice && !$showUserSavedMethods): ?>
              <div class="span-2">
                <label class="checkbox-row" for="pay_by_invoice">
                  <input id="pay_by_invoice" name="pay_by_invoice" type="checkbox" value="1" />
                  Pay by Invoice (Net 30)
                </label>
              </div>
            <?php endif; ?>
            <div class="card-field" id="card-entry-section" style="grid-column: 1 / -1;">
              <label>Card details</label>
              <div class="card-grid">
                <div class="card-slot">
                  <span class="card-label">Card number</span>
                  <div id="card-number-element" class="stripe-card-element"></div>
                </div>
                <div class="card-slot">
                  <span class="card-label">Expiration date</span>
                  <div id="card-expiry-element" class="stripe-card-element"></div>
                </div>
                <div class="card-slot">
                  <span class="card-label">CVC</span>
                  <div id="card-cvc-element" class="stripe-card-element"></div>
                </div>
              </div>
              <div class="card-error" id="card-error" role="alert"></div>
              <div class="notice" id="stripe-checkout-banner">Loading card fields...</div>
            </div>
          <?php endif; ?>
          <?php if ($requireSavedPayment && !$userPaymentMethods): ?>
          <div style="grid-column: 1 / -1;">
            <a class="btn" href="/dashboard-account.php">Add Payment Method</a>
          </div>
          <?php else: ?>
          <div style="grid-column: 1 / -1;">
            <button class="btn" type="submit" id="checkout-submit">
              <?php echo $stripeEnabled || $requireSavedPayment || $showUserSavedMethods ? 'Pay and place order' : 'Place order'; ?>
            </button>
          </div>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </section>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
  <?php if ($stripeEnabled || $requireSavedPayment || $showUserSavedMethods): ?>
    <script nonce="<?php echo opd_csp_nonce(); ?>">
      (function () {
        var stripeKey = <?php echo json_encode($stripePublishableKey); ?>;
        var form = document.getElementById('checkout-form');
        var errorEl = document.getElementById('card-error');
        var bannerEl = document.getElementById('stripe-checkout-banner');
        var submitBtn = document.getElementById('checkout-submit');
        var intentInput = document.getElementById('payment_intent_id');
        var subtotalEl = document.getElementById('checkout-subtotal');
        var taxEl = document.getElementById('checkout-tax');
        var shippingEl = document.getElementById('checkout-shipping');
        var totalEl = document.getElementById('checkout-total');
        var clientId = <?php echo json_encode($clientId); ?>;
        var isGuestMode = <?php echo json_encode($isGuestMode); ?>;
        var isServiceOnlyCart = <?php echo json_encode($isServiceOnlyCart); ?>;
        var requireSavedPayment = <?php echo json_encode($requireSavedPayment); ?>;
        var showUserSavedMethods = <?php echo json_encode($showUserSavedMethods); ?>;
        var userSavedMethodInput = document.getElementById('user_saved_payment_method_id');
        var selectPaymentMethod = document.getElementById('select_payment_method');
        var savedMethodInput = document.getElementById('client_payment_method_id');
        var useNewCardToggle = document.getElementById('use_new_card');
        var cardSection = document.getElementById('card-entry-section');
        var savedPaymentRadios = Array.prototype.slice.call(form.querySelectorAll('input[name="saved_payment_choice"]'));
        if (!form || !submitBtn || !intentInput) {
          return;
        }

        var subtotalValue = subtotalEl ? parseFloat(subtotalEl.dataset.subtotal || '0') : 0;
        var taxableSubtotal = subtotalEl ? parseFloat(subtotalEl.dataset.taxableSubtotal || '0') : 0;
        var taxValue = 0;
        var shippingValue = shippingEl ? parseFloat(shippingEl.dataset.shipping || '0') : 0;
        var taxTimer = null;

        var stripe = null;
        var cardNumber = null;
        var cardExpiry = null;
        var cardCvc = null;
        var didInit = false;
        var submitting = false;
        var numberComplete = false;
        var expiryComplete = false;
        var cvcComplete = false;
        var cardErrors = { cardNumber: '', cardExpiry: '', cardCvc: '' };
        var shippingInputs = Array.prototype.slice.call(form.querySelectorAll('input[name="shipping_method"]'));
        var deliveryZoneMap = <?php echo json_encode($deliveryZones, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        var deliveryCostsData = <?php echo json_encode($deliveryCosts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        var hasLargeDelivery = <?php echo $hasLargeDelivery ? 'true' : 'false'; ?>;
        var deliveryZipInput = document.getElementById('checkout-delivery-zip');
        var sameDayCostLabel = document.getElementById('checkout-same-day-cost');
        var deliveryZoneErrorEl = document.getElementById('checkout-delivery-zone-error');
        var shippingZonesData = <?php echo json_encode($shippingZones, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        var cartTotalWeight = <?php echo json_encode($cartTotalWeight); ?>;
        var standardCostLabel = document.getElementById('checkout-standard-cost');
        var standardErrorEl = document.getElementById('checkout-standard-error');

        // Sync payment method dropdown selection to hidden input
        if (selectPaymentMethod && userSavedMethodInput) {
          selectPaymentMethod.addEventListener('change', function () {
            userSavedMethodInput.value = selectPaymentMethod.value;
          });
        }

        function getUserPaymentMethodId() {
          if (selectPaymentMethod) {
            return selectPaymentMethod.value;
          }
          return userSavedMethodInput ? userSavedMethodInput.value : '';
        }

        function getSelectedSavedPaymentChoice() {
          for (var i = 0; i < savedPaymentRadios.length; i++) {
            if (savedPaymentRadios[i].checked) return savedPaymentRadios[i].value;
          }
          return '';
        }

        function isUsingSavedUserMethod() {
          if (!showUserSavedMethods) return false;
          var choice = getSelectedSavedPaymentChoice();
          return choice !== '' && choice !== 'new_card' && choice !== 'invoice';
        }

        function isUsingInvoice() {
          var invoiceCheckbox = document.getElementById('pay_by_invoice');
          if (invoiceCheckbox && invoiceCheckbox.checked) return true;
          return getSelectedSavedPaymentChoice() === 'invoice';
        }

        function usingSavedMethod() {
          return !!(savedMethodInput && savedMethodInput.value) && !(useNewCardToggle && useNewCardToggle.checked);
        }

        function toggleCardSection() {
          var pmTypeInput = document.getElementById('payment_method_type');
          if (isUsingInvoice()) {
            if (cardSection) cardSection.style.display = 'none';
            if (pmTypeInput) pmTypeInput.value = 'invoice';
            if (submitBtn) submitBtn.textContent = 'Place order (Invoice)';
            return;
          }
          if (pmTypeInput) pmTypeInput.value = 'card';
          if (!cardSection) {
            return;
          }
          if (showUserSavedMethods && isUsingSavedUserMethod()) {
            cardSection.style.display = 'none';
            if (submitBtn) submitBtn.textContent = 'Pay and place order';
            return;
          }
          cardSection.style.display = '';
          if (submitBtn) submitBtn.textContent = 'Pay and place order';
        }

        function formatMoney(value) {
          return '$' + Number(value || 0).toFixed(2);
        }

        function setTaxDisplay(value, showDash) {
          taxValue = Number(value || 0);
          if (taxEl) {
            taxEl.textContent = showDash ? '—' : formatMoney(taxValue);
          }
          if (totalEl) {
            totalEl.textContent = formatMoney(subtotalValue + taxValue + shippingValue);
          }
        }

        function setShippingDisplay(value) {
          shippingValue = Number(value || 0);
          if (shippingEl) {
            shippingEl.textContent = formatMoney(shippingValue);
          }
          if (totalEl) {
            totalEl.textContent = formatMoney(subtotalValue + taxValue + shippingValue);
          }
        }

        function findSelectedShippingInput() {
          for (var i = 0; i < shippingInputs.length; i += 1) {
            if (shippingInputs[i] && shippingInputs[i].checked) {
              return shippingInputs[i];
            }
          }
          return null;
        }

        function getSelectedShippingMethod() {
          var selected = findSelectedShippingInput();
          return selected ? selected.value : 'pickup';
        }

        function calcCheckoutDeliveryCost(zip) {
          if (!zip) return { cost: 0, error: '', ready: false };
          var zone = deliveryZoneMap[zip];
          if (!zone) return { cost: 0, error: 'Sorry, we do not deliver outside Oklahoma', ready: false };
          var cls = hasLargeDelivery ? 'large' : 'small';
          var cost = Number((deliveryCostsData[cls] || {})[zone] || 0);
          return { cost: cost, error: '', ready: true };
        }

        function calcCheckoutStandardCost(state) {
          state = (state || '').toUpperCase().trim();
          if (!state) return { cost: 0, error: '', ready: false };
          var keys = Object.keys(shippingZonesData);
          for (var i = 0; i < keys.length; i++) {
            var zoneData = shippingZonesData[keys[i]];
            if (zoneData.states && zoneData.states.indexOf(state) !== -1) {
              var cost = Number(zoneData.flat || 0) + (cartTotalWeight * Number(zoneData.perLb || 0));
              return { cost: Math.max(0, cost), error: '', ready: true };
            }
          }
          return { cost: 0, error: 'Sorry we do not ship outside the continental United States', ready: false };
        }

        function refreshShipping() {
          if (isServiceOnlyCart) {
            setShippingDisplay(0);
            return;
          }
          var selected = findSelectedShippingInput();
          var method = selected ? selected.value : 'pickup';
          if (method === 'same_day') {
            var postalField = document.getElementById('postal');
            var zip = postalField ? postalField.value.trim() : '';
            if (deliveryZipInput) deliveryZipInput.value = zip;
            var result = calcCheckoutDeliveryCost(zip);
            if (deliveryZoneErrorEl) {
              if (result.error) {
                deliveryZoneErrorEl.textContent = result.error;
                deliveryZoneErrorEl.hidden = false;
              } else {
                deliveryZoneErrorEl.textContent = '';
                deliveryZoneErrorEl.hidden = true;
              }
            }
            if (standardErrorEl) { standardErrorEl.hidden = true; }
            if (sameDayCostLabel) {
              sameDayCostLabel.textContent = result.ready ? formatMoney(result.cost) : (result.error || 'Enter ZIP for rate');
            }
            setShippingDisplay(result.ready ? result.cost : 0);
          } else if (method === 'standard') {
            if (deliveryZoneErrorEl) { deliveryZoneErrorEl.hidden = true; }
            if (sameDayCostLabel) { sameDayCostLabel.textContent = 'Enter ZIP for rate'; }
            var stateField = document.getElementById('state');
            var stateVal = stateField ? stateField.value.trim() : '';
            var stdResult = calcCheckoutStandardCost(stateVal);
            if (standardErrorEl) {
              if (stdResult.error) {
                standardErrorEl.textContent = stdResult.error;
                standardErrorEl.hidden = false;
              } else {
                standardErrorEl.textContent = '';
                standardErrorEl.hidden = true;
              }
            }
            if (standardCostLabel) {
              standardCostLabel.textContent = stdResult.ready ? formatMoney(stdResult.cost) : (stdResult.error || 'Enter state for rate');
            }
            setShippingDisplay(stdResult.ready ? stdResult.cost : 0);
          } else {
            if (deliveryZoneErrorEl) { deliveryZoneErrorEl.hidden = true; }
            if (standardErrorEl) { standardErrorEl.hidden = true; }
            if (sameDayCostLabel) { sameDayCostLabel.textContent = 'Enter ZIP for rate'; }
            if (standardCostLabel) { standardCostLabel.textContent = 'Enter state for rate'; }
            setShippingDisplay(0);
          }
        }

        function applyCartShippingPrefill() {
          try {
            var raw = localStorage.getItem('opd_cart_shipping_v2');
            if (!raw) {
              return;
            }
            var data = JSON.parse(raw);
            if (!data || typeof data !== 'object') {
              return;
            }

            var address1 = data.address1 || '';
            var address2 = data.address2 || '';
            var city = data.city || '';
            var state = data.state || '';
            var zip = data.zip || '';
            if (!address1 && data.mode === 'coords' && data.coordinate) {
              address1 = 'Coordinates: ' + data.coordinate;
            }

            var address1Input = form.querySelector('#address1');
            var address2Input = form.querySelector('#address2');
            var cityInput = form.querySelector('#city');
            var stateInput = form.querySelector('#state');
            var postalInput = form.querySelector('#postal');

            if (address1 && address1Input) address1Input.value = address1;
            if (address2 && address2Input) address2Input.value = address2;
            if (city && cityInput) cityInput.value = city;
            if (state && stateInput) stateInput.value = state;
            if (zip && postalInput) postalInput.value = zip;

            if (data.method) {
              for (var i = 0; i < shippingInputs.length; i += 1) {
                if (shippingInputs[i] && shippingInputs[i].value === data.method) {
                  shippingInputs[i].checked = true;
                }
              }
            }
          } catch (e) {
            // ignore storage errors
          }
        }

        function fetchTaxQuote() {
          var stateInput = form.querySelector('#state');
          var postalInput = form.querySelector('#postal');
          var stateValue = stateInput ? stateInput.value.trim() : '';
          var postalValue = postalInput ? postalInput.value.trim() : '';
          if (!stateValue || !postalValue) {
            setTaxDisplay(0, true);
            return;
          }
          fetch('/api/tax_quote.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ subtotal: taxableSubtotal, state: stateValue, postal: postalValue })
          })
            .then(function (resp) {
              return resp.json().catch(function () {
                return {};
              }).then(function (data) {
                if (!resp.ok) {
                  throw new Error(data.error || 'Tax lookup failed.');
                }
                if (data.taxable && !data.rateFound) {
                  setTaxDisplay(0, true);
                } else {
                  setTaxDisplay(Number(data.tax || 0), false);
                }
              });
            })
            .catch(function () {
              setTaxDisplay(0, true);
            });
        }

        function queueTaxQuote() {
          if (taxTimer) {
            clearTimeout(taxTimer);
          }
          taxTimer = setTimeout(fetchTaxQuote, 350);
        }

        function normalizeCountry(value) {
          var raw = (value || '').trim();
          if (!raw) {
            return '';
          }
          var upper = raw.toUpperCase();
          if (upper.length === 2) {
            return upper;
          }
          var lower = raw.toLowerCase();
          if (lower === 'usa' || lower === 'u.s.a.' || lower === 'u.s.' || lower === 'united states' || lower === 'united states of america') {
            return 'US';
          }
          return '';
        }

        function setError(message) {
          if (!errorEl) {
            return;
          }
          errorEl.textContent = message || '';
        }

        function showStripeLoadError(message) {
          var text = message || 'Stripe failed to load. Disable blockers or allow js.stripe.com.';
          setError(text);
          if (bannerEl) {
            bannerEl.textContent = text;
            bannerEl.className = 'notice is-error';
            bannerEl.style.display = 'block';
          }
        }

        if (useNewCardToggle) {
          useNewCardToggle.addEventListener('change', toggleCardSection);
        }
        var invoiceCheckbox = document.getElementById('pay_by_invoice');
        if (invoiceCheckbox) {
          invoiceCheckbox.addEventListener('change', toggleCardSection);
        }
        savedPaymentRadios.forEach(function (radio) {
          radio.addEventListener('change', function () {
            toggleCardSection();
            // Init Stripe if switching to new card
            if (getSelectedSavedPaymentChoice() === 'new_card' && !didInit) {
              waitForStripe(new Date().getTime());
            }
          });
        });
        toggleCardSection();

        function initStripe() {
          if (didInit) {
            return;
          }
          didInit = true;
          if (!window.Stripe || !stripeKey) {
            showStripeLoadError('Stripe failed to load. Disable blockers or allow js.stripe.com.');
            return;
          }
          stripe = Stripe(stripeKey);
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
          cardNumber = elements.create('cardNumber', { style: elementStyle, disableLink: true });
          cardExpiry = elements.create('cardExpiry', { style: elementStyle, disableLink: true });
          cardCvc = elements.create('cardCvc', { style: elementStyle, disableLink: true });
          cardNumber.mount('#card-number-element');
          cardExpiry.mount('#card-expiry-element');
          cardCvc.mount('#card-cvc-element');

          var numberWrap = document.getElementById('card-number-element');
          var expiryWrap = document.getElementById('card-expiry-element');
          var cvcWrap = document.getElementById('card-cvc-element');

          function handleCardChange(event) {
            var code = event.error && event.error.code ? (' (' + event.error.code + ')') : '';
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

          if (numberWrap) {
            numberWrap.addEventListener('click', function () {
              cardNumber.focus();
            });
          }
          if (expiryWrap) {
            expiryWrap.addEventListener('click', function () {
              cardExpiry.focus();
            });
          }
          if (cvcWrap) {
            cvcWrap.addEventListener('click', function () {
              cardCvc.focus();
            });
          }

          setTimeout(function () {
            var hasNumber = numberWrap && numberWrap.querySelector('iframe');
            var hasExpiry = expiryWrap && expiryWrap.querySelector('iframe');
            var hasCvc = cvcWrap && cvcWrap.querySelector('iframe');
            if (!hasNumber || !hasExpiry || !hasCvc) {
              showStripeLoadError('Stripe is blocked. Disable blockers or allow js.stripe.com to pay by card.');
            }
          }, 1200);
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

        Array.prototype.forEach.call(form.querySelectorAll('#state, #postal'), function (input) {
          ['input', 'change', 'blur'].forEach(function (eventName) {
            input.addEventListener(eventName, queueTaxQuote);
          });
        });
        var postalInput = document.getElementById('postal');
        var stateInput = document.getElementById('state');
        if (postalInput) {
          ['input', 'change', 'blur'].forEach(function (eventName) {
            postalInput.addEventListener(eventName, refreshShipping);
          });
        }
        if (stateInput) {
          ['input', 'change', 'blur'].forEach(function (eventName) {
            stateInput.addEventListener(eventName, refreshShipping);
          });
        }
        shippingInputs.forEach(function (input) {
          if (!input) {
            return;
          }
          input.addEventListener('change', refreshShipping);
        });
        applyCartShippingPrefill();
        refreshShipping();
        setTaxDisplay(0, true);
        queueTaxQuote();
        if (!requireSavedPayment && !(showUserSavedMethods && isUsingSavedUserMethod())) {
          waitForStripe(new Date().getTime());
        }

        form.addEventListener('submit', function (event) {
          if (submitting) {
            return;
          }
          event.preventDefault();
          setError('');
          submitting = true;
          submitBtn.disabled = true;
          submitBtn.textContent = 'Processing…';

          var stateInput = form.querySelector('#state');
          var postalInput = form.querySelector('#postal');
          var stateValue = stateInput ? stateInput.value || '' : '';
          var postalValue = postalInput ? postalInput.value || '' : '';

          // Invoice checkout: skip Stripe entirely, submit form directly
          if (isUsingInvoice()) {
            form.submit();
            return;
          }

          // Service checkout: use user's saved payment method
          if (requireSavedPayment) {
            var pmId = getUserPaymentMethodId();
            if (!pmId) {
              setError('A saved payment method is required for service items.');
              submitting = false;
              submitBtn.disabled = false;
              return;
            }
            fetch('/api/stripe_checkout_intent.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': <?php echo json_encode($csrf); ?>
              },
              body: JSON.stringify({
                state: stateValue,
                postal: postalValue,
                userPaymentMethodId: pmId,
                shippingMethod: getSelectedShippingMethod()
              })
            })
              .then(function (resp) {
                return resp.json().catch(function () { return {}; }).then(function (intentData) {
                  if (!resp.ok || !intentData.paymentIntentId) {
                    throw new Error(intentData.error || 'Unable to charge the saved card.');
                  }
                  if (typeof intentData.tax !== 'undefined') {
                    var showDash = intentData.taxable && !intentData.rateFound;
                    setTaxDisplay(Number(intentData.tax || 0), showDash);
                  }
                  if (typeof intentData.shipping !== 'undefined') {
                    setShippingDisplay(Number(intentData.shipping || 0));
                  }
                  return intentData;
                });
              })
              .then(function (intentData) {
                intentInput.value = intentData.paymentIntentId || '';
                form.submit();
              })
              .catch(function (err) {
                setError(err && err.message ? err.message : 'Unable to process payment.');
                submitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Pay and place order';
              });
            return;
          }

          // Signed-in user chose a saved payment method (non-service)
          if (showUserSavedMethods && isUsingSavedUserMethod()) {
            var userPmId = getSelectedSavedPaymentChoice();
            fetch('/api/stripe_checkout_intent.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': <?php echo json_encode($csrf); ?>
              },
              body: JSON.stringify({
                state: stateValue,
                postal: postalValue,
                userPaymentMethodId: userPmId,
                shippingMethod: getSelectedShippingMethod()
              })
            })
              .then(function (resp) {
                return resp.json().catch(function () { return {}; }).then(function (intentData) {
                  if (!resp.ok || !intentData.paymentIntentId) {
                    throw new Error(intentData.error || 'Unable to charge the saved card.');
                  }
                  if (typeof intentData.tax !== 'undefined') {
                    var showDash = intentData.taxable && !intentData.rateFound;
                    setTaxDisplay(Number(intentData.tax || 0), showDash);
                  }
                  if (typeof intentData.shipping !== 'undefined') {
                    setShippingDisplay(Number(intentData.shipping || 0));
                  }
                  return intentData;
                });
              })
              .then(function (intentData) {
                intentInput.value = intentData.paymentIntentId || '';
                form.submit();
              })
              .catch(function (err) {
                setError(err && err.message ? err.message : 'Unable to process payment.');
                submitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Pay and place order';
              });
            return;
          }

          if (usingSavedMethod()) {
            fetch('/api/stripe_checkout_intent.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': <?php echo json_encode($csrf); ?>
              },
              body: JSON.stringify({
                state: stateValue,
                postal: postalValue,
                clientId: clientId || '',
                paymentMethodId: savedMethodInput ? savedMethodInput.value : '',
                shippingMethod: getSelectedShippingMethod()
              })
            })
              .then(function (resp) {
                return resp.json().catch(function () {
                  return {};
                }).then(function (intentData) {
                  if (!resp.ok || !intentData.paymentIntentId) {
                    throw new Error(intentData.error || 'Unable to charge the saved card.');
                  }
                  if (typeof intentData.tax !== 'undefined') {
                    var showDash = intentData.taxable && !intentData.rateFound;
                    setTaxDisplay(Number(intentData.tax || 0), showDash);
                  }
                  if (typeof intentData.shipping !== 'undefined') {
                    setShippingDisplay(Number(intentData.shipping || 0));
                  }
                  return intentData;
                });
            })
              .then(function (intentData) {
                intentInput.value = intentData.paymentIntentId || '';
                form.submit();
              })
              .catch(function (err) {
                setError(err && err.message ? err.message : 'Unable to start payment.');
                submitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = 'Pay and place order';
                if (useNewCardToggle) {
                  useNewCardToggle.checked = true;
                }
                toggleCardSection();
              });
            return;
          }

          if (!stripe || !cardNumber || !cardExpiry || !cardCvc) {
            showStripeLoadError('Stripe failed to load. Disable blockers or allow js.stripe.com.');
            submitting = false;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Pay and place order';
            return;
          }

          if (!numberComplete || !expiryComplete || !cvcComplete) {
            setError(cardErrors.cardNumber || cardErrors.cardExpiry || cardErrors.cardCvc || 'Enter complete card details.');
            submitting = false;
            submitBtn.disabled = false;
            submitBtn.textContent = 'Pay and place order';
            return;
          }

          // Build request payload - include guest info for guest checkout
          var requestPayload = {
            state: stateValue,
            postal: postalValue,
            shippingMethod: getSelectedShippingMethod()
          };
          if (isGuestMode) {
            var nameInput = form.querySelector('#name');
            var emailInput = form.querySelector('#email');
            requestPayload.guest = true;
            requestPayload.name = nameInput ? nameInput.value : '';
            requestPayload.email = emailInput ? emailInput.value : '';
          }

          fetch('/api/stripe_checkout_intent.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': <?php echo json_encode($csrf); ?>
            },
            body: JSON.stringify(requestPayload)
          })
            .then(function (resp) {
              return resp.json().catch(function () {
                return {};
              }).then(function (intentData) {
                if (!resp.ok || !intentData.clientSecret) {
                  throw new Error(intentData.error || 'Unable to start payment.');
                }
                if (typeof intentData.tax !== 'undefined') {
                  var showDash = intentData.taxable && !intentData.rateFound;
                  setTaxDisplay(Number(intentData.tax || 0), showDash);
                }
                if (typeof intentData.shipping !== 'undefined') {
                  setShippingDisplay(Number(intentData.shipping || 0));
                }
                return intentData;
              });
            })
            .then(function (intentData) {
              var countryInput = form.querySelector('#country');
              var countryValue = normalizeCountry(countryInput ? countryInput.value : '');
              var nameInput = form.querySelector('#name');
              var emailInput = form.querySelector('#email');
              var phoneInput = form.querySelector('#phone');
              var billingDetails = {
                name: nameInput ? nameInput.value : '',
                email: emailInput ? emailInput.value : '',
                phone: phoneInput ? phoneInput.value : ''
              };
              var address = {};
              var line1Input = form.querySelector('#address1');
              var line2Input = form.querySelector('#address2');
              var cityInput = form.querySelector('#city');
              var stateInputInner = form.querySelector('#state');
              var postalInputInner = form.querySelector('#postal');
              var line1 = line1Input ? line1Input.value : '';
              var line2 = line2Input ? line2Input.value : '';
              var city = cityInput ? cityInput.value : '';
              var state = stateInputInner ? stateInputInner.value : '';
              var postal = postalInputInner ? postalInputInner.value : '';
              if (line1) {
                address.line1 = line1;
              }
              if (line2) {
                address.line2 = line2;
              }
              if (city) {
                address.city = city;
              }
              if (state) {
                address.state = state;
              }
              if (postal) {
                address.postal_code = postal;
              }
              if (countryValue) {
                address.country = countryValue;
              }
              if (Object.keys(address).length) {
                billingDetails.address = address;
              }

              return stripe.confirmCardPayment(intentData.clientSecret, {
                payment_method: {
                  card: cardNumber,
                  billing_details: billingDetails
                }
              });
            })
            .then(function (result) {
              if (result.error) {
                var code = result.error.code ? (' (' + result.error.code + ')') : '';
                if (window.console && console.error) {
                  console.error('Stripe confirmCardPayment error', result.error);
                }
                throw new Error((result.error.message || 'Payment failed.') + code);
              }
              if (!result.paymentIntent || result.paymentIntent.status !== 'succeeded') {
                throw new Error('Payment did not complete. Please try again.');
              }
              intentInput.value = result.paymentIntent.id || '';
              form.submit();
            })
            .catch(function (err) {
              setError(err && err.message ? err.message : 'Unable to start payment.');
              submitting = false;
              submitBtn.disabled = false;
              submitBtn.textContent = 'Pay and place order';
            });
        });
      })();
    </script>
  <?php endif; ?>
</body>
</html>
