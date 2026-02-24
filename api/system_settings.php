<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/store.php';

$keys = [
    'client_invite_sms' => 'clientInviteSms',
    'vendor_invite_sms' => 'vendorInviteSms',
    'auto_approve_help_text' => 'autoApproveHelpText',
    'auto_approve_time' => 'autoApproveTime',
    'vendor_limit_text' => 'vendorLimitText',
    'delivery_small_zone1' => 'deliverySmallZone1',
    'delivery_small_zone2' => 'deliverySmallZone2',
    'delivery_small_zone3' => 'deliverySmallZone3',
    'delivery_large_zone1' => 'deliveryLargeZone1',
    'delivery_large_zone2' => 'deliveryLargeZone2',
    'delivery_large_zone3' => 'deliveryLargeZone3',
    'my_equipment_text' => 'myEquipmentText',
];

function opd_fetch_system_settings(array $keys): array
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
    $result = opd_fetch_system_settings($keys);
    $item = array_merge(
        ['id' => 'system-settings', 'clientInviteSms' => '', 'vendorInviteSms' => '', 'autoApproveHelpText' => '', 'autoApproveTime' => '', 'vendorLimitText' => '', 'deliverySmallZone1' => '', 'deliverySmallZone2' => '', 'deliverySmallZone3' => '', 'deliveryLargeZone1' => '', 'deliveryLargeZone2' => '', 'deliveryLargeZone3' => '', 'myEquipmentText' => '', 'updatedAt' => $result['updatedAt']],
        $result['values']
    );
    opd_json_response(['items' => [$item], 'total' => 1]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $clientInviteSms = trim((string) ($payload['clientInviteSms'] ?? ''));
    $vendorInviteSms = trim((string) ($payload['vendorInviteSms'] ?? ''));
    $autoApproveHelpText = trim((string) ($payload['autoApproveHelpText'] ?? ''));
    $autoApproveTime = trim((string) ($payload['autoApproveTime'] ?? ''));
    $vendorLimitText = trim((string) ($payload['vendorLimitText'] ?? ''));
    $deliverySmallZone1 = trim((string) ($payload['deliverySmallZone1'] ?? ''));
    $deliverySmallZone2 = trim((string) ($payload['deliverySmallZone2'] ?? ''));
    $deliverySmallZone3 = trim((string) ($payload['deliverySmallZone3'] ?? ''));
    $deliveryLargeZone1 = trim((string) ($payload['deliveryLargeZone1'] ?? ''));
    $deliveryLargeZone2 = trim((string) ($payload['deliveryLargeZone2'] ?? ''));
    $deliveryLargeZone3 = trim((string) ($payload['deliveryLargeZone3'] ?? ''));
    $myEquipmentText = trim((string) ($payload['myEquipmentText'] ?? ''));

    if ($clientInviteSms === '' || $vendorInviteSms === '') {
        opd_json_response(['error' => 'Client and vendor invite messages are required.'], 400);
    }

    site_set_setting_value('client_invite_sms', $clientInviteSms);
    site_set_setting_value('vendor_invite_sms', $vendorInviteSms);
    site_set_setting_value('auto_approve_help_text', $autoApproveHelpText === '' ? null : $autoApproveHelpText);
    site_set_setting_value('auto_approve_time', $autoApproveTime === '' ? null : $autoApproveTime);
    site_set_setting_value('vendor_limit_text', $vendorLimitText === '' ? null : $vendorLimitText);
    site_set_setting_value('delivery_small_zone1', $deliverySmallZone1 === '' ? null : $deliverySmallZone1);
    site_set_setting_value('delivery_small_zone2', $deliverySmallZone2 === '' ? null : $deliverySmallZone2);
    site_set_setting_value('delivery_small_zone3', $deliverySmallZone3 === '' ? null : $deliverySmallZone3);
    site_set_setting_value('delivery_large_zone1', $deliveryLargeZone1 === '' ? null : $deliveryLargeZone1);
    site_set_setting_value('delivery_large_zone2', $deliveryLargeZone2 === '' ? null : $deliveryLargeZone2);
    site_set_setting_value('delivery_large_zone3', $deliveryLargeZone3 === '' ? null : $deliveryLargeZone3);
    site_set_setting_value('my_equipment_text', $myEquipmentText === '' ? null : $myEquipmentText);

    $item = [
        'id' => 'system-settings',
        'clientInviteSms' => $clientInviteSms,
        'vendorInviteSms' => $vendorInviteSms,
        'autoApproveHelpText' => $autoApproveHelpText,
        'autoApproveTime' => $autoApproveTime,
        'vendorLimitText' => $vendorLimitText,
        'deliverySmallZone1' => $deliverySmallZone1,
        'deliverySmallZone2' => $deliverySmallZone2,
        'deliverySmallZone3' => $deliverySmallZone3,
        'deliveryLargeZone1' => $deliveryLargeZone1,
        'deliveryLargeZone2' => $deliveryLargeZone2,
        'deliveryLargeZone3' => $deliveryLargeZone3,
        'myEquipmentText' => $myEquipmentText,
        'updatedAt' => gmdate('Y-m-d H:i:s'),
    ];
    $status = $_SERVER['REQUEST_METHOD'] === 'POST' ? 201 : 200;
    opd_json_response($item, $status);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    opd_require_role(['admin']);
    opd_require_csrf();
    site_set_setting_value('client_invite_sms', null);
    site_set_setting_value('vendor_invite_sms', null);
    site_set_setting_value('auto_approve_help_text', null);
    site_set_setting_value('auto_approve_time', null);
    site_set_setting_value('vendor_limit_text', null);
    site_set_setting_value('delivery_small_zone1', null);
    site_set_setting_value('delivery_small_zone2', null);
    site_set_setting_value('delivery_small_zone3', null);
    site_set_setting_value('delivery_large_zone1', null);
    site_set_setting_value('delivery_large_zone2', null);
    site_set_setting_value('delivery_large_zone3', null);
    site_set_setting_value('my_equipment_text', null);
    opd_json_response(['ok' => true]);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
