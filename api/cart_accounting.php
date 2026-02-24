<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

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

function resolve_client_id(string $userId, $clientId): ?string
{
    if (!is_string($clientId) || $clientId === '') {
        return null;
    }
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT id, status FROM clients WHERE id = ? AND userId = ? LIMIT 1');
    $stmt->execute([$clientId, $userId]);
    $row = $stmt->fetch();
    if (!$row || !site_client_is_billable($row)) {
        return null;
    }
    return $clientId;
}

$user = site_current_user();
if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Login required']);
    exit;
}

$cartId = site_get_cart_id($user['id']);
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $clientId = resolve_client_id($user['id'], $_GET['clientId'] ?? null);
    $data = site_get_cart_accounting($cartId, $clientId);
    if (!$data) {
        $data = ['groups' => [], 'assignments' => [], 'clientId' => $clientId];
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($method === 'POST') {
    require_json_csrf();
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $clientId = resolve_client_id($user['id'], $payload['clientId'] ?? null);
    $groupsRaw = $payload['groups'] ?? [];
    $assignmentsRaw = $payload['assignments'] ?? [];
    if (!is_array($groupsRaw) || !is_array($assignmentsRaw)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    $groups = [];
    foreach ($groupsRaw as $group) {
        if (!is_array($group)) {
            continue;
        }
        $groups[] = [
            'location' => (string) ($group['location'] ?? ''),
            'code1' => (string) ($group['code1'] ?? ''),
            'code2' => (string) ($group['code2'] ?? ''),
        ];
    }

    $items = site_cart_items_for_user($user['id']);
    $validKeys = [];
    foreach ($items as $item) {
        $validKeys[(string) ($item['key'] ?? '')] = true;
    }

    $assignments = [];
    foreach ($assignmentsRaw as $key => $entryList) {
        if (!isset($validKeys[(string) $key]) || !is_array($entryList)) {
            continue;
        }
        $normalized = [];
        foreach ($entryList as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $normalized[] = [
                'groupIndex' => isset($entry['groupIndex']) ? (int) $entry['groupIndex'] : 0,
                'qty' => isset($entry['qty']) ? (int) $entry['qty'] : 0,
            ];
        }
        if ($normalized) {
            $assignments[(string) $key] = $normalized;
        }
    }

    site_save_cart_accounting($cartId, $clientId, $groups, $assignments);
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
header('Content-Type: application/json');
echo json_encode(['error' => 'Method not allowed']);
