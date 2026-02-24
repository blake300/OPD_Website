<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/store.php';

$user = site_require_auth();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $equipmentId = $_GET['equipmentId'] ?? '';
    if ($equipmentId === '') {
        opd_json_response(['error' => 'Missing equipmentId'], 400);
    }
    // Verify ownership
    $equipment = site_equipment_get($equipmentId);
    if (!$equipment || $equipment['userId'] !== $user['id']) {
        opd_json_response(['error' => 'Not found'], 404);
    }
    $images = site_equipment_images($equipmentId);
    opd_json_response(['images' => $images]);
}

if ($method === 'PUT') {
    site_require_csrf();
    $payload = opd_read_json();
    $action = $payload['action'] ?? '';
    $equipmentId = $payload['equipmentId'] ?? '';
    $imageId = $payload['imageId'] ?? '';

    if ($equipmentId === '') {
        opd_json_response(['error' => 'Missing equipmentId'], 400);
    }
    $equipment = site_equipment_get($equipmentId);
    if (!$equipment || $equipment['userId'] !== $user['id']) {
        opd_json_response(['error' => 'Not found'], 404);
    }

    if ($action === 'setPrimary' && $imageId !== '') {
        site_equipment_set_primary_image($equipmentId, $imageId);
        opd_json_response(['ok' => true]);
    }

    opd_json_response(['error' => 'Unknown action'], 400);
}

if ($method === 'DELETE') {
    site_require_csrf();
    $equipmentId = $_GET['equipmentId'] ?? '';
    $imageId = $_GET['imageId'] ?? '';

    if ($equipmentId === '' || $imageId === '') {
        opd_json_response(['error' => 'Missing parameters'], 400);
    }
    $equipment = site_equipment_get($equipmentId);
    if (!$equipment || $equipment['userId'] !== $user['id']) {
        opd_json_response(['error' => 'Not found'], 404);
    }

    site_equipment_delete_image($imageId, $equipmentId);
    opd_json_response(['ok' => true]);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
