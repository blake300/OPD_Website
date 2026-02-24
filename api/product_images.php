<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/db_conn.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/store.php';

$pdo = opd_db();
site_ensure_product_images_table($pdo);
$method = $_SERVER['REQUEST_METHOD'];

function opd_set_primary_product_image(PDO $pdo, string $productId, string $imageId): ?string
{
    $stmt = $pdo->prepare('SELECT id, url FROM product_images WHERE id = ? AND productId = ? LIMIT 1');
    $stmt->execute([$imageId, $productId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $pdo->prepare('UPDATE product_images SET isPrimary = 0 WHERE productId = ?')->execute([$productId]);
    $pdo->prepare('UPDATE product_images SET isPrimary = 1 WHERE id = ?')->execute([$imageId]);
    $pdo->prepare('UPDATE products SET imageUrl = ? WHERE id = ?')->execute([$row['url'], $productId]);
    return (string) ($row['url'] ?? '');
}

if ($method === 'GET') {
    opd_require_role(['admin', 'manager']);
    $productId = trim((string) ($_GET['productId'] ?? ''));
    if ($productId === '') {
        opd_json_response(['error' => 'Missing productId'], 400);
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM product_images WHERE productId = ? ORDER BY isPrimary DESC, sortOrder ASC, createdAt ASC'
    );
    $stmt->execute([$productId]);
    $items = $stmt->fetchAll();
    opd_json_response(['items' => $items, 'total' => count($items)]);
}

if ($method === 'POST') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $productId = trim((string) ($payload['productId'] ?? ''));
    if ($productId === '') {
        opd_json_response(['error' => 'Missing productId'], 400);
    }
    $urls = [];
    if (!empty($payload['url']) && is_string($payload['url'])) {
        $urls[] = trim($payload['url']);
    }
    if (!empty($payload['urls']) && is_array($payload['urls'])) {
        foreach ($payload['urls'] as $url) {
            if (is_string($url) && trim($url) !== '') {
                $urls[] = trim($url);
            }
        }
    }
    $urls = array_values(array_unique(array_filter($urls)));
    if (!$urls) {
        opd_json_response(['error' => 'Missing image urls'], 400);
    }

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(sortOrder), 0) AS maxSort FROM product_images WHERE productId = ?');
    $stmt->execute([$productId]);
    $maxSort = (int) (($stmt->fetch()['maxSort'] ?? 0));

    $stmt = $pdo->prepare('SELECT id FROM product_images WHERE productId = ? AND isPrimary = 1 LIMIT 1');
    $stmt->execute([$productId]);
    $hasPrimary = (bool) $stmt->fetch();
    $makePrimary = !empty($payload['makePrimary']);

    $insert = $pdo->prepare(
        'INSERT INTO product_images (id, productId, url, isPrimary, sortOrder, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $now = gmdate('Y-m-d H:i:s');
    $primaryId = '';
    foreach ($urls as $index => $url) {
        $id = opd_generate_id('pimg');
        $sortOrder = ++$maxSort;
        $isPrimary = 0;
        if ((!$hasPrimary && $index === 0) || ($makePrimary && $index === 0)) {
            $isPrimary = 1;
            $primaryId = $id;
            $hasPrimary = true;
        }
        $insert->execute([$id, $productId, $url, $isPrimary, $sortOrder, $now, $now]);
    }

    if ($primaryId !== '') {
        opd_set_primary_product_image($pdo, $productId, $primaryId);
    }

    opd_json_response(['ok' => true]);
}

if ($method === 'PUT') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $productId = trim((string) ($payload['productId'] ?? ''));
    $primaryId = trim((string) ($payload['primaryId'] ?? $payload['id'] ?? ''));
    if ($productId === '' || $primaryId === '') {
        opd_json_response(['error' => 'Missing productId or image id'], 400);
    }
    $primaryUrl = opd_set_primary_product_image($pdo, $productId, $primaryId);
    if ($primaryUrl === null) {
        opd_json_response(['error' => 'Image not found'], 404);
    }
    opd_json_response(['ok' => true]);
}

if ($method === 'DELETE') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $id = trim((string) ($_GET['id'] ?? ''));
    if ($id === '') {
        opd_json_response(['error' => 'Missing id'], 400);
    }
    $stmt = $pdo->prepare('SELECT productId, isPrimary FROM product_images WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        opd_json_response(['error' => 'Not found'], 404);
    }
    $productId = (string) ($row['productId'] ?? '');
    $wasPrimary = (int) ($row['isPrimary'] ?? 0) === 1;
    $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$id]);

    if ($wasPrimary && $productId !== '') {
        $stmt = $pdo->prepare(
            'SELECT id FROM product_images WHERE productId = ? ORDER BY sortOrder ASC, createdAt ASC LIMIT 1'
        );
        $stmt->execute([$productId]);
        $next = $stmt->fetch();
        if ($next) {
            opd_set_primary_product_image($pdo, $productId, (string) $next['id']);
        } else {
            $pdo->prepare('UPDATE products SET imageUrl = NULL WHERE id = ?')->execute([$productId]);
        }
    }

    opd_json_response(['ok' => true]);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
