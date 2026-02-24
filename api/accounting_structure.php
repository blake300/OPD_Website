<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

// simple CSRF check for JSON API via header
function require_json_csrf(): void
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

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    $clientId = $_GET['clientId'] ?? null;
    $clientId = is_string($clientId) && $clientId !== '' ? $clientId : null;
    $data = site_get_accounting_structure_for_client($user['id'], $clientId);
    echo json_encode($data);
    exit;
}

if ($method === 'POST') {
    require_json_csrf();
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    // basic validation: keys must exist
    $required = ['location','code1','code2'];
    foreach ($required as $k) {
        if (!isset($json[$k]) || !is_array($json[$k])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing or invalid key: $k"]);
            exit;
        }
    }
    // requireSub is optional but should be an object if present
    if (isset($json['requireSub']) && !is_array($json['requireSub'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid requireSub format']);
        exit;
    }
    site_save_accounting_structure($user['id'], $json);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
