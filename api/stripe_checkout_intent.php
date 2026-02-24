<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/stripe_service.php';
require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/tax_rates.php';

function site_require_json_csrf(): void
{
    site_start_session();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $session = $_SESSION['site_csrf'] ?? '';
    if ($token === '' || $session === '' || !hash_equals($session, $token)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    opd_json_response(['error' => 'Method not allowed'], 405);
}

// Start session for CSRF and guest cart
site_start_session();

// Check for guest mode or authenticated user
$user = site_current_user();
$payload = opd_read_json();
$isGuest = !$user && isset($payload['guest']) && $payload['guest'] === true;

// Require auth if not guest mode
if (!$user && !$isGuest) {
    opd_json_response(['error' => 'Authentication required'], 401);
}

site_require_json_csrf();

// Get cart items - from session for guests, from database for logged-in users
if ($user) {
    $items = site_cart_items_for_user($user['id']);
} else {
    $items = site_cart_items(); // Uses session cart for guests
}

if (!$items) {
    opd_json_response(['error' => 'Cart is empty'], 400);
}

// Payload already read above for guest detection
$state = trim((string) ($payload['state'] ?? ''));
$postal = trim((string) ($payload['postal'] ?? ''));
$clientId = trim((string) ($payload['clientId'] ?? ''));
$requestedPaymentMethodId = trim((string) ($payload['paymentMethodId'] ?? ''));
$shippingMethod = trim((string) ($payload['shippingMethod'] ?? $payload['shipping_method'] ?? ''));

// Guest checkout requires email and name
$guestEmail = trim((string) ($payload['email'] ?? ''));
$guestName = trim((string) ($payload['name'] ?? ''));

if ($shippingMethod === '') {
    $shippingMethod = 'pickup';
}

// Only fetch profile for logged-in users
if ($user && ($state === '' || $postal === '')) {
    $pdo = opd_db();
    $profile = $pdo->prepare('SELECT state, zip FROM users WHERE id = ? LIMIT 1');
    $profile->execute([$user['id']]);
    $row = $profile->fetch() ?: [];
    if ($state === '') {
        $state = trim((string) ($row['state'] ?? ''));
    }
    if ($postal === '') {
        $postal = trim((string) ($row['zip'] ?? ''));
    }
}

$total = site_cart_total($items);
$taxableSubtotal = site_cart_taxable_total($items);
$taxData = opd_calculate_ok_sales_tax($taxableSubtotal, $state, $postal);
$tax = (float) ($taxData['tax'] ?? 0.0);
$isServiceOnly = site_cart_has_only_service_items($items);
if ($isServiceOnly) {
    $shipping = 0.0;
    $shippingMethod = 'service';
} elseif ($shippingMethod === 'same_day') {
    $deliveryZip = trim((string) ($payload['delivery_zip'] ?? $postal));
    $deliveryResult = site_get_same_day_delivery_cost($deliveryZip, $items);
    if ($deliveryResult['error']) {
        opd_json_response(['error' => $deliveryResult['error']], 400);
    }
    $shipping = $deliveryResult['cost'];
} elseif ($shippingMethod === 'standard') {
    $stdResult = site_calculate_standard_shipping($state, $items);
    if ($stdResult['error']) {
        opd_json_response(['error' => $stdResult['error']], 400);
    }
    $shipping = $stdResult['cost'];
} else {
    $shipping = 0.0;
}
$amountCents = (int) round(($total + $tax + $shipping) * 100);
if ($amountCents <= 0) {
    opd_json_response(['error' => 'Invalid cart total'], 400);
}

// For guests, use 'guest' as userId marker and provided email
$metadata = [
    'userId' => $user ? ($user['id'] ?? '') : 'guest',
    'email' => $user ? ($user['email'] ?? '') : $guestEmail,
    'cartItems' => (string) count($items),
    'tax' => number_format($tax, 2, '.', ''),
    'taxRate' => number_format((float) ($taxData['ratePercent'] ?? 0.0), 3, '.', ''),
    'taxZip' => (string) ($taxData['zip'] ?? ''),
    'taxState' => (string) ($taxData['state'] ?? ''),
    'shipping' => number_format($shipping, 2, '.', ''),
    'shippingMethod' => $shippingMethod,
];
if ($isGuest) {
    $metadata['isGuest'] = 'true';
    $metadata['guestName'] = $guestName;
}

// Handle user's own saved payment method (for service checkout)
$userPaymentMethodId = trim((string) ($payload['userPaymentMethodId'] ?? ''));
if ($user && $userPaymentMethodId !== '') {
    $userPaymentMethod = site_get_payment_method($user['id'], $userPaymentMethodId);
    if (!$userPaymentMethod) {
        opd_json_response(['error' => 'Payment method not found'], 404);
    }

    $stripePaymentMethodId = trim((string) ($userPaymentMethod['stripePaymentMethodId'] ?? ''));
    if ($stripePaymentMethodId === '') {
        opd_json_response(['error' => 'Payment method is not properly configured'], 400);
    }

    $customerId = stripe_get_or_create_customer($user);
    if (!$customerId) {
        opd_json_response(['error' => 'Unable to load Stripe customer'], 500);
    }

    // Verify payment method is attached to this customer
    $pmCheck = stripe_retrieve_payment_method($stripePaymentMethodId);
    if (!$pmCheck['ok']) {
        opd_json_response(['error' => 'Payment method is invalid or expired'], 400);
    }

    $pmCustomer = $pmCheck['data']['customer'] ?? null;
    if ($pmCustomer !== $customerId) {
        $attachResult = stripe_attach_payment_method($stripePaymentMethodId, $customerId);
        if (!$attachResult['ok']) {
            opd_json_response(['error' => 'Unable to use this payment method.'], 400);
        }
    }

    $metadata['userPaymentMethodId'] = $userPaymentMethodId;

    $intent = stripe_create_payment_intent($customerId, $stripePaymentMethodId, $amountCents, 'usd', $metadata);
    if (!$intent['ok']) {
        $errorMsg = $intent['error'] ?? 'Unknown Stripe error';
        if (str_contains($errorMsg, 'authentication') || str_contains($errorMsg, 'requires_action')) {
            opd_json_response(['error' => 'This card requires additional authentication. Please update your payment method.'], 400);
        } elseif (str_contains($errorMsg, 'insufficient_funds')) {
            opd_json_response(['error' => 'The card has insufficient funds.'], 400);
        } elseif (str_contains($errorMsg, 'card_declined')) {
            opd_json_response(['error' => 'The card was declined. Please try a different payment method.'], 400);
        } else {
            opd_json_response(['error' => 'Payment failed: ' . $errorMsg], 502);
        }
    }

    opd_json_response([
        'paymentIntentId' => $intent['data']['id'] ?? '',
        'status' => $intent['data']['status'] ?? '',
        'amount' => $amountCents,
        'tax' => $tax,
        'taxRate' => $taxData['ratePercent'] ?? 0.0,
        'taxable' => $taxData['taxable'] ?? false,
        'rateFound' => $taxData['rateFound'] ?? false,
        'shipping' => $shipping,
        'shippingMethod' => $shippingMethod,
        'currency' => 'usd',
        'usingSavedMethod' => true,
    ]);
}

$savedMethodRequested = $clientId !== '' && $requestedPaymentMethodId !== '';
if ($savedMethodRequested) {
    $clientRecord = site_get_client_record($user['id'], $clientId);
    if ($clientRecord && site_client_is_billable($clientRecord)) {
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
            $pdo = opd_db();
            $check = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
            $check->execute([$clientId]);
            if ($check->fetch()) {
                $linkedUserId = $clientId;
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
                $vendorPaymentMethodId = trim((string) ($vendorRecord['paymentMethodId'] ?? ''));

                if ($status === 'active' && $vendorPaymentMethodId !== '' && $vendorPaymentMethodId === $requestedPaymentMethodId) {
                    $clientPaymentMethod = site_get_payment_method($linkedUserId, $vendorPaymentMethodId);

                    if (!$clientPaymentMethod) {
                        error_log('Payment method not found: ' . $vendorPaymentMethodId . ' for user: ' . $linkedUserId);
                        opd_json_response(['error' => 'Payment method not found'], 404);
                    }

                    $stripePaymentMethodId = $clientPaymentMethod['stripePaymentMethodId'] ?? '';
                    if (!is_string($stripePaymentMethodId) || $stripePaymentMethodId === '') {
                        error_log('No Stripe payment method ID for payment method: ' . $vendorPaymentMethodId);
                        opd_json_response(['error' => 'Payment method is not properly configured'], 400);
                    }

                    $clientUserStmt = opd_db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
                    $clientUserStmt->execute([$linkedUserId]);
                    $clientUser = $clientUserStmt->fetch();

                    if (!$clientUser) {
                        error_log('Client user not found: ' . $linkedUserId);
                        opd_json_response(['error' => 'Client account not found'], 404);
                    }

                    $customerId = stripe_get_or_create_customer($clientUser);
                    if (!$customerId) {
                        error_log('Unable to get/create Stripe customer for user: ' . $linkedUserId);
                        opd_json_response(['error' => 'Unable to load Stripe customer'], 500);
                    }

                    // Verify payment method is attached to this customer
                    $pmCheck = stripe_retrieve_payment_method($stripePaymentMethodId);
                    if (!$pmCheck['ok']) {
                        error_log('Failed to retrieve payment method from Stripe: ' . $stripePaymentMethodId . ' - ' . ($pmCheck['error'] ?? 'unknown error'));
                        opd_json_response(['error' => 'Payment method is invalid or expired'], 400);
                    }

                    $pmCustomer = $pmCheck['data']['customer'] ?? null;
                    if ($pmCustomer !== $customerId) {
                        // Try to attach the payment method to the customer
                        error_log('Payment method not attached to customer, attempting to attach: ' . $stripePaymentMethodId . ' to ' . $customerId);
                        $attachResult = stripe_attach_payment_method($stripePaymentMethodId, $customerId);
                        if (!$attachResult['ok']) {
                            error_log('Failed to attach payment method: ' . ($attachResult['error'] ?? 'unknown error'));
                            opd_json_response(['error' => 'Unable to use this payment method. It may belong to a different account.'], 400);
                        }
                    }

                    $metadata['clientUserId'] = $linkedUserId;
                    $metadata['clientId'] = $clientId;
                    $metadata['vendorPaymentMethodId'] = $vendorPaymentMethodId;

                    $intent = stripe_create_payment_intent($customerId, $stripePaymentMethodId, $amountCents, 'usd', $metadata);

                    if (!$intent['ok']) {
                        $errorMsg = $intent['error'] ?? 'Unknown Stripe error';
                        error_log('Stripe payment intent failed: ' . $errorMsg . ' - Data: ' . json_encode($intent['data'] ?? []));

                        // Provide more specific error messages
                        if (str_contains($errorMsg, 'authentication') || str_contains($errorMsg, 'requires_action')) {
                            opd_json_response(['error' => 'This card requires additional authentication. Please use a different payment method or contact the card owner.'], 400);
                        } elseif (str_contains($errorMsg, 'insufficient_funds')) {
                            opd_json_response(['error' => 'The card has insufficient funds.'], 400);
                        } elseif (str_contains($errorMsg, 'card_declined')) {
                            opd_json_response(['error' => 'The card was declined. Please try a different payment method.'], 400);
                        } else {
                            opd_json_response(['error' => 'Payment failed: ' . $errorMsg], 502);
                        }
                    }

                    opd_json_response([
                        'paymentIntentId' => $intent['data']['id'] ?? '',
                        'status' => $intent['data']['status'] ?? '',
                        'amount' => $amountCents,
                        'tax' => $tax,
                        'taxRate' => $taxData['ratePercent'] ?? 0.0,
                        'taxable' => $taxData['taxable'] ?? false,
                        'rateFound' => $taxData['rateFound'] ?? false,
                        'shipping' => $shipping,
                        'shippingMethod' => $shippingMethod,
                        'currency' => 'usd',
                        'usingSavedMethod' => true,
                    ]);
                }
            }
        }
    }
    opd_json_response(['error' => 'Saved payment method is unavailable for this client.'], 400);
}

// Create Stripe customer - for guests, create a one-time customer
if ($user) {
    $customerId = stripe_get_or_create_customer($user);
} else {
    // Guest checkout - validate email is provided
    if ($guestEmail === '') {
        opd_json_response(['error' => 'Email is required for guest checkout'], 400);
    }
    // Create guest Stripe customer
    $customerId = stripe_create_guest_customer($guestEmail, $guestName);
}
if (!$customerId) {
    opd_json_response(['error' => 'Unable to load Stripe customer'], 500);
}

$intent = stripe_create_checkout_intent($customerId, $amountCents, 'usd', $metadata);
if (!$intent['ok']) {
    opd_json_response(['error' => $intent['error'] ?? 'Stripe error'], 502);
}

opd_json_response([
    'clientSecret' => $intent['data']['client_secret'] ?? '',
    'amount' => $amountCents,
    'tax' => $tax,
    'taxRate' => $taxData['ratePercent'] ?? 0.0,
    'taxable' => $taxData['taxable'] ?? false,
    'rateFound' => $taxData['rateFound'] ?? false,
    'shipping' => $shipping,
    'shippingMethod' => $shippingMethod,
    'currency' => 'usd',
]);
