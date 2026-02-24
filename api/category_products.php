<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';

header('Content-Type: application/json');

$category = trim((string) ($_GET['category'] ?? ''));
$limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 78;
$offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

$limit = max(1, min($limit, 200));
$offset = max(0, $offset);

if ($category === '') {
    echo json_encode(['items' => [], 'hasMore' => false, 'nextOffset' => $offset]);
    exit;
}

$items = site_get_products($category, null, $limit + 1, $offset);
$hasMore = count($items) > $limit;
if ($hasMore) {
    $items = array_slice($items, 0, $limit);
}

$ids = array_values(array_filter(array_map(fn($item) => $item['id'] ?? null, $items)));
$variantCounts = [];
$assocCounts = [];
if ($ids) {
    $pdo = opd_db();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $variantStmt = $pdo->prepare(
        "SELECT productId, COUNT(*) AS total FROM product_variants WHERE productId IN ({$placeholders}) GROUP BY productId"
    );
    $variantStmt->execute($ids);
    foreach ($variantStmt->fetchAll() as $row) {
        $variantCounts[(string) ($row['productId'] ?? '')] = (int) ($row['total'] ?? 0);
    }
    $assocStmt = $pdo->prepare(
        "SELECT productId, COUNT(*) AS total FROM product_associations WHERE productId IN ({$placeholders}) GROUP BY productId"
    );
    $assocStmt->execute($ids);
    foreach ($assocStmt->fetchAll() as $row) {
        $assocCounts[(string) ($row['productId'] ?? '')] = (int) ($row['total'] ?? 0);
    }
}

$normalized = [];
foreach ($items as $item) {
    $id = (string) ($item['id'] ?? '');
    $normalized[] = array_merge($item, [
        'hasVariants' => ($variantCounts[$id] ?? 0) > 0,
        'hasAssociations' => ($assocCounts[$id] ?? 0) > 0,
    ]);
}

echo json_encode([
    'items' => $normalized,
    'hasMore' => $hasMore,
    'nextOffset' => $offset + count($normalized),
]);
