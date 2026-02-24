<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db_conn.php';
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
    $stmt = $pdo->prepare(
        'SELECT relatedProductId, sortOrder FROM product_associations
         WHERE productId = ?
         ORDER BY (sortOrder IS NULL), sortOrder ASC, createdAt ASC'
    );
    $stmt->execute([$productId]);
    $rows = $stmt->fetchAll();
    $ids = array_map(fn($row) => $row['relatedProductId'], $rows);
    $orderMap = [];
    foreach ($rows as $row) {
        $orderMap[(string) ($row['relatedProductId'] ?? '')] = $row['sortOrder'] ?? null;
    }
    $variantIds = [];
    if ($ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $variants = $pdo->prepare("SELECT id FROM product_variants WHERE productId IN ({$placeholders})");
        $variants->execute($ids);
        $variantIds = array_map(fn($row) => $row['id'], $variants->fetchAll());
    }
    opd_json_response([
        'productId' => $productId,
        'relatedProductIds' => $ids,
        'relatedOrders' => $orderMap,
        'related' => array_map(fn($row) => [
            'id' => $row['relatedProductId'] ?? null,
            'sortOrder' => $row['sortOrder'] ?? null,
        ], $rows),
        'relatedVariantIds' => $variantIds,
    ]);
}

if ($method === 'POST') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $productId = $payload['productId'] ?? '';
    $relatedRaw = $payload['related'] ?? ($payload['relatedProductIds'] ?? []);
    if ($productId === '' || !is_array($relatedRaw)) {
        opd_json_response(['error' => 'Invalid payload'], 400);
    }

    $related = [];
    $sortIndex = 1;
    foreach ($relatedRaw as $entry) {
        if (is_array($entry)) {
            $id = (string) ($entry['id'] ?? ($entry['relatedProductId'] ?? ''));
            if ($id === '' || $id === $productId) {
                continue;
            }
            $sortOrder = isset($entry['sortOrder']) ? (int) $entry['sortOrder'] : $sortIndex;
            $related[$id] = $sortOrder;
            $sortIndex += 1;
            continue;
        }
        if (is_string($entry) && $entry !== '' && $entry !== $productId) {
            $related[$entry] = $sortIndex;
            $sortIndex += 1;
        }
    }
    $related = array_values(array_map(
        fn($id, $sortOrder) => ['id' => $id, 'sortOrder' => $sortOrder],
        array_keys($related),
        array_values($related)
    ));
    $now = gmdate('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $delete = $pdo->prepare('DELETE FROM product_associations WHERE productId = ?');
        $delete->execute([$productId]);

        if ($related) {
            $insert = $pdo->prepare(
                'INSERT INTO product_associations (id, productId, relatedProductId, sortOrder, createdAt, updatedAt)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($related as $entry) {
                $relatedId = (string) ($entry['id'] ?? '');
                if ($relatedId === '') {
                    continue;
                }
                $id = opd_generate_id('assoc');
                $insert->execute([
                    $id,
                    $productId,
                    $relatedId,
                    $entry['sortOrder'] ?? null,
                    $now,
                    $now,
                ]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        opd_json_response(['error' => 'Failed to save associations'], 500);
    }

    $variantIds = [];
    if ($related) {
        $relatedIds = array_map(fn($entry) => (string) ($entry['id'] ?? ''), $related);
        $relatedIds = array_values(array_filter($relatedIds, fn($id) => $id !== ''));
        $placeholders = implode(',', array_fill(0, count($relatedIds), '?'));
        $variants = $pdo->prepare("SELECT id FROM product_variants WHERE productId IN ({$placeholders})");
        $variants->execute($relatedIds);
        $variantIds = array_map(fn($row) => $row['id'], $variants->fetchAll());
    }

    opd_json_response([
        'productId' => $productId,
        'relatedProductIds' => array_map(fn($entry) => $entry['id'], $related),
        'related' => $related,
        'relatedVariantIds' => $variantIds,
    ]);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
