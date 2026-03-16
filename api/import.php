<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/db_conn.php';
require_once __DIR__ . '/../src/store.php';

opd_require_role(['admin']);
opd_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    opd_json_response(['error' => 'Method Not Allowed'], 405);
}

$importType = $_POST['importType'] ?? '';
$mode = $_POST['mode'] ?? '';

if (!in_array($importType, ['products', 'variants', 'images'], true)) {
    opd_json_response(['error' => 'Invalid importType. Use: products, variants, images'], 400);
}
if (!in_array($mode, ['add', 'update'], true)) {
    opd_json_response(['error' => 'Invalid mode. Use: add, update'], 400);
}

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    opd_json_response(['error' => 'Missing or invalid CSV file'], 400);
}

$file = $_FILES['csv'];
$maxBytes = 10 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxBytes) {
    opd_json_response(['error' => 'CSV file too large (max 10MB)'], 400);
}

$handle = fopen($file['tmp_name'], 'r');
if ($handle === false) {
    opd_json_response(['error' => 'Unable to read CSV file'], 400);
}

// Read BOM if present
$bom = fread($handle, 3);
if ($bom !== "\xEF\xBB\xBF") {
    rewind($handle);
}

$headers = fgetcsv($handle);
if ($headers === false || !$headers) {
    fclose($handle);
    opd_json_response(['error' => 'CSV file is empty or has no headers'], 400);
}

// Trim whitespace from headers
$headers = array_map('trim', $headers);

$rows = [];
while (($row = fgetcsv($handle)) !== false) {
    if (count($row) !== count($headers)) {
        continue;
    }
    $rows[] = array_combine($headers, array_map('trim', $row));
}
fclose($handle);

if (!$rows) {
    opd_json_response(['error' => 'CSV file contains no data rows'], 400);
}

$pdo = opd_db();

// ---- Import Products ----
if ($importType === 'products') {
    $allowedColumns = [
        'name', 'sku', 'imageUrl', 'price', 'status', 'service', 'largeDelivery',
        'daysOut', 'posNum', 'inventory', 'invStockTo', 'invMin', 'category',
        'shortDescription', 'longDescription', 'wgt', 'lng', 'wdth', 'hght',
        'tags', 'vnName', 'vnContact', 'vnPrice', 'compName', 'compPrice',
        'shelfNum', 'featured'
    ];

    $numericFields = [
        'price', 'inventory', 'invStockTo', 'invMin', 'posNum', 'daysOut',
        'wgt', 'lng', 'wdth', 'hght', 'vnPrice', 'compPrice'
    ];
    $boolFields = ['service', 'largeDelivery', 'featured'];

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $i => $row) {
        $lineNum = $i + 2;
        $sku = $row['sku'] ?? '';
        if ($sku === '') {
            $errors[] = "Row {$lineNum}: Missing SKU, skipped.";
            $skipped++;
            continue;
        }

        // Check if product with this SKU exists
        $existingStmt = $pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
        $existingStmt->execute([$sku]);
        $existing = $existingStmt->fetch();

        if ($mode === 'add' && $existing) {
            $skipped++;
            continue;
        }

        if ($mode === 'update' && !$existing) {
            $skipped++;
            continue;
        }

        // Build data array from CSV columns
        $data = [];
        foreach ($allowedColumns as $col) {
            if (!isset($row[$col]) || $row[$col] === '') {
                continue;
            }
            $val = $row[$col];
            if (in_array($col, $numericFields, true)) {
                $val = is_numeric($val) ? $val + 0 : null;
            } elseif (in_array($col, $boolFields, true)) {
                $val = in_array(strtolower($val), ['1', 'true', 'yes'], true) ? 1 : 0;
            }
            if ($val !== null) {
                $data[$col] = $val;
            }
        }

        if ($mode === 'add') {
            $name = $data['name'] ?? '';
            $category = $data['category'] ?? '';
            if ($name === '' || $category === '') {
                $errors[] = "Row {$lineNum}: Missing name or category for new product, skipped.";
                $skipped++;
                continue;
            }
            $id = opd_generate_id('prod');
            $now = gmdate('Y-m-d H:i:s');
            $data['id'] = $id;
            $data['sku'] = $sku;
            $data['createdAt'] = $now;
            $data['updatedAt'] = $now;

            $cols = array_keys($data);
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $colNames = implode(', ', $cols);
            $stmt = $pdo->prepare("INSERT INTO products ({$colNames}) VALUES ({$placeholders})");
            $stmt->execute(array_values($data));
            $created++;
        } else {
            // Update mode
            if (empty($data)) {
                $skipped++;
                continue;
            }
            $data['updatedAt'] = gmdate('Y-m-d H:i:s');
            $sets = [];
            $vals = [];
            foreach ($data as $col => $val) {
                $sets[] = "{$col} = ?";
                $vals[] = $val;
            }
            $vals[] = $existing['id'];
            $stmt = $pdo->prepare("UPDATE products SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($vals);
            $updated++;
        }
    }

    opd_json_response([
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'total' => count($rows),
    ]);
}

