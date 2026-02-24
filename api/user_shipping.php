<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

function require_json_csrf_token(): void
{
    site_start_session();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $session = $_SESSION['site_csrf'] ?? '';
    if ($token === '' || $session === '' || !hash_equals($session, $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

$user = site_current_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Login required']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_json_csrf_token();

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$shippingAddress1 = trim((string) ($payload['shippingAddress1'] ?? ''));
$shippingAddress2 = trim((string) ($payload['shippingAddress2'] ?? ''));
$shippingCity = trim((string) ($payload['shippingCity'] ?? ''));
$shippingState = trim((string) ($payload['shippingState'] ?? ''));
$shippingPostcode = trim((string) ($payload['shippingPostcode'] ?? ''));

$pdo = opd_db();
$now = gmdate('Y-m-d H:i:s');
$stmt = $pdo->prepare(
    'UPDATE users SET shippingAddress1 = ?, shippingAddress2 = ?, shippingCity = ?, shippingState = ?, shippingPostcode = ?, updatedAt = ? WHERE id = ?'
);
$stmt->execute([
    $shippingAddress1 !== '' ? $shippingAddress1 : null,
    $shippingAddress2 !== '' ? $shippingAddress2 : null,
    $shippingCity !== '' ? $shippingCity : null,
    $shippingState !== '' ? $shippingState : null,
    $shippingPostcode !== '' ? $shippingPostcode : null,
    $now,
    $user['id']
]);

header('Content-Type: application/json');
echo json_encode([
    'ok' => true,
    'shipping' => [
        'shippingAddress1' => $shippingAddress1,
        'shippingAddress2' => $shippingAddress2,
        'shippingCity' => $shippingCity,
        'shippingState' => $shippingState,
        'shippingPostcode' => $shippingPostcode
    ]
]);
