<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/store.php';

$keys = [
    'shipping_zone1_states' => 'shippingZone1States',
    'shipping_zone1_flat' => 'shippingZone1Flat',
    'shipping_zone1_per_lb' => 'shippingZone1PerLb',
    'shipping_zone2_states' => 'shippingZone2States',
    'shipping_zone2_flat' => 'shippingZone2Flat',
    'shipping_zone2_per_lb' => 'shippingZone2PerLb',
    'shipping_zone3_states' => 'shippingZone3States',
    'shipping_zone3_flat' => 'shippingZone3Flat',
    'shipping_zone3_per_lb' => 'shippingZone3PerLb',
];

function opd_fetch_shipping_settings(array $keys): array
{
    $pdo = opd_db();
    $placeholders = implode(',', array_fill(0, count($keys), '?'));
    $stmt = $pdo->prepare(
        "SELECT `key`, value, updatedAt FROM settings WHERE `key` IN ({$placeholders})"
    );
    $stmt->execute(array_keys($keys));
    $rows = $stmt->fetchAll();

    $values = [];
    $updatedAt = '';
    foreach ($rows as $row) {
        $key = (string) ($row['key'] ?? '');
        if ($key === '' || !isset($keys[$key])) {
            continue;
        }
        $values[$keys[$key]] = (string) ($row['value'] ?? '');
        $rowUpdated = (string) ($row['updatedAt'] ?? '');
        if ($rowUpdated !== '' && ($updatedAt === '' || $rowUpdated > $updatedAt)) {
            $updatedAt = $rowUpdated;
        }
    }
    return [
        'values' => $values,
        'updatedAt' => $updatedAt,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    opd_require_role(['admin', 'manager']);
    $result = opd_fetch_shipping_settings($keys);
    $item = array_merge(
        ['id' => 'shipping-settings', 'shippingZone1States' => '', 'shippingZone1Flat' => '', 'shippingZone1PerLb' => '', 'shippingZone2States' => '', 'shippingZone2Flat' => '', 'shippingZone2PerLb' => '', 'shippingZone3States' => '', 'shippingZone3Flat' => '', 'shippingZone3PerLb' => '', 'updatedAt' => $result['updatedAt']],
        $result['values']
    );
    opd_json_response(['items' => [$item], 'total' => 1]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $shippingZone1States = trim((string) ($payload['shippingZone1States'] ?? ''));
    $shippingZone1Flat = trim((string) ($payload['shippingZone1Flat'] ?? ''));
    $shippingZone1PerLb = trim((string) ($payload['shippingZone1PerLb'] ?? ''));
    $shippingZone2States = trim((string) ($payload['shippingZone2States'] ?? ''));
    $shippingZone2Flat = trim((string) ($payload['shippingZone2Flat'] ?? ''));
    $shippingZone2PerLb = trim((string) ($payload['shippingZone2PerLb'] ?? ''));
    $shippingZone3States = trim((string) ($payload['shippingZone3States'] ?? ''));
    $shippingZone3Flat = trim((string) ($payload['shippingZone3Flat'] ?? ''));
    $shippingZone3PerLb = trim((string) ($payload['shippingZone3PerLb'] ?? ''));

    site_set_setting_value('shipping_zone1_states', $shippingZone1States === '' ? null : $shippingZone1States);
    site_set_setting_value('shipping_zone1_flat', $shippingZone1Flat === '' ? null : $shippingZone1Flat);
    site_set_setting_value('shipping_zone1_per_lb', $shippingZone1PerLb === '' ? null : $shippingZone1PerLb);
    site_set_setting_value('shipping_zone2_states', $shippingZone2States === '' ? null : $shippingZone2States);
    site_set_setting_value('shipping_zone2_flat', $shippingZone2Flat === '' ? null : $shippingZone2Flat);
    site_set_setting_value('shipping_zone2_per_lb', $shippingZone2PerLb === '' ? null : $shippingZone2PerLb);
    site_set_setting_value('shipping_zone3_states', $shippingZone3States === '' ? null : $shippingZone3States);
    site_set_setting_value('shipping_zone3_flat', $shippingZone3Flat === '' ? null : $shippingZone3Flat);
    site_set_setting_value('shipping_zone3_per_lb', $shippingZone3PerLb === '' ? null : $shippingZone3PerLb);

    $item = [
        'id' => 'shipping-settings',
        'shippingZone1States' => $shippingZone1States,
        'shippingZone1Flat' => $shippingZone1Flat,
        'shippingZone1PerLb' => $shippingZone1PerLb,
        'shippingZone2States' => $shippingZone2States,
        'shippingZone2Flat' => $shippingZone2Flat,
        'shippingZone2PerLb' => $shippingZone2PerLb,
        'shippingZone3States' => $shippingZone3States,
        'shippingZone3Flat' => $shippingZone3Flat,
        'shippingZone3PerLb' => $shippingZone3PerLb,
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ];
    $status = $_SERVER['REQUEST_METHOD'] === 'POST' ? 201 : 200;
    opd_json_response($item, $status);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    opd_require_role(['admin']);
    opd_require_csrf();
    foreach (array_keys($keys) as $dbKey) {
        site_set_setting_value($dbKey, null);
    }
    opd_json_response(['ok' => true]);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
