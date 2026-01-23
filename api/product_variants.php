<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/auth.php';

$pdo = opd_db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    opd_require_role(['admin', 'manager']);
    $productId = $_GET['productId'] ?? '';
    if ($productId !== '') {
        $stmt = $pdo->prepare('SELECT * FROM product_variants WHERE productId = ? ORDER BY updatedAt DESC');
        $stmt->execute([$productId]);
        $items = $stmt->fetchAll();
    } else {
        $stmt = $pdo->query('SELECT * FROM product_variants ORDER BY updatedAt DESC');
        $items = $stmt->fetchAll();
    }
    opd_json_response(['items' => $items, 'total' => count($items)]);
}

if ($method === 'POST') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $productId = $payload['productId'] ?? '';
    $name = $payload['name'] ?? '';
    $sku = $payload['sku'] ?? '';
    if ($productId === '' || $name === '' || $sku === '') {
        opd_json_response(['error' => 'Missing required fields'], 400);
    }

    $id = 'var-' . random_int(1000, 99999);
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'INSERT INTO product_variants (id, productId, name, sku, price, inventory, status, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id,
        $productId,
        $name,
        $sku,
        $payload['price'] ?? null,
        $payload['inventory'] ?? null,
        $payload['status'] ?? null,
        $now,
        $now,
    ]);
    $row = $pdo->prepare('SELECT * FROM product_variants WHERE id = ?');
    $row->execute([$id]);
    opd_json_response($row->fetch() ?: [], 201);
}

if ($method === 'PUT') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $id = $_GET['id'] ?? ($payload['id'] ?? '');
    if ($id === '') {
        opd_json_response(['error' => 'Missing id'], 400);
    }
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'UPDATE product_variants
         SET productId = ?, name = ?, sku = ?, price = ?, inventory = ?, status = ?, updatedAt = ?
         WHERE id = ?'
    );
    $stmt->execute([
        $payload['productId'] ?? null,
        $payload['name'] ?? null,
        $payload['sku'] ?? null,
        $payload['price'] ?? null,
        $payload['inventory'] ?? null,
        $payload['status'] ?? null,
        $now,
        $id,
    ]);
    if ($stmt->rowCount() === 0) {
        opd_json_response(['error' => 'Not found'], 404);
    }
    $row = $pdo->prepare('SELECT * FROM product_variants WHERE id = ?');
    $row->execute([$id]);
    opd_json_response($row->fetch() ?: []);
}

if ($method === 'DELETE') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $id = $_GET['id'] ?? '';
    if ($id === '') {
        opd_json_response(['error' => 'Missing id'], 400);
    }
    $stmt = $pdo->prepare('DELETE FROM product_variants WHERE id = ?');
    $stmt->execute([$id]);
    opd_json_response(['ok' => $stmt->rowCount() > 0]);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
