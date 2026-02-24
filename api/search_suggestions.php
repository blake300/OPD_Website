<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';

$query = trim((string) ($_GET['q'] ?? ''));
if ($query === '' || strlen($query) < 2) {
    header('Content-Type: application/json');
    echo json_encode(['results' => []]);
    exit;
}

$limit = 8;
if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
    $limit = (int) $_GET['limit'];
}
$limit = max(1, min(12, $limit));

$rows = site_get_product_suggestions($query, $limit);
$results = [];
foreach ($rows as $row) {
    $results[] = [
        'id' => (string) ($row['id'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'sku' => (string) ($row['sku'] ?? ''),
        'category' => (string) ($row['category'] ?? ''),
        'imageUrl' => (string) ($row['imageUrl'] ?? '')
    ];
}

header('Content-Type: application/json');
echo json_encode(['results' => $results]);
