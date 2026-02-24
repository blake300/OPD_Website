<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

function order_details_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function order_details_require_json_csrf(): void
{
    site_start_session();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $session = $_SESSION['site_csrf'] ?? '';
    if ($token === '' || $session === '' || !hash_equals($session, $token)) {
        order_details_json_response(['error' => 'Invalid CSRF token'], 403);
    }
}

$user = site_current_user();
if (!$user) {
    order_details_json_response(['error' => 'Login required'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = opd_db();

if ($method === 'GET') {
    $orderId = trim((string) ($_GET['orderId'] ?? ''));
    if ($orderId === '') {
        order_details_json_response(['error' => 'Missing orderId'], 400);
    }
    $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    if (!$order) {
        order_details_json_response(['error' => 'Order not found'], 404);
    }
    $orderUserId = (string) ($order['userId'] ?? '');
    $orderClientUserId = (string) ($order['clientUserId'] ?? '');
    if ($orderUserId !== $user['id'] && $orderClientUserId !== $user['id']) {
        order_details_json_response(['error' => 'Forbidden'], 403);
    }

    $itemStmt = $pdo->prepare(
        'SELECT oi.*, COALESCE(pi.url, p.imageUrl) AS imageUrl
         FROM order_items oi
         JOIN products p ON p.id = oi.productId
         LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
         WHERE oi.orderId = ?
         ORDER BY oi.createdAt ASC'
    );
    $itemStmt->execute([$orderId]);
    $items = [];
    $orderAmount = 0.0;
    foreach ($itemStmt->fetchAll() as $row) {
        $price = (float) ($row['price'] ?? 0);
        $qty = (int) ($row['quantity'] ?? 0);
        $total = $row['total'] ?? null;
        $itemTotal = $total !== null ? (float) $total : $price * $qty;
        $orderAmount += $itemTotal;
        $items[] = [
            'id' => (string) ($row['id'] ?? ''),
            'productId' => (string) ($row['productId'] ?? ''),
            'variantId' => $row['variantId'] ?? null,
            'name' => (string) ($row['name'] ?? ''),
            'price' => $price,
            'quantity' => $qty,
            'total' => $itemTotal,
            'arrivalDate' => $row['arrivalDate'] ?? null,
            'imageUrl' => $row['imageUrl'] ?? null,
        ];
    }

    $orderTotal = (float) ($order['total'] ?? $orderAmount);
    $orderTax = null;
    $orderShipping = null;
    if (array_key_exists('tax', $order) && $order['tax'] !== null) {
        $orderTax = (float) $order['tax'];
    }
    if (array_key_exists('shipping', $order) && $order['shipping'] !== null) {
        $orderShipping = (float) $order['shipping'];
    }
    if ($orderTax === null && $orderShipping === null) {
        $orderTax = max($orderTotal - $orderAmount, 0.0);
        $orderShipping = 0.0;
    } elseif ($orderTax === null) {
        $orderTax = max($orderTotal - $orderAmount - (float) $orderShipping, 0.0);
    } elseif ($orderShipping === null) {
        $orderShipping = max($orderTotal - $orderAmount - (float) $orderTax, 0.0);
    }

    $accStmt = $pdo->prepare(
        'SELECT * FROM order_accounting WHERE orderId = ? ORDER BY updatedAt DESC LIMIT 1'
    );
    $accStmt->execute([$orderId]);
    $accountingRow = $accStmt->fetch();
    $groups = [];
    $assignments = [];
    if ($accountingRow) {
        $groups = json_decode($accountingRow['groupsJson'] ?? '[]', true);
        $assignments = json_decode($accountingRow['assignmentsJson'] ?? '[]', true);
    }
    if (!is_array($groups)) {
        $groups = [];
    }
    if (!is_array($assignments)) {
        $assignments = [];
    }
    if (!$groups) {
        $groups = [[
            'location' => '',
            'code1' => '',
            'code2' => '',
        ]];
    }

    $itemIds = array_map(fn($item) => $item['id'], $items);
    $itemIdSet = array_fill_keys($itemIds, true);
    $hasMatch = false;
    foreach ($assignments as $key => $_) {
        if (isset($itemIdSet[$key])) {
            $hasMatch = true;
            break;
        }
    }
    if (!$hasMatch) {
        $assignments = [];
    }
    foreach ($items as $item) {
        $itemId = (string) ($item['id'] ?? '');
        if ($itemId === '') {
            continue;
        }
        if (!isset($assignments[$itemId]) || !is_array($assignments[$itemId]) || !$assignments[$itemId]) {
            $assignments[$itemId] = [[
                'groupIndex' => 0,
                'qty' => (int) ($item['quantity'] ?? 1),
            ]];
        }
    }

    foreach ($assignments as $itemId => $rows) {
        if (!is_array($rows)) {
            $assignments[$itemId] = [];
            continue;
        }
        $normalized = [];
        foreach ($rows as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $index = (int) ($entry['groupIndex'] ?? 0);
            if ($index < 0 || $index >= count($groups)) {
                $index = 0;
            }
            $qty = (int) ($entry['qty'] ?? 0);
            if ($qty < 0) {
                $qty = 0;
            }
            $normalized[] = [
                'groupIndex' => $index,
                'qty' => $qty,
            ];
        }
        if (!$normalized) {
            $normalized[] = ['groupIndex' => 0, 'qty' => 0];
        }
        $assignments[$itemId] = $normalized;
    }

    $structureUserId = $orderUserId !== '' ? $orderUserId : $user['id'];
    $clientId = $order['clientId'] ?? null;
    $accountingStructure = $clientId
        ? site_get_accounting_structure_for_client($structureUserId, (string) $clientId)
        : site_get_accounting_structure($structureUserId);

    order_details_json_response([
        'order' => [
            'id' => $orderId,
            'number' => $order['number'] ?? $orderId,
            'total' => $orderTotal,
            'amount' => $orderAmount,
            'tax' => $orderTax,
            'shipping' => $orderShipping,
            'currency' => $order['currency'] ?? 'USD',
        ],
        'items' => $items,
        'accounting' => [
            'groups' => $groups,
            'assignments' => $assignments,
        ],
        'accountingStructure' => $accountingStructure,
    ]);
}

if ($method === 'POST') {
    order_details_require_json_csrf();
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        order_details_json_response(['error' => 'Invalid JSON'], 400);
    }
    $orderId = trim((string) ($payload['orderId'] ?? ''));
    if ($orderId === '') {
        order_details_json_response(['error' => 'Missing orderId'], 400);
    }
    $orderStmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    if (!$order) {
        order_details_json_response(['error' => 'Order not found'], 404);
    }
    $orderUserId = (string) ($order['userId'] ?? '');
    $orderClientUserId = (string) ($order['clientUserId'] ?? '');
    if ($orderUserId !== $user['id'] && $orderClientUserId !== $user['id']) {
        order_details_json_response(['error' => 'Forbidden'], 403);
    }

    $groups = is_array($payload['groups'] ?? null) ? $payload['groups'] : [];
    $assignments = is_array($payload['assignments'] ?? null) ? $payload['assignments'] : [];
    $normalizedGroups = [];
    foreach ($groups as $group) {
        if (!is_array($group)) {
            continue;
        }
        $normalizedGroups[] = [
            'location' => trim((string) ($group['location'] ?? '')),
            'code1' => trim((string) ($group['code1'] ?? '')),
            'code2' => trim((string) ($group['code2'] ?? '')),
        ];
    }
    if (!$normalizedGroups) {
        $normalizedGroups = [[
            'location' => '',
            'code1' => '',
            'code2' => '',
        ]];
    }

    $normalizedAssignments = [];
    foreach ($assignments as $itemId => $rows) {
        if (!is_array($rows)) {
            continue;
        }
        $normalizedRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $index = (int) ($row['groupIndex'] ?? 0);
            if ($index < 0 || $index >= count($normalizedGroups)) {
                $index = 0;
            }
            $qty = (int) ($row['qty'] ?? 0);
            if ($qty < 0) {
                $qty = 0;
            }
            $normalizedRows[] = [
                'groupIndex' => $index,
                'qty' => $qty,
            ];
        }
        if ($normalizedRows) {
            $normalizedAssignments[$itemId] = $normalizedRows;
        }
    }

    site_save_order_accounting($orderId, $order['clientId'] ?? null, $normalizedGroups, $normalizedAssignments);
    order_details_json_response(['ok' => true]);
}

order_details_json_response(['error' => 'Method not allowed'], 405);
