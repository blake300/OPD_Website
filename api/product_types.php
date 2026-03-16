<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/db_conn.php';

opd_require_role(['admin', 'manager']);

$pdo = opd_db();

// Get variant counts per product
$variantCounts = [];
$stmt = $pdo->query('SELECT productId, COUNT(*) AS cnt FROM product_variants GROUP BY productId');
foreach ($stmt->fetchAll() as $row) {
    $variantCounts[$row['productId']] = (int) $row['cnt'];
}

// Get association counts per product
$assocCounts = [];
$tableCheck = $pdo->prepare(
    "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'product_associations'"
);
$tableCheck->execute();
if ($tableCheck->fetch()) {
    $stmt = $pdo->query('SELECT productId, COUNT(*) AS cnt FROM product_associations GROUP BY productId');
    foreach ($stmt->fetchAll() as $row) {
        $assocCounts[$row['productId']] = (int) $row['cnt'];
    }
}

// Build type map
$types = [];
$stmt = $pdo->query('SELECT id FROM products');
foreach ($stmt->fetchAll() as $row) {
    $id = $row['id'];
    $hasVariants = ($variantCounts[$id] ?? 0) > 0;
    $hasAssoc = ($assocCounts[$id] ?? 0) > 0;

    if ($hasVariants && $hasAssoc) {
        $type = 'Combo';
    } elseif ($hasVariants) {
        $type = 'Variant';
    } elseif ($hasAssoc) {
        $type = 'Associated';
    } else {
        $type = 'Simple';
    }
    $types[$id] = $type;
}

opd_json_response(['types' => $types]);
