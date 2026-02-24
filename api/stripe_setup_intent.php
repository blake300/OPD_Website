<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/stripe_service.php';

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
$label = trim((string) ($payload['label'] ?? ''));

$customerId = stripe_get_or_create_customer($user);
if (!$customerId) {
    opd_json_response(['error' => 'Unable to create Stripe customer'], 500);
}

$metadata = [
    'userId' => $user['id'] ?? '',
    'email' => $user['email'] ?? '',
];
if ($label !== '') {
    $metadata['label'] = $label;
}

$intent = stripe_create_setup_intent($customerId, $metadata);
if (!$intent['ok']) {
    opd_json_response(['error' => $intent['error'] ?? 'Stripe error'], 502);
}

opd_json_response([
    'clientSecret' => $intent['data']['client_secret'] ?? '',
    'customerId' => $customerId,
]);
