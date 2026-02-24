<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/stripe_service.php';
require_once __DIR__ . '/../src/store.php';

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

$user = site_require_auth();
site_require_json_csrf();

$payload = opd_read_json();
$paymentMethodId = trim((string) ($payload['paymentMethodId'] ?? ''));
$label = trim((string) ($payload['label'] ?? ''));

if ($paymentMethodId === '') {
    opd_json_response(['error' => 'Missing payment method'], 400);
}

$customerId = stripe_get_or_create_customer($user);
if (!$customerId) {
    opd_json_response(['error' => 'Unable to load Stripe customer'], 500);
}

$pmResponse = stripe_retrieve_payment_method($paymentMethodId);
if (!$pmResponse['ok']) {
    opd_json_response(['error' => $pmResponse['error'] ?? 'Unable to fetch payment method'], 502);
}

$pm = $pmResponse['data'] ?? [];
$pmCustomer = $pm['customer'] ?? null;
if ($pmCustomer && $pmCustomer !== $customerId) {
    opd_json_response(['error' => 'Payment method belongs to another customer'], 403);
}

if (!$pmCustomer) {
    $attach = stripe_attach_payment_method($paymentMethodId, $customerId);
    if (!$attach['ok']) {
        opd_json_response(['error' => $attach['error'] ?? 'Unable to attach payment method'], 502);
    }
    $pm = $attach['data'] ?? $pm;
}

if (($pm['type'] ?? '') !== 'card') {
    opd_json_response(['error' => 'Only card payment methods are supported'], 400);
}

$card = $pm['card'] ?? [];
$last4 = (string) ($card['last4'] ?? '');
$expMonth = (int) ($card['exp_month'] ?? 0);
$expYear = (int) ($card['exp_year'] ?? 0);
$brand = (string) ($card['brand'] ?? 'Card');

if ($label === '') {
    $label = sprintf('%s ending %s', ucfirst($brand), $last4);
}

$pdo = opd_db();
site_ensure_payment_methods_table($pdo);
$exists = $pdo->prepare('SELECT id FROM payment_methods WHERE userId = ? AND stripePaymentMethodId = ? LIMIT 1');
$exists->execute([$user['id'], $paymentMethodId]);
$row = $exists->fetch();

if ($row) {
    opd_json_response([
        'id' => $row['id'],
        'label' => $label,
        'type' => 'card',
        'brand' => $brand,
        'last4' => $last4,
        'expMonth' => $expMonth,
        'expYear' => $expYear,
        'stripePaymentMethodId' => $paymentMethodId,
    ]);
}

$now = gmdate('Y-m-d H:i:s');
$localId = opd_generate_id('pm');
$insert = $pdo->prepare(
    'INSERT INTO payment_methods (id, userId, label, type, brand, last4, stripePaymentMethodId, expMonth, expYear, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$insert->execute([
    $localId,
    $user['id'],
    $label,
    'card',
    $brand,
    $last4,
    $paymentMethodId,
    $expMonth ?: null,
    $expYear ?: null,
    $now,
    $now,
]);

opd_json_response([
    'id' => $localId,
    'label' => $label,
    'type' => 'card',
    'brand' => $brand,
    'last4' => $last4,
    'expMonth' => $expMonth,
    'expYear' => $expYear,
    'stripePaymentMethodId' => $paymentMethodId,
]);