// ---- Import Variants ----
if ($importType === 'variants') {
    $allowedColumns = [
        'name', 'sku', 'price', 'largeDelivery', 'inventory', 'allowBackorders',
        'invStockTo', 'invMin', 'status', 'posNum', 'shortDescription', 'longDescription',
        'wgt', 'lng', 'wdth', 'hght', 'tags', 'vnName', 'vnContact', 'vnPrice',
        'compName', 'compPrice', 'shelfNum'
    ];

    $numericFields = [
        'price', 'inventory', 'invStockTo', 'invMin', 'posNum',
        'wgt', 'lng', 'wdth', 'hght', 'vnPrice', 'compPrice'
    ];
    $boolFields = ['largeDelivery', 'allowBackorders'];

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $i => $row) {
        $lineNum = $i + 2;
        $parentSku = $row['parentSku'] ?? '';
        $variantSku = $row['sku'] ?? '';

        if ($parentSku === '') {
            $errors[] = "Row {$lineNum}: Missing parentSku, skipped.";
            $skipped++;
            continue;
        }
        if ($variantSku === '') {
            $errors[] = "Row {$lineNum}: Missing variant sku, skipped.";
            $skipped++;
            continue;
        }

        // Find parent product by SKU
        $parentStmt = $pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
        $parentStmt->execute([$parentSku]);
        $parent = $parentStmt->fetch();
        if (!$parent) {
            $errors[] = "Row {$lineNum}: Parent SKU '{$parentSku}' not found, skipped.";
            $skipped++;
            continue;
        }
        $productId = $parent['id'];

        // Check if variant with this SKU exists
        $existingStmt = $pdo->prepare('SELECT id FROM product_variants WHERE sku = ? LIMIT 1');
        $existingStmt->execute([$variantSku]);
        $existing = $existingStmt->fetch();

        if ($mode === 'add' && $existing) {
            $skipped++;
            continue;
        }
        if ($mode === 'update' && !$existing) {
            $skipped++;
            continue;
        }

        // Build data
        $data = [];
        foreach ($allowedColumns as $col) {
            if (!isset($row[$col]) || $row[$col] === '') {
                continue;
            }
            $val = $row[$col];
            if (in_array($col, $numericFields, true)) {
                $val = is_numeric($val) ? $val + 0 : null;
            } elseif (in_array($col, $boolFields, true)) {
                $val = in_array(strtolower($val), ['1', 'true', 'yes'], true) ? 1 : 0;
            }
            if ($val !== null) {
                $data[$col] = $val;
            }
        }

        if ($mode === 'add') {
            $name = $data['name'] ?? '';
            if ($name === '') {
                $errors[] = "Row {$lineNum}: Missing variant name, skipped.";
                $skipped++;
                continue;
            }
            $id = opd_generate_id('var');
            $now = gmdate('Y-m-d H:i:s');
            $data['id'] = $id;
            $data['productId'] = $productId;
            $data['sku'] = $variantSku;
            $data['createdAt'] = $now;
            $data['updatedAt'] = $now;
            if (!isset($data['allowBackorders'])) {
                $data['allowBackorders'] = 1;
            }

            $cols = array_keys($data);
            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $colNames = implode(', ', $cols);
            $stmt = $pdo->prepare("INSERT INTO product_variants ({$colNames}) VALUES ({$placeholders})");
            $stmt->execute(array_values($data));
            $created++;
        } else {
            if (empty($data)) {
                $skipped++;
                continue;
            }
            $data['updatedAt'] = gmdate('Y-m-d H:i:s');
            $sets = [];
            $vals = [];
            foreach ($data as $col => $val) {
                $sets[] = "{$col} = ?";
                $vals[] = $val;
            }
            $vals[] = $existing['id'];
            $stmt = $pdo->prepare("UPDATE product_variants SET " . implode(', ', $sets) . " WHERE id = ?");
            $stmt->execute($vals);
            $updated++;
        }
    }

    opd_json_response([
        'created' => $created,
        'updated' => $updated,
        'skipped' => $skipped,
        'errors' => $errors,
        'total' => count($rows),
    ]);
}

