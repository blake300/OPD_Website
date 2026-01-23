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
    if ($productId === '') {
        opd_json_response(['error' => 'Missing productId'], 400);
    }
    $stmt = $pdo->prepare('SELECT relatedProductId FROM product_associations WHERE productId = ?');
    $stmt->execute([$productId]);
    $ids = array_map(fn($row) => $row['relatedProductId'], $stmt->fetchAll());
    opd_json_response(['productId' => $productId, 'relatedProductIds' => $ids]);
}

if ($method === 'POST') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $productId = $payload['productId'] ?? '';
    $related = $payload['relatedProductIds'] ?? [];
    if ($productId === '' || !is_array($related)) {
        opd_json_response(['error' => 'Invalid payload'], 400);
    }

    $related = array_values(array_unique(array_filter($related, fn($id) => is_string($id) && $id !== '' && $id !== $productId)));
    $now = gmdate('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM product_associations WHERE productId = ?');
        $delete->execute([$productId]);

        if ($related) {
            $insert = $pdo->prepare(
                'INSERT INTO product_associations (id, productId, relatedProductId, createdAt, updatedAt)
                 VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($related as $relatedId) {
                $id = 'assoc-' . random_int(1000, 99999);
                $insert->execute([$id, $productId, $relatedId, $now, $now]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        opd_json_response(['error' => 'Failed to save associations'], 500);
    }

    opd_json_response(['productId' => $productId, 'relatedProductIds' => $related]);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
