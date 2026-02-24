<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/store.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    opd_require_role(['admin', 'manager']);
    $items = site_equipment_all_for_admin();

    // Enrich with sold/curQty data
    foreach ($items as &$item) {
        $sold = 0;
        if (!empty($item['productId'])) {
            $sold = site_equipment_sold_quantity($item['productId']);
        }
        $item['soldQty'] = $sold;
        $item['curQty'] = max(0, (int) ($item['quantity'] ?? 0) - $sold);
    }
    unset($item);

    opd_json_response(['items' => $items, 'total' => count($items)]);
}

if ($method === 'PUT') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $action = $payload['action'] ?? '';
    $equipmentId = $payload['equipmentId'] ?? '';

    if ($equipmentId === '') {
        opd_json_response(['error' => 'Missing equipmentId'], 400);
    }

    if ($action === 'approve') {
        $result = site_equipment_approve($equipmentId);
        if (!$result) {
            opd_json_response(['error' => 'Equipment not found'], 404);
        }
        opd_json_response(['ok' => true, 'productId' => $result['productId']]);
    }

    if ($action === 'decline') {
        site_equipment_decline($equipmentId);
        opd_json_response(['ok' => true]);
    }

    opd_json_response(['error' => 'Unknown action'], 400);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