// ---- Import Images ----
if ($importType === 'images') {
    site_ensure_product_images_table($pdo);

    if (!in_array('sku', $headers, true) || !in_array('imageLocation', $headers, true) || !in_array('imageType', $headers, true)) {
        opd_json_response(['error' => 'CSV must have columns: sku, imageLocation, imageType'], 400);
    }

    $created = 0;
    $skipped = 0;
    $errors = [];

    foreach ($rows as $i => $row) {
        $lineNum = $i + 2;
        $sku = $row['sku'] ?? '';
        $imageLocation = $row['imageLocation'] ?? '';
        $imageType = strtolower($row['imageType'] ?? '');

        if ($sku === '' || $imageLocation === '') {
            $errors[] = "Row {$lineNum}: Missing sku or imageLocation, skipped.";
            $skipped++;
            continue;
        }
        if (!in_array($imageType, ['primary', 'secondary'], true)) {
            $errors[] = "Row {$lineNum}: imageType must be 'primary' or 'secondary', skipped.";
            $skipped++;
            continue;
        }

        // Find product by SKU
        $prodStmt = $pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
        $prodStmt->execute([$sku]);
        $product = $prodStmt->fetch();
        if (!$product) {
            $errors[] = "Row {$lineNum}: Product SKU '{$sku}' not found, skipped.";
            $skipped++;
            continue;
        }
        $productId = $product['id'];

        // Get current max sort order
        $sortStmt = $pdo->prepare('SELECT COALESCE(MAX(sortOrder), 0) AS maxSort FROM product_images WHERE productId = ?');
        $sortStmt->execute([$productId]);
        $maxSort = (int) ($sortStmt->fetch()['maxSort'] ?? 0);

        $isPrimary = $imageType === 'primary' ? 1 : 0;
        $id = opd_generate_id('pimg');
        $now = gmdate('Y-m-d H:i:s');

        // Auto-promote to primary if product has no images yet
        if (!$isPrimary) {
            $countStmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM product_images WHERE productId = ?');
            $countStmt->execute([$productId]);
            $imgCount = (int) ($countStmt->fetch()['cnt'] ?? 0);
            if ($imgCount === 0) {
                $isPrimary = 1;
            }
        }

        // If setting as primary, clear existing primary first
        if ($isPrimary) {
            $pdo->prepare('UPDATE product_images SET isPrimary = 0 WHERE productId = ?')->execute([$productId]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO product_images (id, productId, url, isPrimary, sortOrder, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$id, $productId, $imageLocation, $isPrimary, $maxSort + 1, $now, $now]);

        // Update product imageUrl so it displays in admin and storefront
        if ($isPrimary) {
            $pdo->prepare('UPDATE products SET imageUrl = ? WHERE id = ?')->execute([$imageLocation, $productId]);
        }

        $created++;
    }

    opd_json_response([
        'created' => $created,
        'skipped' => $skipped,
        'errors' => $errors,
        'total' => count($rows),
    ]);
}

opd_json_response(['error' => 'Invalid import type'], 400);
