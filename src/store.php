<?php

declare(strict_types=1);

require_once __DIR__ . '/db_conn.php';
require_once __DIR__ . '/site_auth.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/tax_rates.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/experttexting_service.php';
require_once __DIR__ . '/api_helpers.php';

function site_ensure_product_images_table(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS product_images (
            id VARCHAR(64) PRIMARY KEY,
            productId VARCHAR(64),
            url TEXT,
            isPrimary TINYINT(1) DEFAULT 0,
            sortOrder INT DEFAULT 0,
            createdAt DATETIME,
            updatedAt DATETIME,
            INDEX productId (productId)
        )'
    );
}

function site_get_categories(): array
{
    $hidden = opd_hidden_categories();
    $pdo = opd_db();
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category");
    $rows = $stmt->fetchAll();
    $dbCategories = array_map(fn($row) => $row['category'], $rows);
    $categories = opd_public_product_categories();
    foreach ($dbCategories as $category) {
        if ($category !== '' && !in_array($category, $categories, true) && !in_array($category, $hidden, true)) {
            $categories[] = $category;
        }
    }
    return $categories;
}

function site_storefront_product_columns(string $alias = 'p'): string
{
    return implode(', ', [
        "{$alias}.id AS id",
        "{$alias}.name AS name",
        "{$alias}.sku AS sku",
        "{$alias}.category AS category",
        "{$alias}.price AS price",
        "COALESCE({$alias}.status, 'active') AS status",
        "{$alias}.shortDescription AS shortDescription",
        "{$alias}.longDescription AS longDescription",
        "{$alias}.service AS service",
        "{$alias}.largeDelivery AS largeDelivery",
        "{$alias}.daysOut AS daysOut",
        "{$alias}.inventory AS inventory",
        "{$alias}.wgt AS wgt",
        "{$alias}.posNum AS posNum",
        "{$alias}.updatedAt AS updatedAt",
    ]);
}

function site_storefront_visibility_filter(string $alias = 'p', bool $includeHiddenCategories = false): array
{
    $conditions = ["COALESCE({$alias}.status, 'active') = 'active'"];
    $params = [];
    if (!$includeHiddenCategories) {
        $hiddenCategories = opd_hidden_categories();
        if ($hiddenCategories) {
            $conditions[] = "COALESCE({$alias}.category, '') NOT IN (" . implode(',', array_fill(0, count($hiddenCategories), '?')) . ')';
            $params = array_merge($params, $hiddenCategories);
        }
    }
    return [
        'sql' => implode(' AND ', $conditions),
        'params' => $params,
    ];
}

function site_association_display_filter(string $alias = 'p'): array
{
    return site_storefront_visibility_filter($alias, true);
}

function site_is_storefront_visible_product(array $product, bool $includeHiddenCategories = false): bool
{
    $status = strtolower(trim((string) ($product['status'] ?? 'active')));
    if ($status !== '' && $status !== 'active') {
        return false;
    }
    if ($includeHiddenCategories) {
        return true;
    }
    $category = trim((string) ($product['category'] ?? ''));
    return !in_array($category, opd_hidden_categories(), true);
}

function site_is_storefront_sellable_product(array $product, bool $includeHiddenCategories = false): bool
{
    if (!site_is_storefront_visible_product($product, $includeHiddenCategories)) {
        return false;
    }
    if (($product['category'] ?? '') !== 'Used Equipment') {
        return true;
    }
    $productId = (string) ($product['id'] ?? '');
    $inventory = (int) ($product['inventory'] ?? 0);
    if ($productId === '' || $inventory <= 0) {
        return true;
    }
    return site_equipment_sold_quantity($productId) < $inventory;
}

function site_normalize_association_source_product_id(mixed $associationSourceProductId): ?string
{
    if (!is_string($associationSourceProductId)) {
        return null;
    }
    $associationSourceProductId = trim($associationSourceProductId);
    return $associationSourceProductId !== '' ? $associationSourceProductId : null;
}

function site_has_displayable_product_association(string $sourceProductId, string $relatedProductId): bool
{
    if ($sourceProductId === '' || $relatedProductId === '') {
        return false;
    }
    $sourceProduct = site_get_public_product($sourceProductId);
    if (!$sourceProduct || !site_is_storefront_sellable_product($sourceProduct)) {
        return false;
    }
    $pdo = opd_db();
    $visibility = site_association_display_filter('p');
    $stmt = $pdo->prepare(
        'SELECT 1
         FROM product_associations pa
         JOIN products p ON p.id = pa.relatedProductId
         WHERE ' . $visibility['sql'] . ' AND pa.productId = ? AND pa.relatedProductId = ?
         LIMIT 1'
    );
    $stmt->execute(array_merge($visibility['params'], [$sourceProductId, $relatedProductId]));
    return (bool) $stmt->fetchColumn();
}

function site_is_association_context_sellable_product(array $product, ?string $associationSourceProductId = null): bool
{
    if (site_is_storefront_sellable_product($product)) {
        return true;
    }
    $associationSourceProductId = site_normalize_association_source_product_id($associationSourceProductId);
    if ($associationSourceProductId === null) {
        return false;
    }
    if (!site_is_storefront_sellable_product($product, true)) {
        return false;
    }
    return site_has_displayable_product_association($associationSourceProductId, (string) ($product['id'] ?? ''));
}

function site_resolve_cart_product_context(string $productId, ?string $associationSourceProductId = null): array
{
    $associationSourceProductId = site_normalize_association_source_product_id($associationSourceProductId);
    $product = site_get_product($productId);
    if (!$product || !site_is_association_context_sellable_product($product, $associationSourceProductId)) {
        return [
            'product' => null,
            'associationSourceProductId' => null,
        ];
    }
    if ($associationSourceProductId !== null && !site_has_displayable_product_association($associationSourceProductId, $productId)) {
        $associationSourceProductId = null;
    }
    return [
        'product' => $product,
        'associationSourceProductId' => $associationSourceProductId,
    ];
}

function site_public_product_payload(array $product): array
{
    return [
        'id' => (string) ($product['id'] ?? ''),
        'name' => (string) ($product['name'] ?? ''),
        'sku' => (string) ($product['sku'] ?? ''),
        'category' => (string) ($product['category'] ?? ''),
        'price' => (float) ($product['price'] ?? 0),
        'status' => (string) ($product['status'] ?? 'active'),
        'imageUrl' => (string) ($product['imageUrl'] ?? ''),
        'service' => !empty($product['service']),
        'daysOut' => (int) ($product['daysOut'] ?? 0),
    ];
}

function site_get_products(?string $category = null, ?string $search = null, int $limit = 24, int $offset = 0): array
{
    $pdo = opd_db();
    site_ensure_product_images_table($pdo);
    $visibility = site_storefront_visibility_filter('p');
    $conditions = [$visibility['sql']];
    $params = $visibility['params'];
    if ($category) {
        $conditions[] = 'p.category = ?';
        $params[] = $category;
    }
    if ($search) {
        $like = '%' . $search . '%';
        $conditions[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.tags LIKE ? OR EXISTS (
            SELECT 1 FROM product_variants pv
            WHERE pv.productId = p.id
              AND COALESCE(pv.status, \'active\') = \'active\'
              AND (pv.name LIKE ? OR pv.sku LIKE ? OR pv.tags LIKE ?)
        ))';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $sql = 'SELECT ' . site_storefront_product_columns('p') . ', COALESCE(pi.url, p.imageUrl) AS imageUrl
            FROM products p
            LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1';
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
    if ($category) {
        $sql .= ' ORDER BY (p.posNum IS NULL), p.posNum ASC, p.updatedAt DESC';
    } else {
        $sql .= ' ORDER BY p.updatedAt DESC';
    }
    $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function site_get_product_suggestions(string $search, int $limit = 8): array
{
    $search = trim($search);
    if ($search === '') {
        return [];
    }
    $pdo = opd_db();
    site_ensure_product_images_table($pdo);
    $limit = max(1, min(20, $limit));
    $like = '%' . $search . '%';
    $likeStart = $search . '%';
    $visibility = site_storefront_visibility_filter('p');
    $sql = 'SELECT p.id, p.name, p.sku, p.category, COALESCE(pi.url, p.imageUrl) AS imageUrl
            FROM products p
            LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
            WHERE ' . $visibility['sql'] . '
              AND (p.name LIKE ? OR p.sku LIKE ? OR p.tags LIKE ? OR EXISTS (
                SELECT 1 FROM product_variants pv
                WHERE pv.productId = p.id
                  AND COALESCE(pv.status, \'active\') = \'active\'
                  AND (pv.name LIKE ? OR pv.sku LIKE ? OR pv.tags LIKE ?)
            ))
            ORDER BY (p.name LIKE ?) DESC, (p.sku LIKE ?) DESC, p.updatedAt DESC
            LIMIT ' . (int) $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($visibility['params'], [$like, $like, $like, $like, $like, $like, $likeStart, $likeStart]));
    return $stmt->fetchAll();
}

function site_get_featured_products(int $limit = 12): array
{
    $pdo = opd_db();
    site_ensure_product_images_table($pdo);
    $limit = max(1, $limit);
    $visibility = site_storefront_visibility_filter('p');
    $sql = 'SELECT ' . site_storefront_product_columns('p') . ', COALESCE(pi.url, p.imageUrl) AS imageUrl
            FROM products p
            LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
            WHERE ' . $visibility['sql'] . ' AND p.featured = 1
            ORDER BY p.updatedAt DESC
            LIMIT ' . (int) $limit;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($visibility['params']);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('Featured products query failed: ' . $e->getMessage());
        $fallback = 'SELECT ' . site_storefront_product_columns('p') . ', COALESCE(pi.url, p.imageUrl) AS imageUrl
                     FROM products p
                     LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
                     WHERE ' . $visibility['sql'] . '
                     ORDER BY p.updatedAt DESC
                     LIMIT ' . (int) $limit;
        $stmt = $pdo->prepare($fallback);
        $stmt->execute($visibility['params']);
        return $stmt->fetchAll();
    }
}

function site_get_public_product(string $id): ?array
{
    $pdo = opd_db();
    site_ensure_product_images_table($pdo);
    $visibility = site_storefront_visibility_filter('p');
    $stmt = $pdo->prepare(
        'SELECT ' . site_storefront_product_columns('p') . ', COALESCE(pi.url, p.imageUrl) AS imageUrl
         FROM products p
         LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
         WHERE ' . $visibility['sql'] . ' AND p.id = ?
         LIMIT 1'
    );
    $stmt->execute(array_merge($visibility['params'], [$id]));
    $product = $stmt->fetch();
    return $product ?: null;
}

function site_get_product(string $id): ?array
{
    $pdo = opd_db();
    site_ensure_product_images_table($pdo);
    $stmt = $pdo->prepare(
        'SELECT p.*, COALESCE(pi.url, p.imageUrl) AS imageUrl
         FROM products p
         LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
         WHERE p.id = ?
         LIMIT 1'
    );
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function site_get_product_images(string $productId): array
{
    $pdo = opd_db();
    site_ensure_product_images_table($pdo);
    $stmt = $pdo->prepare(
        'SELECT * FROM product_images WHERE productId = ? ORDER BY isPrimary DESC, sortOrder ASC, createdAt ASC'
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function site_get_product_variants(string $productId): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        "SELECT id, productId, name, sku, price, largeDelivery, wgt, COALESCE(status, 'active') AS status
         FROM product_variants
         WHERE productId = ? AND COALESCE(status, 'active') = 'active'
         ORDER BY (posNum IS NULL), posNum ASC, name ASC"
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function site_variant_exists_for_product(string $productId, string $variantId): bool
{
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        "SELECT 1
         FROM product_variants
         WHERE id = ? AND productId = ? AND COALESCE(status, 'active') = 'active'
         LIMIT 1"
    );
    $stmt->execute([$variantId, $productId]);
    return (bool) $stmt->fetchColumn();
}

function site_get_related_products(string $productId, int $limit = 6): array
{
    $pdo = opd_db();
    site_ensure_product_images_table($pdo);
    $visibility = site_association_display_filter('p');
    $stmt = $pdo->prepare(
        'SELECT ' . site_storefront_product_columns('p') . ", COALESCE(pi.url, p.imageUrl) AS imageUrl
         FROM product_associations pa
         JOIN products p ON p.id = pa.relatedProductId
         LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
         WHERE " . $visibility['sql'] . " AND pa.productId = ?
         ORDER BY COALESCE(p.category, ''), (pa.sortOrder IS NULL), pa.sortOrder ASC, p.updatedAt DESC"
    );
    $stmt->execute(array_merge($visibility['params'], [$productId]));
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!site_is_storefront_sellable_product($row, true)) {
            continue;
        }
        $items[] = $row;
        if (count($items) >= $limit) {
            break;
        }
    }
    return $items;
}

function site_get_related_product_counts(array $productIds): array
{
    $productIds = array_values(array_filter(array_map(
        static fn($id) => is_string($id) || is_numeric($id) ? trim((string) $id) : '',
        $productIds
    )));
    if (!$productIds) {
        return [];
    }
    $productIds = array_values(array_unique($productIds));
    $pdo = opd_db();
    site_ensure_product_images_table($pdo);
    $visibility = site_association_display_filter('p');
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $stmt = $pdo->prepare(
        'SELECT pa.productId AS sourceProductId, ' . site_storefront_product_columns('p') . ", COALESCE(pi.url, p.imageUrl) AS imageUrl
         FROM product_associations pa
         JOIN products p ON p.id = pa.relatedProductId
         LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
         WHERE " . $visibility['sql'] . " AND pa.productId IN ({$placeholders})
         ORDER BY pa.productId ASC, COALESCE(p.category, ''), (pa.sortOrder IS NULL), pa.sortOrder ASC, p.updatedAt DESC"
    );
    $stmt->execute(array_merge($visibility['params'], $productIds));
    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!site_is_storefront_sellable_product($row, true)) {
            continue;
        }
        $sourceProductId = (string) ($row['sourceProductId'] ?? '');
        if ($sourceProductId === '') {
            continue;
        }
        $counts[$sourceProductId] = ($counts[$sourceProductId] ?? 0) + 1;
    }
    return $counts;
}

function site_cart_items(): array
{
    $user = site_current_user();
    if ($user) {
        return site_cart_items_for_user($user['id']);
    }
    site_start_session();
    $cart = $_SESSION['site_cart'] ?? [];
    if (!$cart) {
        return [];
    }
    $items = [];
    $cartChanged = false;
    foreach ($cart as $key => $item) {
        $resolved = site_resolve_cart_product_context(
            (string) ($item['productId'] ?? ''),
            $item['associationSourceProductId'] ?? null
        );
        $product = $resolved['product'] ?? null;
        if (!$product) {
            unset($cart[$key]);
            $cartChanged = true;
            continue;
        }
        $itemLargeDelivery = !empty($product['largeDelivery']);
        $itemWeight = (float) ($product['wgt'] ?? 0);
        $vRow = null;
        if (!empty($item['variantId'])) {
            $pdo = opd_db();
            $vStmt = $pdo->prepare(
                "SELECT name, sku, price, largeDelivery, wgt
                 FROM product_variants
                 WHERE id = ? AND productId = ? AND COALESCE(status, 'active') = 'active'
                 LIMIT 1"
            );
            $vStmt->execute([$item['variantId'], $product['id']]);
            $vRow = $vStmt->fetch();
            if (!$vRow) {
                unset($cart[$key]);
                $cartChanged = true;
                continue;
            }
            if ($vRow) {
                if (!empty($vRow['largeDelivery'])) {
                    $itemLargeDelivery = true;
                }
                if (!empty($vRow['wgt']) && is_numeric($vRow['wgt'])) {
                    $itemWeight = (float) $vRow['wgt'];
                }
            }
        }
        $variantName = ($vRow['name'] ?? null);
        $variantSku = ($vRow['sku'] ?? null);
        $variantPrice = ($vRow['price'] ?? null);
        $items[] = [
            'key' => $key,
            'productId' => $item['productId'],
            'variantId' => $item['variantId'],
            'name' => !empty($variantName) ? $variantName : $product['name'],
            'productName' => $product['name'] ?? '',
            'variantName' => $variantName ?? '',
            'sku' => !empty($variantSku) ? (string) $variantSku : (string) ($product['sku'] ?? ''),
            'price' => ($variantPrice !== null && $variantPrice !== '') ? (float) $variantPrice : (float) $product['price'],
            'quantity' => (int) $item['quantity'],
            'imageUrl' => $product['imageUrl'] ?? null,
            'arrivalDate' => $item['arrivalDate'] ?? null,
            'service' => !empty($product['service']),
            'largeDelivery' => $itemLargeDelivery,
            'wgt' => $itemWeight,
            'associationSourceProductId' => $resolved['associationSourceProductId'] ?? null,
        ];
    }
    if ($cartChanged) {
        $_SESSION['site_cart'] = $cart;
    }
    return $items;
}

function site_cart_count(): int
{
    $user = site_current_user();
    if ($user) {
        $pdo = opd_db();
        $cartId = site_get_cart_id($user['id']);
        $stmt = $pdo->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cartId = ?');
        $stmt->execute([$cartId]);
        return (int) $stmt->fetchColumn();
    }
    site_start_session();
    $cart = $_SESSION['site_cart'] ?? [];
    $count = 0;
    foreach ($cart as $item) {
        $count += (int) ($item['quantity'] ?? 1);
    }
    return $count;
}

function site_cart_items_for_user(string $userId): array
{
    $pdo = opd_db();
    site_ensure_product_images_table($pdo);
    site_ensure_cart_columns($pdo);
    site_ensure_delivery_columns($pdo);
    $cartId = site_get_cart_id($userId);
    if (!$cartId) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT ci.id as `key`, ci.productId, ci.variantId, ci.quantity, ci.arrivalDate, ci.associationSourceProductId,
                p.name AS productName, p.sku AS productSku, p.price AS productPrice, p.service,
                COALESCE(p.status, \'active\') AS productStatus, p.category AS productCategory, p.inventory AS productInventory,
                p.largeDelivery AS productLargeDelivery,
                p.wgt AS productWeight,
                pv.name AS variantName, pv.sku AS variantSku, pv.price AS variantPrice,
                pv.largeDelivery AS variantLargeDelivery,
                pv.wgt AS variantWeight,
                COALESCE(pi.url, p.imageUrl) AS imageUrl
         FROM cart_items ci
         JOIN products p ON p.id = ci.productId
         LEFT JOIN product_variants pv ON pv.id = ci.variantId AND COALESCE(pv.status, \'active\') = \'active\'
         LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
         WHERE ci.cartId = ?'
    );
    $stmt->execute([$cartId]);
    $rows = $stmt->fetchAll();
    $items = [];
    foreach ($rows as $row) {
        $resolved = site_resolve_cart_product_context(
            (string) ($row['productId'] ?? ''),
            $row['associationSourceProductId'] ?? null
        );
        if (empty($resolved['product'])) {
            continue;
        }
        $row['name'] = !empty($row['variantName']) ? $row['variantName'] : ($row['productName'] ?? '');
        $row['productName'] = $row['productName'] ?? '';
        $row['variantName'] = $row['variantName'] ?? '';
        $row['sku'] = !empty($row['variantSku']) ? (string) $row['variantSku'] : (string) ($row['productSku'] ?? '');
        $row['price'] = ($row['variantPrice'] !== null && $row['variantPrice'] !== '') ? $row['variantPrice'] : ($row['productPrice'] ?? 0);
        $row['associationSourceProductId'] = $resolved['associationSourceProductId'] ?? null;
        $items[] = $row;
    }
    return $items;
}

function site_get_cart_id(string $userId): ?string
{
    $pdo = opd_db();
    $stmt = $pdo->prepare("SELECT id FROM carts WHERE userId = ? AND status = 'open' LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    if ($row) {
        return $row['id'];
    }
    $cartId = opd_generate_id('cart');
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare('INSERT INTO carts (id, userId, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$cartId, $userId, 'open', $now, $now]);
    return $cartId;
}

function site_add_to_cart(
    string $productId,
    int $quantity,
    ?string $variantId = null,
    ?string $arrivalDate = null,
    ?string $associationSourceProductId = null
): ?string
{
    $quantity = max(1, $quantity);
    if (!is_string($arrivalDate) || trim($arrivalDate) === '') {
        $arrivalDate = null;
    } else {
        $arrivalDate = trim($arrivalDate);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $arrivalDate)) {
            $arrivalDate = null;
        }
    }
    $resolved = site_resolve_cart_product_context($productId, $associationSourceProductId);
    $product = $resolved['product'] ?? null;
    $associationSourceProductId = $resolved['associationSourceProductId'] ?? null;
    if (!$product) {
        return null;
    }
    if ($variantId !== null && $variantId !== '' && !site_variant_exists_for_product($productId, $variantId)) {
        return null;
    }
    $user = site_current_user();
    if ($user) {
        $pdo = opd_db();
        site_ensure_cart_columns($pdo);
        $cartId = site_get_cart_id($user['id']);
        $stmt = $pdo->prepare(
            'SELECT id, quantity
             FROM cart_items
             WHERE cartId = ? AND productId = ? AND variantId <=> ? AND arrivalDate <=> ? AND associationSourceProductId <=> ?'
        );
        $stmt->execute([$cartId, $productId, $variantId, $arrivalDate, $associationSourceProductId]);
        $row = $stmt->fetch();
        if ($row) {
            $update = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
            $update->execute([(int) $row['quantity'] + $quantity, $row['id']]);
            return (string) $row['id'];
        }
        $itemId = opd_generate_id('ci');
        $insert = $pdo->prepare(
            'INSERT INTO cart_items (id, cartId, productId, variantId, quantity, arrivalDate, associationSourceProductId, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $insert->execute([$itemId, $cartId, $productId, $variantId, $quantity, $arrivalDate, $associationSourceProductId, $now, $now]);
        return $itemId;
    }

    site_start_session();
    $cart = $_SESSION['site_cart'] ?? [];
    $key = $productId . ':' . ($variantId ?? '') . ':' . ($arrivalDate ?? '') . ':' . ($associationSourceProductId ?? '');
    $existing = $cart[$key] ?? null;
    if ($existing) {
        $existing['quantity'] += $quantity;
        $cart[$key] = $existing;
    } else {
        $cart[$key] = [
            'productId' => $productId,
            'variantId' => $variantId,
            'quantity' => $quantity,
            'arrivalDate' => $arrivalDate,
            'associationSourceProductId' => $associationSourceProductId,
        ];
    }
    $_SESSION['site_cart'] = $cart;
    return $key;
}

function site_update_cart_item(string $key, int $quantity): void
{
    $quantity = max(1, $quantity);
    $user = site_current_user();
    if ($user) {
        $pdo = opd_db();
        $update = $pdo->prepare('UPDATE cart_items SET quantity = ?, updatedAt = ? WHERE id = ?');
        $update->execute([$quantity, gmdate('Y-m-d H:i:s'), $key]);
        return;
    }
    site_start_session();
    $cart = $_SESSION['site_cart'] ?? [];
    if (isset($cart[$key])) {
        $cart[$key]['quantity'] = $quantity;
    }
    $_SESSION['site_cart'] = $cart;
}

function site_remove_cart_item(string $key): void
{
    $user = site_current_user();
    if ($user) {
        $pdo = opd_db();
        $stmt = $pdo->prepare('DELETE FROM cart_items WHERE id = ?');
        $stmt->execute([$key]);
        return;
    }
    site_start_session();
    $cart = $_SESSION['site_cart'] ?? [];
    unset($cart[$key]);
    $_SESSION['site_cart'] = $cart;
}

function site_cart_total(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        $total += (float) $item['price'] * (int) $item['quantity'];
    }
    return $total;
}

function site_cart_taxable_total(array $items): float
{
    $total = 0.0;
    foreach ($items as $item) {
        if (empty($item['service'])) {
            $total += (float) $item['price'] * (int) $item['quantity'];
        }
    }
    return $total;
}

function site_cart_has_only_service_items(array $items): bool
{
    if (!$items) {
        return false;
    }
    foreach ($items as $item) {
        if (empty($item['service'])) {
            return false;
        }
    }
    return true;
}

function site_cart_has_any_service_items(array $items): bool
{
    foreach ($items as $item) {
        if (!empty($item['service'])) {
            return true;
        }
    }
    return false;
}

function site_get_cart_accounting(string $cartId, ?string $clientId = null): ?array
{
    $pdo = opd_db();
    if ($clientId) {
        $stmt = $pdo->prepare('SELECT * FROM cart_accounting WHERE cartId = ? AND clientId = ? ORDER BY updatedAt DESC LIMIT 1');
        $stmt->execute([$cartId, $clientId]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM cart_accounting WHERE cartId = ? AND clientId IS NULL ORDER BY updatedAt DESC LIMIT 1');
        $stmt->execute([$cartId]);
    }
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $groups = json_decode($row['groupsJson'] ?? '[]', true);
    $assignments = json_decode($row['assignmentsJson'] ?? '[]', true);
    if (!is_array($groups)) {
        $groups = [];
    }
    if (!is_array($assignments)) {
        $assignments = [];
    }
    return [
        'groups' => $groups,
        'assignments' => $assignments,
        'clientId' => $row['clientId'] ?? null,
    ];
}

function site_get_latest_cart_accounting_client_id(string $cartId): ?string
{
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        'SELECT clientId FROM cart_accounting WHERE cartId = ? AND clientId IS NOT NULL ORDER BY updatedAt DESC LIMIT 1'
    );
    $stmt->execute([$cartId]);
    $row = $stmt->fetch();
    $clientId = trim((string) ($row['clientId'] ?? ''));
    return $clientId !== '' ? $clientId : null;
}

function site_get_cart_accounting_for_user(string $userId, ?string $clientId = null): ?array
{
    $cartId = site_get_cart_id($userId);
    if (!$cartId) {
        return null;
    }
    return site_get_cart_accounting($cartId, $clientId);
}

function site_save_cart_accounting(string $cartId, ?string $clientId, array $groups, array $assignments): void
{
    $pdo = opd_db();
    $now = gmdate('Y-m-d H:i:s');
    $groupsJson = json_encode($groups);
    $assignmentsJson = json_encode($assignments);
    if ($groupsJson === false) {
        $groupsJson = '[]';
    }
    if ($assignmentsJson === false) {
        $assignmentsJson = '[]';
    }

    $clientId = $clientId ?: null;
    if ($clientId) {
        $stmt = $pdo->prepare('SELECT id FROM cart_accounting WHERE cartId = ? AND clientId = ? LIMIT 1');
        $stmt->execute([$cartId, $clientId]);
    } else {
        // User switched back to default — clear all client-specific rows so checkout
        // won't pick up a stale clientId from site_get_latest_cart_accounting_client_id()
        $pdo->prepare('DELETE FROM cart_accounting WHERE cartId = ? AND clientId IS NOT NULL')
            ->execute([$cartId]);
        $stmt = $pdo->prepare('SELECT id FROM cart_accounting WHERE cartId = ? AND clientId IS NULL LIMIT 1');
        $stmt->execute([$cartId]);
    }
    $row = $stmt->fetch();
    if ($row) {
        $update = $pdo->prepare('UPDATE cart_accounting SET groupsJson = ?, assignmentsJson = ?, updatedAt = ? WHERE id = ?');
        $update->execute([$groupsJson, $assignmentsJson, $now, $row['id']]);
        return;
    }
    $insert = $pdo->prepare(
        'INSERT INTO cart_accounting (id, cartId, clientId, groupsJson, assignmentsJson, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        opd_generate_id('cartacc'),
        $cartId,
        $clientId,
        $groupsJson,
        $assignmentsJson,
        $now,
        $now,
    ]);
}

function site_save_order_accounting(string $orderId, ?string $clientId, array $groups, array $assignments): void
{
    $pdo = opd_db();
    $now = gmdate('Y-m-d H:i:s');
    $groupsJson = json_encode($groups);
    $assignmentsJson = json_encode($assignments);
    if ($groupsJson === false) {
        $groupsJson = '[]';
    }
    if ($assignmentsJson === false) {
        $assignmentsJson = '[]';
    }
    $existing = $pdo->prepare('SELECT id FROM order_accounting WHERE orderId = ? LIMIT 1');
    $existing->execute([$orderId]);
    $row = $existing->fetch();
    if ($row) {
        $update = $pdo->prepare(
            'UPDATE order_accounting SET clientId = ?, groupsJson = ?, assignmentsJson = ?, updatedAt = ? WHERE id = ?'
        );
        $update->execute([$clientId ?: null, $groupsJson, $assignmentsJson, $now, $row['id']]);
        return;
    }
    $insert = $pdo->prepare(
        'INSERT INTO order_accounting (id, orderId, clientId, groupsJson, assignmentsJson, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        opd_generate_id('ordacc'),
        $orderId,
        $clientId ?: null,
        $groupsJson,
        $assignmentsJson,
        $now,
        $now,
    ]);
}

/**
 * Validate order authentication and retrieve cart items.
 * @return array{user: ?array, items: array, isGuest: bool, error: ?string}
 */
function site_validate_order_auth(array $data): array
{
    $user = site_current_user();
    $isGuest = !$user && !empty($data['guest']);

    if (!$user && !$isGuest) {
        return ['user' => null, 'items' => [], 'isGuest' => false, 'error' => 'Login required'];
    }

    $items = $user ? site_cart_items_for_user($user['id']) : site_cart_items();

    if (!$items) {
        return ['user' => $user, 'items' => [], 'isGuest' => $isGuest, 'error' => 'Cart is empty'];
    }

    return ['user' => $user, 'items' => $items, 'isGuest' => $isGuest, 'error' => null];
}

/**
 * Calculate order totals including subtotal, tax, and shipping.
 * @return array{subtotal: float, tax: float, taxData: array, shipping: float, shippingMethod: string, total: float}
 */
function site_calculate_order_totals(array $items, array $data, ?array $user): array
{
    $subtotal = site_cart_total($items);
    $pdo = opd_db();
    $stateInput = trim((string) ($data['state'] ?? ''));
    $postalInput = trim((string) ($data['postal'] ?? ''));

    if ($user && ($stateInput === '' || $postalInput === '')) {
        $profile = $pdo->prepare('SELECT state, zip FROM users WHERE id = ? LIMIT 1');
        $profile->execute([$user['id']]);
        $profileRow = $profile->fetch() ?: [];
        if ($stateInput === '') {
            $stateInput = trim((string) ($profileRow['state'] ?? ''));
        }
        if ($postalInput === '') {
            $postalInput = trim((string) ($profileRow['zip'] ?? ''));
        }
    }

    $taxableSubtotal = site_cart_taxable_total($items);
    $taxData = opd_calculate_ok_sales_tax($taxableSubtotal, $stateInput, $postalInput);
    $tax = (float) ($taxData['tax'] ?? 0.0);
    $shippingMethod = trim((string) ($data['shipping_method'] ?? ''));
    $deliveryZone = null;
    $deliveryClass = null;
    if (site_cart_has_only_service_items($items)) {
        $shipping = 0.0;
        $shippingMethod = 'service';
    } elseif ($shippingMethod === 'same_day') {
        $deliveryZip = trim((string) ($data['delivery_zip'] ?? $postalInput));
        $deliveryResult = site_get_same_day_delivery_cost($deliveryZip, $items);
        if ($deliveryResult['error']) {
            return [
                'subtotal' => $subtotal,
                'tax' => $tax,
                'taxData' => $taxData,
                'shipping' => 0.0,
                'shippingMethod' => $shippingMethod,
                'deliveryZone' => null,
                'deliveryClass' => null,
                'total' => $subtotal + $tax,
                'deliveryError' => $deliveryResult['error'],
            ];
        }
        $shipping = $deliveryResult['cost'];
        $deliveryZone = $deliveryResult['zone'];
        $deliveryClass = $deliveryResult['class'];
    } elseif ($shippingMethod === 'standard') {
        $shippingResult = site_calculate_standard_shipping($stateInput, $items);
        if ($shippingResult['error']) {
            return [
                'subtotal' => $subtotal,
                'tax' => $tax,
                'taxData' => $taxData,
                'shipping' => 0.0,
                'shippingMethod' => $shippingMethod,
                'deliveryZone' => null,
                'deliveryClass' => null,
                'total' => $subtotal + $tax,
                'deliveryError' => $shippingResult['error'],
            ];
        }
        $shipping = $shippingResult['cost'];
    } else {
        $shipping = 0.0;
    }
    $total = $subtotal + $tax + $shipping;

    return [
        'subtotal' => $subtotal,
        'tax' => $tax,
        'taxData' => $taxData,
        'shipping' => $shipping,
        'shippingMethod' => $shippingMethod,
        'deliveryZone' => $deliveryZone,
        'deliveryClass' => $deliveryClass,
        'total' => $total,
    ];
}

/**
 * Resolve client and vendor relationships for the order.
 * @return array{clientId: ?string, clientUserId: ?string, error: ?string}
 */
function site_resolve_order_client(array $data, ?array $user, float $total): array
{
    $clientId = null;
    $clientUserId = null;
    $accounting = $data['accounting'] ?? null;

    if ($user) {
        $candidateId = null;
        if (is_array($accounting)) {
            $candidateId = $accounting['clientId'] ?? null;
        }
        if (!is_string($candidateId) || $candidateId === '') {
            $candidateId = $data['clientId'] ?? ($data['client_id'] ?? null);
        }
        if (is_string($candidateId) && $candidateId !== '') {
            $clientRecord = site_get_client_record($user['id'], $candidateId);
            if ($clientRecord && site_client_is_billable($clientRecord)) {
                $clientId = $clientRecord['id'];
                $linkedUserId = trim((string) ($clientRecord['linkedUserId'] ?? ''));
                if ($linkedUserId === '') {
                    $email = trim((string) ($clientRecord['email'] ?? ''));
                    if ($email !== '') {
                        $linkedUser = site_find_user_by_email($email);
                        if ($linkedUser) {
                            $linkedUserId = (string) ($linkedUser['id'] ?? '');
                        }
                    }
                }
                if ($linkedUserId === '') {
                    $possibleId = trim((string) ($clientRecord['id'] ?? ''));
                    if ($possibleId !== '') {
                        $pdo = opd_db();
                        $check = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
                        $check->execute([$possibleId]);
                        if ($check->fetch()) {
                            $linkedUserId = $possibleId;
                        }
                    }
                }
                $clientUserId = $linkedUserId !== '' ? $linkedUserId : null;
            }
        }
    }

    if ($user && $clientUserId) {
        $vendorRecord = site_find_vendor_record_for_client(
            $clientUserId,
            (string) $user['id'],
            (string) ($user['email'] ?? '')
        );
        if ($vendorRecord) {
            $status = strtolower(trim((string) ($vendorRecord['status'] ?? '')));
            if ($status === 'declined') {
                return ['clientId' => null, 'clientUserId' => null, 'error' => 'Client declined this vendor relationship.', 'monthlyLimitExceeded' => false];
            }
            $limitResult = site_check_vendor_limits($vendorRecord, $total, $clientUserId, (string) $user['id']);
            if ($limitResult['error']) {
                return ['clientId' => null, 'clientUserId' => null, 'error' => $limitResult['error'], 'monthlyLimitExceeded' => false];
            }
            return ['clientId' => $clientId, 'clientUserId' => $clientUserId, 'error' => null, 'monthlyLimitExceeded' => $limitResult['monthlyLimitExceeded']];
        }
    }

    return ['clientId' => $clientId, 'clientUserId' => $clientUserId, 'error' => null, 'monthlyLimitExceeded' => false];
}

/**
 * Normalize billing and shipping address fields from order data.
 * @return array Address fields ready for order insertion
 */
function site_normalize_order_address(array $data, ?array $user, ?string $clientUserId = null): array
{
    $normalize = static function ($value) {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    };

    // When a vendor places an order for a client, use the client's profile for billing
    $billingProfile = null;
    if ($clientUserId !== null && $clientUserId !== '') {
        $pdo = opd_db();
        $stmt = $pdo->prepare('SELECT name, lastName, email, companyName, cellPhone, address, address2, city, state, zip FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$clientUserId]);
        $billingProfile = $stmt->fetch() ?: null;
    }

    $fullName = trim((string) ($data['name'] ?? ($user['name'] ?? '') ?? ''));
    $nameParts = $fullName !== '' ? preg_split('/\s+/', $fullName, 2) : [];

    // Billing name/address: prefer client's profile when ordering for a client
    if ($billingProfile) {
        $billingName = trim((string) ($billingProfile['name'] ?? ''));
        $billingNameParts = $billingName !== '' ? preg_split('/\s+/', $billingName, 2) : [];
        $billingFirstName = $normalize($billingNameParts[0] ?? '');
        $billingLastName = $normalize($billingProfile['lastName'] ?? ($billingNameParts[1] ?? ''));
        $billingCompany = $normalize($billingProfile['companyName'] ?? '');
        $billingAddress1 = $normalize($billingProfile['address'] ?? '');
        $billingAddress2 = $normalize($billingProfile['address2'] ?? '');
        $billingCity = $normalize($billingProfile['city'] ?? '');
        $billingStateCode = $normalize($billingProfile['state'] ?? '');
        $billingEmail = $normalize($billingProfile['email'] ?? '');
        $billingPhone = $normalize($billingProfile['cellPhone'] ?? '');
        $billingPostcode = $normalize($billingProfile['zip'] ?? '');
    } else {
        $billingFirstName = $normalize($nameParts[0] ?? '');
        $billingLastName = $normalize($nameParts[1] ?? '');
        $billingCompany = $normalize($data['company'] ?? ($user['companyName'] ?? '') ?? '');
        $billingAddress1 = $normalize($data['address1'] ?? '');
        $billingAddress2 = $normalize($data['address2'] ?? '');
        $billingCity = $normalize($data['city'] ?? '');
        $billingStateCode = $normalize($data['state'] ?? '');
        $billingEmail = $normalize($data['email'] ?? ($user['email'] ?? '') ?? '');
        $billingPhone = $normalize($data['phone'] ?? ($user['cellPhone'] ?? '') ?? '');
        $billingPostcode = $normalize($data['postal'] ?? '');
    }

    return [
        'customerName' => $data['name'] ?? ($user['name'] ?? ''),
        'customerEmail' => $data['email'] ?? ($user['email'] ?? ''),
        'customerPhone' => $data['phone'] ?? null,
        'address1' => $data['address1'] ?? null,
        'address2' => $data['address2'] ?? null,
        'city' => $data['city'] ?? null,
        'state' => $data['state'] ?? null,
        'postal' => $data['postal'] ?? null,
        'country' => $data['country'] ?? 'USA',
        'billingFirstName' => $billingFirstName,
        'billingLastName' => $billingLastName,
        'billingCompany' => $billingCompany,
        'billingAddress1' => $billingAddress1,
        'billingAddress2' => $billingAddress2,
        'billingCity' => $billingCity,
        'billingStateCode' => $billingStateCode,
        'billingEmail' => $billingEmail,
        'billingPhone' => $billingPhone,
        'billingPostcode' => $billingPostcode,
        'shippingFirstName' => $normalize($data['shippingFirstName'] ?? ''),
        'shippingLastName' => $normalize($data['shippingLastName'] ?? ''),
        'shippingCompany' => $normalize($data['shippingCompany'] ?? ''),
        'shippingPhone' => $normalize($data['shippingPhone'] ?? ''),
        'shippingAddress1' => $normalize($data['shippingAddress1'] ?? ''),
        'shippingAddress2' => $normalize($data['shippingAddress2'] ?? ''),
        'shippingCity' => $normalize($data['shippingCity'] ?? ''),
        'shippingState' => $normalize($data['shippingState'] ?? ''),
        'shippingPostcode' => $normalize($data['shippingPostcode'] ?? ''),
        'notes' => $data['notes'] ?? null,
    ];
}

/**
 * Insert order items and build a mapping from cart keys to order item IDs.
 * @return array<string, string> Map of cart key => order item ID
 */
function site_insert_order_items(PDO $pdo, string $orderId, array $items, string $now): array
{
    $itemInsert = $pdo->prepare(
        'INSERT INTO order_items (id, orderId, productId, variantId, name, productName, variantName, sku, price, quantity, total, arrivalDate, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    $itemMap = [];
    foreach ($items as $item) {
        $itemTotal = (float) $item['price'] * (int) $item['quantity'];
        $orderItemId = opd_generate_id('oi');
        $cartKey = (string) ($item['key'] ?? '');
        if ($cartKey !== '') {
            $itemMap[$cartKey] = $orderItemId;
        }
        $itemInsert->execute([
            $orderItemId,
            $orderId,
            $item['productId'],
            $item['variantId'],
            $item['name'],
            $item['productName'] ?? $item['name'] ?? '',
            $item['variantName'] ?? '',
            $item['sku'] ?? '',
            $item['price'],
            $item['quantity'],
            $itemTotal,
            $item['arrivalDate'] ?? null,
            $now,
            $now
        ]);
    }

    return $itemMap;
}

/**
 * Reduce product/variant inventory by the quantity purchased.
 * If the item has a variantId, reduce the variant inventory;
 * otherwise reduce the product inventory.
 */
function site_reduce_inventory(PDO $pdo, array $items): void
{
    $productStmt = $pdo->prepare(
        'UPDATE products SET inventory = GREATEST(COALESCE(inventory, 0) - ?, 0) WHERE id = ?'
    );
    $variantStmt = $pdo->prepare(
        'UPDATE product_variants SET inventory = GREATEST(COALESCE(inventory, 0) - ?, 0) WHERE id = ?'
    );

    foreach ($items as $item) {
        $qty = (int) ($item['quantity'] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $variantId = $item['variantId'] ?? '';
        $productId = $item['productId'] ?? '';
        if ($variantId !== '') {
            $variantStmt->execute([$qty, $variantId]);
        } elseif ($productId !== '') {
            $productStmt->execute([$qty, $productId]);
        }
    }
}

/**
 * Map cart accounting assignments to order item IDs and save to order_accounting.
 */
function site_save_order_accounting_from_cart(
    string $orderId,
    ?string $clientId,
    ?array $accounting,
    array $itemMap,
    array $items
): void {
    if (!is_array($accounting)) {
        return;
    }

    $groups = $accounting['groups'] ?? null;
    $assignments = $accounting['assignments'] ?? null;

    if (!is_array($groups) || !is_array($assignments)) {
        return;
    }

    $mappedAssignments = [];
    foreach ($assignments as $itemKey => $entries) {
        if (!is_array($entries)) {
            continue;
        }
        $targetKey = isset($itemMap[$itemKey]) ? $itemMap[$itemKey] : $itemKey;
        $mappedAssignments[$targetKey] = $entries;
    }

    if (!$mappedAssignments) {
        foreach ($items as $item) {
            $cartKey = (string) ($item['key'] ?? '');
            $orderItemId = $cartKey !== '' ? ($itemMap[$cartKey] ?? '') : '';
            if ($orderItemId === '') {
                continue;
            }
            $mappedAssignments[$orderItemId] = [[
                'groupIndex' => 0,
                'qty' => (int) ($item['quantity'] ?? 1),
            ]];
        }
    }

    site_save_order_accounting($orderId, $clientId, $groups, $mappedAssignments);
}

/**
 * Clear the user's cart after order placement.
 */
function site_clear_user_cart(PDO $pdo, ?array $user, string $now): void
{
    if ($user) {
        $cartId = site_get_cart_id($user['id']);
        $pdo->prepare("UPDATE carts SET status = 'converted', updatedAt = ? WHERE id = ?")->execute([$now, $cartId]);
        $pdo->prepare('DELETE FROM cart_items WHERE cartId = ?')->execute([$cartId]);
    } else {
        site_start_session();
        $_SESSION['site_cart'] = [];
    }
}

function site_ensure_cart_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    // arrivalDate on cart_items
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cart_items' AND column_name = 'arrivalDate'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE cart_items ADD COLUMN arrivalDate DATE AFTER quantity");
    }

    // associationSourceProductId on cart_items
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'cart_items' AND column_name = 'associationSourceProductId'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE cart_items ADD COLUMN associationSourceProductId VARCHAR(64) AFTER arrivalDate");
    }

    // arrivalDate on order_items
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'order_items' AND column_name = 'arrivalDate'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE order_items ADD COLUMN arrivalDate DATE AFTER total");
    }

    // sku on order_items
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'order_items' AND column_name = 'sku'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE order_items ADD COLUMN sku VARCHAR(100) AFTER variantName");
    }
}

function site_ensure_delivery_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    // largeDelivery on products
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'largeDelivery'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN largeDelivery TINYINT(1) DEFAULT 0 AFTER service");
    }

    // largeDelivery on product_variants
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'product_variants' AND column_name = 'largeDelivery'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE product_variants ADD COLUMN largeDelivery TINYINT(1) DEFAULT 0 AFTER price");
    }

    // wgt on products
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'wgt'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN wgt DECIMAL(10,2) AFTER longDescription");
    }

    // wgt on product_variants
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'product_variants' AND column_name = 'wgt'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE product_variants ADD COLUMN wgt DECIMAL(10,2) AFTER longDescription");
    }

    // service and daysOut on products
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'service'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN service TINYINT(1) AFTER featured");
    }

    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'products' AND column_name = 'daysOut'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE products ADD COLUMN daysOut INT AFTER service");
    }

    // deliveryZone and deliveryClass on orders
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'deliveryZone'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN deliveryZone TINYINT AFTER shippingMethod, ADD COLUMN deliveryClass VARCHAR(10) AFTER deliveryZone");
    }
}

function site_ensure_approval_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'approvalStatus'"
    );
    $stmt->execute();
    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE orders ADD COLUMN approvalStatus VARCHAR(20) DEFAULT 'Not required', ADD COLUMN approvalSentAt DATETIME");
    }
}

function site_place_order(array $data): array
{
    // 1. Validate auth and get cart
    $auth = site_validate_order_auth($data);
    if ($auth['error']) {
        return ['error' => $auth['error']];
    }
    $user = $auth['user'];
    $items = $auth['items'];

    // 2. Calculate totals
    $totals = site_calculate_order_totals($items, $data, $user);
    if (!empty($totals['deliveryError'])) {
        return ['error' => $totals['deliveryError']];
    }

    // 3. Resolve client/vendor
    $clientInfo = site_resolve_order_client($data, $user, $totals['total']);
    if ($clientInfo['error']) {
        return ['error' => $clientInfo['error']];
    }

    // 4. Normalize addresses (use client's profile for billing when ordering for a client)
    $address = site_normalize_order_address($data, $user, $clientInfo['clientUserId'] ?? null);

    // 5. Create order in transaction
    $orderId = opd_generate_id('ord');
    $orderNumber = 'OPD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $now = gmdate('Y-m-d H:i:s');
    $pdo = opd_db();

    // Determine approval status
    $monthlyLimitExceeded = !empty($clientInfo['monthlyLimitExceeded']);
    $approvalStatus = $monthlyLimitExceeded ? 'Waiting' : 'Not required';
    $approvalSentAt = $monthlyLimitExceeded ? $now : null;

    site_ensure_approval_columns($pdo);
    site_ensure_delivery_columns($pdo);
    site_ensure_cart_columns($pdo);

    $pdo->beginTransaction();
    try {
        // Insert order record
        $insert = $pdo->prepare(
            'INSERT INTO orders (id, number, status, customerName, customerEmail, customerPhone, address1, address2, city, state, postal, country, billingFirstName, billingLastName, billingCompany, billingAddress1, billingAddress2, billingCity, billingStateCode, billingEmail, billingPhone, billingPostcode, shippingFirstName, shippingLastName, shippingCompany, shippingAddress1, shippingAddress2, shippingCity, shippingStateCode, shippingPhone, shippingPostcode, notes, userId, clientId, clientUserId, orderAmount, total, tax, shipping, refundAmount, currency, shippingMethod, deliveryZone, deliveryClass, paymentStatus, fulfillmentStatus, approvalStatus, approvalSentAt, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $orderId,
            $orderNumber,
            'New',
            $address['customerName'],
            $address['customerEmail'],
            $address['customerPhone'],
            $address['address1'],
            $address['address2'],
            $address['city'],
            $address['state'],
            $address['postal'],
            $address['country'],
            $address['billingFirstName'],
            $address['billingLastName'],
            $address['billingCompany'],
            $address['billingAddress1'],
            $address['billingAddress2'],
            $address['billingCity'],
            $address['billingStateCode'],
            $address['billingEmail'],
            $address['billingPhone'],
            $address['billingPostcode'],
            !empty($address['shippingFirstName']) ? $address['shippingFirstName'] : $address['billingFirstName'],
            !empty($address['shippingLastName']) ? $address['shippingLastName'] : $address['billingLastName'],
            !empty($address['shippingCompany']) ? $address['shippingCompany'] : $address['billingCompany'],
            !empty($address['shippingAddress1']) ? $address['shippingAddress1'] : $address['billingAddress1'],
            !empty($address['shippingAddress2']) ? $address['shippingAddress2'] : $address['billingAddress2'],
            !empty($address['shippingCity']) ? $address['shippingCity'] : $address['billingCity'],
            !empty($address['shippingState']) ? $address['shippingState'] : $address['billingStateCode'],
            !empty($address['shippingPhone']) ? $address['shippingPhone'] : $address['billingPhone'],
            !empty($address['shippingPostcode']) ? $address['shippingPostcode'] : $address['billingPostcode'],
            $address['notes'],
            $user ? $user['id'] : null,
            $clientInfo['clientId'],
            $clientInfo['clientUserId'],
            $totals['subtotal'],
            $totals['total'],
            $totals['tax'],
            $totals['shipping'],
            0.0,  // refundAmount
            'USD',
            $totals['shippingMethod'],
            $totals['deliveryZone'],
            $totals['deliveryClass'],
            'unpaid',
            'unfulfilled',
            $approvalStatus,
            $approvalSentAt,
            $now,
            $now
        ]);

        // 6. Insert order items
        $itemMap = site_insert_order_items($pdo, $orderId, $items, $now);

        // 6b. Reduce inventory for purchased items
        site_reduce_inventory($pdo, $items);

        // 7. Save accounting data
        $accounting = $data['accounting'] ?? null;
        site_save_order_accounting_from_cart($orderId, $clientInfo['clientId'], $accounting, $itemMap, $items);

        // 8. Clear cart
        site_clear_user_cart($pdo, $user, $now);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Order placement failed: ' . $e->getMessage());
        return ['error' => 'Unable to place order right now. Please try again.'];
    }

    if ($approvalStatus === 'Waiting' && $clientInfo['clientUserId'] && $user) {
        site_send_approval_sms($clientInfo['clientUserId'], $user, $totals['total']);
    }

    return ['orderId' => $orderId, 'orderNumber' => $orderNumber];
}

function site_send_approval_sms(string $clientUserId, array $vendorUser, float $orderTotal): void
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT cellPhone FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$clientUserId]);
    $clientUser = $stmt->fetch();
    if (!$clientUser) {
        return;
    }

    $phone = opd_normalize_us_phone(trim((string) ($clientUser['cellPhone'] ?? '')));
    if ($phone === null) {
        return;
    }

    $template = site_get_setting_value('vendor_limit_text');
    if ($template === null || trim($template) === '') {
        return;
    }

    $vendorUserId = (string) ($vendorUser['id'] ?? '');
    $vendorName = trim((string) ($vendorUser['name'] ?? ($vendorUser['email'] ?? '')));
    $monthStart = gmdate('Y-m-01 00:00:00');
    $stmt = $pdo->prepare(
        'SELECT COALESCE(SUM(total), 0) AS total
         FROM orders
         WHERE userId = ? AND clientUserId = ? AND createdAt >= ?'
    );
    $stmt->execute([$vendorUserId, $clientUserId, $monthStart]);
    $row = $stmt->fetch();
    $monthlyTotal = (float) ($row['total'] ?? 0);

    $baseUrl = site_get_base_url();
    $link = $baseUrl !== '' ? $baseUrl . '/dashboard-orders.php' : '';

    $message = str_replace(
        ['{vendor_name}', '{order_total}', '{monthly_total}', '{link}'],
        [$vendorName, '$' . number_format($orderTotal, 2), '$' . number_format($monthlyTotal, 2), $link],
        $template
    );

    $to = '1' . $phone;
    experttexting_send_sms($to, $message);
}

function site_process_auto_approvals(): int
{
    $pdo = opd_db();
    $autoApproveMinutes = (int) (site_get_setting_value('auto_approve_time') ?? '60');
    if ($autoApproveMinutes <= 0) {
        $autoApproveMinutes = 60;
    }

    $cutoff = gmdate('Y-m-d H:i:s', time() - ($autoApproveMinutes * 60));

    $stmt = $pdo->prepare(
        "SELECT id, userId, clientUserId
         FROM orders
         WHERE approvalStatus = 'Waiting'
           AND approvalSentAt IS NOT NULL
           AND approvalSentAt <= ?"
    );
    $stmt->execute([$cutoff]);
    $orders = $stmt->fetchAll();

    $count = 0;
    $update = $pdo->prepare(
        "UPDATE orders SET approvalStatus = 'Auto Approved', updatedAt = ? WHERE id = ?"
    );
    $now = gmdate('Y-m-d H:i:s');

    foreach ($orders as $order) {
        $clientUserId = (string) ($order['clientUserId'] ?? '');
        $vendorUserId = (string) ($order['userId'] ?? '');

        if ($clientUserId === '' || $vendorUserId === '') {
            continue;
        }

        $vendorRecord = site_find_vendor_record_for_client($clientUserId, $vendorUserId, '');
        if (!$vendorRecord || empty($vendorRecord['autoApprove'])) {
            continue;
        }

        $update->execute([$now, $order['id']]);
        $count++;
    }

    return $count;
}

function site_get_orders_for_user(string $userId): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        'SELECT * FROM orders WHERE userId = ? OR clientUserId = ? ORDER BY createdAt DESC'
    );
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll();
}

function site_ensure_favorite_tables(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS favorite_categories (
            id VARCHAR(64) PRIMARY KEY,
            userId VARCHAR(64),
            name VARCHAR(120),
            sortOrder INT,
            createdAt DATETIME,
            updatedAt DATETIME
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS favorite_entries (
            id VARCHAR(64) PRIMARY KEY,
            userId VARCHAR(64),
            categoryId VARCHAR(64),
            productId VARCHAR(64),
            variantId VARCHAR(64),
            quantity INT,
            splitsJson MEDIUMTEXT,
            sortOrder INT,
            createdAt DATETIME,
            updatedAt DATETIME
        )'
    );
}

function site_legacy_favorites_enabled(): bool
{
    $flag = $_ENV['OPD_ENABLE_LEGACY_FAVORITES'] ?? getenv('OPD_ENABLE_LEGACY_FAVORITES') ?? '';
    $flag = strtolower(trim((string) $flag));
    return in_array($flag, ['1', 'true', 'yes', 'on'], true);
}

function site_table_exists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $stmt = $pdo->prepare(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        $exists = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $exists = false;
    }
    $cache[$table] = $exists;
    return $exists;
}

function site_get_uncategorized_favorite_category_id(PDO $pdo, string $userId): string
{
    site_ensure_favorite_tables($pdo);
    $stmt = $pdo->prepare(
        'SELECT id FROM favorite_categories WHERE userId = ? AND LOWER(name) = LOWER(?) ORDER BY sortOrder ASC LIMIT 1'
    );
    $stmt->execute([$userId, 'Uncategorised']);
    $row = $stmt->fetch();
    if ($row && !empty($row['id'])) {
        return (string) $row['id'];
    }
    $id = opd_generate_id('favcat');
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO favorite_categories (id, userId, name, sortOrder, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$id, $userId, 'Uncategorised', 0, $now, $now]);
    return $id;
}

function site_migrate_legacy_favorites(string $userId): void
{
    $pdo = opd_db();
    if (!site_legacy_favorites_enabled()) {
        return;
    }
    if (!site_table_exists($pdo, 'favorites')) {
        return;
    }
    site_ensure_favorite_tables($pdo);
    $legacy = $pdo->prepare('SELECT productId FROM favorites WHERE userId = ?');
    $legacy->execute([$userId]);
    $rows = $legacy->fetchAll();
    if (!$rows) {
        return;
    }
    $categoryId = site_get_uncategorized_favorite_category_id($pdo, $userId);
    $existingStmt = $pdo->prepare(
        'SELECT productId FROM favorite_entries WHERE userId = ? AND categoryId = ? AND variantId IS NULL'
    );
    $existingStmt->execute([$userId, $categoryId]);
    $existing = array_map(fn($row) => (string) ($row['productId'] ?? ''), $existingStmt->fetchAll());
    $existingMap = array_fill_keys($existing, true);
    $maxStmt = $pdo->prepare('SELECT COALESCE(MAX(sortOrder), 0) AS maxSort FROM favorite_entries WHERE userId = ? AND categoryId = ?');
    $maxStmt->execute([$userId, $categoryId]);
    $maxRow = $maxStmt->fetch();
    $nextSort = (int) ($maxRow['maxSort'] ?? 0);
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO favorite_entries (id, userId, categoryId, productId, variantId, quantity, splitsJson, sortOrder, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($rows as $row) {
        $productId = (string) ($row['productId'] ?? '');
        if ($productId === '' || isset($existingMap[$productId])) {
            continue;
        }
        $nextSort += 1;
        $insert->execute([
            opd_generate_id('favent'),
            $userId,
            $categoryId,
            $productId,
            null,
            1,
            '[]',
            $nextSort,
            $now,
            $now,
        ]);
        $existingMap[$productId] = true;
    }
}

function site_get_favorites(string $userId): array
{
    $pdo = opd_db();
    site_ensure_favorite_tables($pdo);
    site_ensure_product_images_table($pdo);
    site_migrate_legacy_favorites($userId);
    $stmt = $pdo->prepare(
        'SELECT fe.id, fe.productId, fe.variantId, p.name, p.price, p.category, COALESCE(pi.url, p.imageUrl) AS imageUrl
         FROM favorite_entries fe
         JOIN products p ON p.id = fe.productId
         LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
         WHERE fe.userId = ?
         ORDER BY fe.createdAt DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function site_get_favorite_categories(string $userId): array
{
    $pdo = opd_db();
    site_migrate_legacy_favorites($userId);
    $uncatId = site_get_uncategorized_favorite_category_id($pdo, $userId);
    $stmt = $pdo->prepare(
        'SELECT * FROM favorite_categories WHERE userId = ?
         ORDER BY CASE WHEN id = ? THEN 0 ELSE 1 END, COALESCE(sortOrder, 999999), name'
    );
    $stmt->execute([$userId, $uncatId]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['isDefault'] = ($row['id'] ?? '') === $uncatId;
    }
    return $rows;
}

function site_get_favorite_items(string $userId, string $categoryId): array
{
    $pdo = opd_db();
    site_ensure_favorite_tables($pdo);
    site_ensure_product_images_table($pdo);
    $stmt = $pdo->prepare(
        'SELECT fe.*, p.name AS productName, p.price AS productPrice, p.sku AS productSku, p.category AS productCategory,
                pv.name AS variantName, pv.price AS variantPrice, pv.sku AS variantSku,
                COALESCE(pi.url, p.imageUrl) AS imageUrl
         FROM favorite_entries fe
         JOIN products p ON p.id = fe.productId
         LEFT JOIN product_variants pv ON pv.id = fe.variantId
         LEFT JOIN product_images pi ON pi.productId = p.id AND pi.isPrimary = 1
         WHERE fe.userId = ? AND fe.categoryId = ?
         ORDER BY COALESCE(fe.sortOrder, 999999), fe.createdAt ASC'
    );
    $stmt->execute([$userId, $categoryId]);
    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $splits = json_decode($row['splitsJson'] ?? '[]', true);
        if (!is_array($splits)) {
            $splits = [];
        }
        $quantity = (int) ($row['quantity'] ?? 1);
        if ($quantity < 1) {
            $quantity = 1;
        }
        $price = $row['variantPrice'] ?? null;
        if ($price === null || $price === '') {
            $price = $row['productPrice'] ?? 0;
        }
        $items[] = [
            'id' => $row['id'] ?? '',
            'productId' => $row['productId'] ?? '',
            'variantId' => $row['variantId'] ?? null,
            'name' => ($row['variantName'] ?: $row['productName']) ?: 'Product',
            'productName' => $row['productName'] ?? '',
            'variantName' => $row['variantName'] ?? '',
            'sku' => $row['variantSku'] ?: ($row['productSku'] ?? ''),
            'price' => (float) $price,
            'quantity' => $quantity,
            'splits' => $splits,
            'imageUrl' => $row['imageUrl'] ?? null,
            'createdAt' => $row['createdAt'] ?? null,
        ];
    }
    return $items;
}

function site_get_favorite_item_category_ids(string $userId, string $productId, ?string $variantId = null): array
{
    $pdo = opd_db();
    site_ensure_favorite_tables($pdo);
    $stmt = $pdo->prepare(
        'SELECT categoryId FROM favorite_entries WHERE userId = ? AND productId = ? AND variantId <=> ?'
    );
    $stmt->execute([$userId, $productId, $variantId]);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function site_save_favorite_categories(string $userId, array $categories): array
{
    $pdo = opd_db();
    site_ensure_favorite_tables($pdo);
    $uncatId = site_get_uncategorized_favorite_category_id($pdo, $userId);
    $existingStmt = $pdo->prepare('SELECT id FROM favorite_categories WHERE userId = ?');
    $existingStmt->execute([$userId]);
    $existing = array_fill_keys($existingStmt->fetchAll(PDO::FETCH_COLUMN), true);
    $keep = [$uncatId => true];
    $now = gmdate('Y-m-d H:i:s');
    $sortOrder = 1;
    $update = $pdo->prepare('UPDATE favorite_categories SET name = ?, sortOrder = ?, updatedAt = ? WHERE id = ? AND userId = ?');
    $insert = $pdo->prepare(
        'INSERT INTO favorite_categories (id, userId, name, sortOrder, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?)'
    );
    foreach ($categories as $category) {
        if (!is_array($category)) {
            continue;
        }
        $name = trim((string) ($category['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $id = (string) ($category['id'] ?? '');
        if ($id === $uncatId) {
            continue;
        }
        if ($id !== '' && isset($existing[$id])) {
            $update->execute([$name, $sortOrder, $now, $id, $userId]);
            $keep[$id] = true;
        } else {
            $newId = opd_generate_id('favcat');
            $insert->execute([$newId, $userId, $name, $sortOrder, $now, $now]);
            $keep[$newId] = true;
        }
        $sortOrder += 1;
    }
    $placeholders = implode(',', array_fill(0, count($keep), '?'));
    $deleteIds = [];
    if ($placeholders !== '') {
        $deleteStmt = $pdo->prepare(
            "SELECT id FROM favorite_categories WHERE userId = ? AND id NOT IN ({$placeholders})"
        );
        $deleteStmt->execute(array_merge([$userId], array_keys($keep)));
        $deleteIds = array_map('strval', $deleteStmt->fetchAll(PDO::FETCH_COLUMN));
    }
    if ($deleteIds) {
        $deleteEntries = $pdo->prepare('DELETE FROM favorite_entries WHERE userId = ? AND categoryId = ?');
        foreach ($deleteIds as $deleteId) {
            $deleteEntries->execute([$userId, $deleteId]);
        }
        $deleteCategories = $pdo->prepare('DELETE FROM favorite_categories WHERE userId = ? AND id = ?');
        foreach ($deleteIds as $deleteId) {
            $deleteCategories->execute([$userId, $deleteId]);
        }
    }
    return site_get_favorite_categories($userId);
}

function site_set_favorite_item_categories(string $userId, string $productId, ?string $variantId, array $categoryIds): array
{
    $pdo = opd_db();
    site_ensure_favorite_tables($pdo);
    $uncatId = site_get_uncategorized_favorite_category_id($pdo, $userId);
    $categoryIds = array_values(array_unique(array_filter($categoryIds, fn($id) => is_string($id) && $id !== '')));
    if (!$categoryIds) {
        $categoryIds = [];
    }

    $validStmt = $pdo->prepare('SELECT id FROM favorite_categories WHERE userId = ?');
    $validStmt->execute([$userId]);
    $validIds = array_fill_keys($validStmt->fetchAll(PDO::FETCH_COLUMN), true);
    $categoryIds = array_values(array_filter($categoryIds, fn($id) => isset($validIds[$id])));
    $existingStmt = $pdo->prepare(
        'SELECT id, categoryId FROM favorite_entries WHERE userId = ? AND productId = ? AND variantId <=> ?'
    );
    $existingStmt->execute([$userId, $productId, $variantId]);
    $existingRows = $existingStmt->fetchAll();
    $existingByCategory = [];
    foreach ($existingRows as $row) {
        $existingByCategory[(string) ($row['categoryId'] ?? '')] = (string) ($row['id'] ?? '');
    }

    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO favorite_entries (id, userId, categoryId, productId, variantId, quantity, splitsJson, sortOrder, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($categoryIds as $categoryId) {
        if (isset($existingByCategory[$categoryId])) {
            unset($existingByCategory[$categoryId]);
            continue;
        }
        $maxStmt = $pdo->prepare(
            'SELECT COALESCE(MAX(sortOrder), 0) AS maxSort FROM favorite_entries WHERE userId = ? AND categoryId = ?'
        );
        $maxStmt->execute([$userId, $categoryId]);
        $maxRow = $maxStmt->fetch();
        $sortOrder = (int) ($maxRow['maxSort'] ?? 0) + 1;
        $insert->execute([
            opd_generate_id('favent'),
            $userId,
            $categoryId,
            $productId,
            $variantId,
            1,
            '[]',
            $sortOrder,
            $now,
            $now,
        ]);
    }
    if ($existingByCategory) {
        $delete = $pdo->prepare('DELETE FROM favorite_entries WHERE userId = ? AND id = ?');
        foreach ($existingByCategory as $entryId) {
            $delete->execute([$userId, $entryId]);
        }
    }
    return $categoryIds;
}

function site_update_favorite_entries(string $userId, array $updates): void
{
    $pdo = opd_db();
    site_ensure_favorite_tables($pdo);
    $now = gmdate('Y-m-d H:i:s');
    $update = $pdo->prepare(
        'UPDATE favorite_entries SET quantity = ?, splitsJson = ?, updatedAt = ? WHERE id = ? AND userId = ?'
    );
    foreach ($updates as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $id = (string) ($entry['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $quantity = (int) ($entry['quantity'] ?? 1);
        if ($quantity < 1) {
            $quantity = 1;
        }
        $splits = $entry['splits'] ?? [];
        if (!is_array($splits)) {
            $splits = [];
        }
        $normalized = [];
        foreach ($splits as $split) {
            if (!is_array($split)) {
                continue;
            }
            $qty = (int) ($split['qty'] ?? 0);
            $normalized[] = [
                'location' => trim((string) ($split['location'] ?? '')),
                'code1' => trim((string) ($split['code1'] ?? '')),
                'code2' => trim((string) ($split['code2'] ?? '')),
                'qty' => $qty,
            ];
        }
        if (!$normalized) {
            $normalized = [[
                'location' => '',
                'code1' => '',
                'code2' => '',
                'qty' => $quantity,
            ]];
        }
        $totalQty = array_sum(array_map(fn($item) => (int) ($item['qty'] ?? 0), $normalized));
        if ($totalQty !== $quantity) {
            $lastIndex = count($normalized) - 1;
            $normalized[$lastIndex]['qty'] = max(0, $quantity - ($totalQty - (int) $normalized[$lastIndex]['qty']));
        }
        $splitsJson = json_encode($normalized);
        if ($splitsJson === false) {
            $splitsJson = '[]';
        }
        $update->execute([$quantity, $splitsJson, $now, $id, $userId]);
    }
}

function site_add_favorites_to_cart(string $userId, array $entryIds): array
{
    $pdo = opd_db();
    site_ensure_favorite_tables($pdo);
    $entryIds = array_values(array_unique(array_filter($entryIds, fn($id) => is_string($id) && $id !== '')));
    if (!$entryIds) {
        return ['added' => 0];
    }
    $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT id, productId, variantId, quantity, splitsJson
         FROM favorite_entries
         WHERE userId = ? AND id IN ({$placeholders})"
    );
    $stmt->execute(array_merge([$userId], $entryIds));
    $entries = $stmt->fetchAll();
    if (!$entries) {
        return ['added' => 0];
    }

    $cartId = site_get_cart_id($userId);
    $accounting = site_get_cart_accounting($cartId, null);
    $groups = is_array($accounting['groups'] ?? null) ? $accounting['groups'] : [];
    $assignments = is_array($accounting['assignments'] ?? null) ? $accounting['assignments'] : [];

    $normalizeGroup = function ($group): array {
        return [
            'location' => trim((string) ($group['location'] ?? '')),
            'code1' => trim((string) ($group['code1'] ?? '')),
            'code2' => trim((string) ($group['code2'] ?? '')),
        ];
    };
    $groups = array_map($normalizeGroup, $groups);
    $added = 0;

    foreach ($entries as $entry) {
        $productId = (string) ($entry['productId'] ?? '');
        if ($productId === '') {
            continue;
        }
        $variantId = $entry['variantId'] ?? null;
        $qty = (int) ($entry['quantity'] ?? 1);
        if ($qty < 1) {
            $qty = 1;
        }
        $itemKey = site_add_to_cart($productId, $qty, $variantId ? (string) $variantId : null);
        if ($itemKey === null || $itemKey === '') {
            continue;
        }
        if (isset($assignments[$itemKey])) {
            $added += 1;
            continue;
        }
        $splits = json_decode($entry['splitsJson'] ?? '[]', true);
        if (!is_array($splits) || !$splits) {
            $splits = [[
                'location' => '',
                'code1' => '',
                'code2' => '',
                'qty' => $qty,
            ]];
        }
        $normalizedSplits = [];
        foreach ($splits as $split) {
            if (!is_array($split)) {
                continue;
            }
            $normalizedSplits[] = [
                'location' => trim((string) ($split['location'] ?? '')),
                'code1' => trim((string) ($split['code1'] ?? '')),
                'code2' => trim((string) ($split['code2'] ?? '')),
                'qty' => (int) ($split['qty'] ?? 0),
            ];
        }
        if (!$normalizedSplits) {
            $normalizedSplits = [[
                'location' => '',
                'code1' => '',
                'code2' => '',
                'qty' => $qty,
            ]];
        }
        $totalQty = array_sum(array_map(fn($item) => (int) ($item['qty'] ?? 0), $normalizedSplits));
        if ($totalQty !== $qty) {
            $lastIndex = count($normalizedSplits) - 1;
            $normalizedSplits[$lastIndex]['qty'] = max(0, $qty - ($totalQty - (int) $normalizedSplits[$lastIndex]['qty']));
        }

        $itemAssignments = [];
        foreach ($normalizedSplits as $split) {
            $group = $normalizeGroup($split);
            $groupIndex = null;
            foreach ($groups as $idx => $existing) {
                if ($existing['location'] === $group['location']
                    && $existing['code1'] === $group['code1']
                    && $existing['code2'] === $group['code2']
                ) {
                    $groupIndex = $idx;
                    break;
                }
            }
            if ($groupIndex === null) {
                $groups[] = $group;
                $groupIndex = count($groups) - 1;
            }
            $itemAssignments[] = [
                'groupIndex' => $groupIndex,
                'qty' => (int) ($split['qty'] ?? 0),
            ];
        }
        $assignments[$itemKey] = $itemAssignments;
        $added += 1;
    }

    if (!$groups) {
        $groups = [['location' => '', 'code1' => '', 'code2' => '']];
    }
    site_save_cart_accounting($cartId, null, $groups, $assignments);
    return ['added' => $added];
}

function site_add_favorite(string $userId, string $productId): void
{
    $pdo = opd_db();
    $categoryId = site_get_uncategorized_favorite_category_id($pdo, $userId);
    site_set_favorite_item_categories($userId, $productId, null, [$categoryId]);
}

function site_remove_favorite(string $favoriteId): void
{
    $pdo = opd_db();
    site_ensure_favorite_tables($pdo);
    $delete = $pdo->prepare('DELETE FROM favorite_entries WHERE id = ?');
    $delete->execute([$favoriteId]);
    if ($delete->rowCount() > 0) {
        return;
    }
    $stmt = $pdo->prepare('DELETE FROM favorites WHERE id = ?');
    $stmt->execute([$favoriteId]);
}

function site_get_payment_methods(string $userId): array
{
    $pdo = opd_db();
    site_ensure_payment_methods_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM payment_methods WHERE userId = ? ORDER BY isPrimary DESC, updatedAt DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function site_get_payment_method(string $userId, string $paymentMethodId): ?array
{
    if ($paymentMethodId === '') {
        return null;
    }
    $pdo = opd_db();
    site_ensure_payment_methods_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM payment_methods WHERE userId = ? AND id = ? LIMIT 1');
    $stmt->execute([$userId, $paymentMethodId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function site_format_payment_method_label(array $method): string
{
    $label = trim((string) ($method['label'] ?? ''));
    if ($label !== '') {
        return $label;
    }
    $parts = [];
    $brand = trim((string) ($method['brand'] ?? ''));
    $type = trim((string) ($method['type'] ?? ''));
    if ($brand !== '') {
        $parts[] = $brand;
    } elseif ($type !== '') {
        $parts[] = $type;
    }
    $last4 = trim((string) ($method['last4'] ?? ''));
    if ($last4 !== '') {
        $parts[] = 'ending ' . $last4;
    }
    if (!empty($method['expMonth']) && !empty($method['expYear'])) {
        $parts[] = 'exp ' . sprintf('%02d/%d', (int) $method['expMonth'], (int) $method['expYear']);
    }
    return $parts ? implode(' - ', $parts) : 'Saved payment method';
}

function site_update_payment_method(string $userId, string $paymentMethodId, array $fields): bool
{
    $pdo = opd_db();
    site_ensure_payment_methods_table($pdo);
    $allowed = ['label', 'type', 'last4', 'expMonth', 'expYear'];
    $sets = [];
    $params = [];
    foreach ($allowed as $col) {
        if (array_key_exists($col, $fields)) {
            $sets[] = "$col = ?";
            $params[] = $fields[$col];
        }
    }
    if (!$sets) {
        return false;
    }
    $sets[] = 'updatedAt = ?';
    $params[] = gmdate('Y-m-d H:i:s');
    $params[] = $userId;
    $params[] = $paymentMethodId;
    $stmt = $pdo->prepare('UPDATE payment_methods SET ' . implode(', ', $sets) . ' WHERE userId = ? AND id = ?');
    $stmt->execute($params);
    return $stmt->rowCount() > 0;
}

function site_delete_payment_method(string $userId, string $paymentMethodId): ?array
{
    $pdo = opd_db();
    site_ensure_payment_methods_table($pdo);
    $stmt = $pdo->prepare('SELECT * FROM payment_methods WHERE userId = ? AND id = ? LIMIT 1');
    $stmt->execute([$userId, $paymentMethodId]);
    $method = $stmt->fetch();
    if (!$method) {
        return null;
    }
    $pdo->prepare('DELETE FROM payment_methods WHERE userId = ? AND id = ?')->execute([$userId, $paymentMethodId]);
    return $method;
}

function site_set_primary_payment_method(string $userId, string $paymentMethodId): bool
{
    $pdo = opd_db();
    site_ensure_payment_methods_table($pdo);
    $now = gmdate('Y-m-d H:i:s');
    // Clear existing primary
    $pdo->prepare('UPDATE payment_methods SET isPrimary = 0, updatedAt = ? WHERE userId = ? AND isPrimary = 1')
        ->execute([$now, $userId]);
    // Set new primary
    $stmt = $pdo->prepare('UPDATE payment_methods SET isPrimary = 1, updatedAt = ? WHERE userId = ? AND id = ?');
    $stmt->execute([$now, $userId, $paymentMethodId]);
    return $stmt->rowCount() > 0;
}

function site_ensure_payment_methods_table(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS payment_methods (
            id VARCHAR(64) PRIMARY KEY,
            userId VARCHAR(64),
            label VARCHAR(255),
            type VARCHAR(50),
            brand VARCHAR(50),
            last4 VARCHAR(8),
            stripePaymentMethodId VARCHAR(255),
            expMonth INT,
            expYear INT,
            isPrimary TINYINT(1) DEFAULT 0,
            createdAt DATETIME,
            updatedAt DATETIME
        )'
    );
    // Ensure isPrimary column exists for older tables
    try {
        $pdo->exec('ALTER TABLE payment_methods ADD COLUMN isPrimary TINYINT(1) DEFAULT 0');
    } catch (Throwable $e) {
        // Column already exists
    }
}

function site_simple_list(string $table, string $userId): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare("SELECT * FROM {$table} WHERE userId = ? ORDER BY updatedAt DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function site_simple_create(string $table, string $userId, array $fields): void
{
    $pdo = opd_db();
    $columns = array_keys($fields);
    $placeholders = implode(',', array_fill(0, count($columns) + 4, '?'));
    $sql = sprintf(
        'INSERT INTO %s (id, userId, %s, createdAt, updatedAt) VALUES (%s)',
        $table,
        implode(',', $columns),
        $placeholders
    );
    $values = [opd_generate_id('row'), $userId];
    foreach ($columns as $column) {
        $values[] = $fields[$column];
    }
    $now = gmdate('Y-m-d H:i:s');
    $values[] = $now;
    $values[] = $now;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
}

function site_simple_delete(string $table, string $id): void
{
    $pdo = opd_db();
    $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
    $stmt->execute([$id]);
}

// --- Equipment helpers ---

function site_ensure_equipment_images_table(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS equipment_images (
            id VARCHAR(64) PRIMARY KEY,
            equipmentId VARCHAR(64),
            url TEXT,
            isPrimary TINYINT(1) DEFAULT 0,
            sortOrder INT DEFAULT 0,
            createdAt DATETIME,
            updatedAt DATETIME,
            INDEX idx_equipmentId (equipmentId)
        )'
    );
}

function site_ensure_equipment_columns(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $cols = [];
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM equipment");
        foreach ($stmt->fetchAll() as $row) {
            $cols[$row['Field']] = true;
        }
    } catch (Throwable $e) {
        return;
    }
    $needed = [
        'contactName' => "ADD COLUMN contactName VARCHAR(255) AFTER notes",
        'contactPhone' => "ADD COLUMN contactPhone VARCHAR(50) AFTER contactName",
        'contactEmail' => "ADD COLUMN contactEmail VARCHAR(255) AFTER contactPhone",
        'quantity' => "ADD COLUMN quantity INT DEFAULT 1 AFTER contactEmail",
        'price' => "ADD COLUMN price DECIMAL(10,2) AFTER quantity",
        'productId' => "ADD COLUMN productId VARCHAR(64) AFTER price",
    ];
    foreach ($needed as $col => $ddl) {
        if (!isset($cols[$col])) {
            try {
                $pdo->exec("ALTER TABLE equipment {$ddl}");
            } catch (Throwable $e) {
                // column may already exist
            }
        }
    }
}

function site_equipment_create(string $userId, array $fields): string
{
    $pdo = opd_db();
    site_ensure_equipment_columns($pdo);
    $id = opd_generate_id('equip');
    $now = gmdate('Y-m-d H:i:s');
    $notes = opd_sanitize_plain_text((string) ($fields['notes'] ?? ''), 4000);
    $stmt = $pdo->prepare(
        'INSERT INTO equipment (id, userId, name, serial, status, location, notes,
         contactName, contactPhone, contactEmail, quantity, price, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $id,
        $userId,
        $fields['name'] ?? '',
        $fields['serial'] ?? '',
        'Pending Approval',
        $fields['location'] ?? '',
        $notes,
        $fields['contactName'] ?? '',
        $fields['contactPhone'] ?? '',
        $fields['contactEmail'] ?? '',
        (int) ($fields['quantity'] ?? 1),
        ($fields['price'] ?? '') !== '' ? (float) $fields['price'] : null,
        $now,
        $now,
    ]);
    return $id;
}

function site_equipment_update(string $id, string $userId, array $fields): void
{
    $pdo = opd_db();
    site_ensure_equipment_columns($pdo);
    $now = gmdate('Y-m-d H:i:s');
    $notes = opd_sanitize_plain_text((string) ($fields['notes'] ?? ''), 4000);
    $stmt = $pdo->prepare(
        'UPDATE equipment SET name = ?, serial = ?, location = ?, notes = ?,
         contactName = ?, contactPhone = ?, contactEmail = ?, quantity = ?, price = ?,
         updatedAt = ?
         WHERE id = ? AND userId = ?'
    );
    $stmt->execute([
        $fields['name'] ?? '',
        $fields['serial'] ?? '',
        $fields['location'] ?? '',
        $notes,
        $fields['contactName'] ?? '',
        $fields['contactPhone'] ?? '',
        $fields['contactEmail'] ?? '',
        (int) ($fields['quantity'] ?? 1),
        ($fields['price'] ?? '') !== '' ? (float) $fields['price'] : null,
        $now,
        $id,
        $userId,
    ]);
}

function site_equipment_list(string $userId): array
{
    $pdo = opd_db();
    site_ensure_equipment_columns($pdo);
    site_ensure_equipment_images_table($pdo);
    $stmt = $pdo->prepare(
        'SELECT e.*, ei.url AS primaryImageUrl
         FROM equipment e
         LEFT JOIN equipment_images ei ON ei.equipmentId = e.id AND ei.isPrimary = 1
         WHERE e.userId = ?
         ORDER BY e.updatedAt DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function site_equipment_get(string $id): ?array
{
    $pdo = opd_db();
    site_ensure_equipment_columns($pdo);
    $stmt = $pdo->prepare('SELECT * FROM equipment WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function site_equipment_delete(string $id, string $userId): void
{
    $pdo = opd_db();
    site_ensure_equipment_columns($pdo);

    // If equipment has a linked product, deactivate it
    $eq = $pdo->prepare('SELECT productId FROM equipment WHERE id = ? AND userId = ? LIMIT 1');
    $eq->execute([$id, $userId]);
    $row = $eq->fetch();
    if ($row && !empty($row['productId'])) {
        $now = gmdate('Y-m-d H:i:s');
        $pdo->prepare("UPDATE products SET status = 'inactive', updatedAt = ? WHERE id = ?")
            ->execute([$now, $row['productId']]);
    }

    // Delete images
    site_ensure_equipment_images_table($pdo);
    $pdo->prepare('DELETE FROM equipment_images WHERE equipmentId = ?')->execute([$id]);
    // Delete equipment
    $pdo->prepare('DELETE FROM equipment WHERE id = ? AND userId = ?')->execute([$id, $userId]);
}

function site_equipment_images(string $equipmentId): array
{
    $pdo = opd_db();
    site_ensure_equipment_images_table($pdo);
    $stmt = $pdo->prepare(
        'SELECT * FROM equipment_images WHERE equipmentId = ?
         ORDER BY isPrimary DESC, sortOrder ASC, createdAt ASC'
    );
    $stmt->execute([$equipmentId]);
    return $stmt->fetchAll();
}

function site_equipment_set_primary_image(string $equipmentId, string $imageId): void
{
    $pdo = opd_db();
    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare('UPDATE equipment_images SET isPrimary = 0, updatedAt = ? WHERE equipmentId = ?')
        ->execute([$now, $equipmentId]);
    $pdo->prepare('UPDATE equipment_images SET isPrimary = 1, updatedAt = ? WHERE id = ? AND equipmentId = ?')
        ->execute([$now, $imageId, $equipmentId]);
}

function site_equipment_delete_image(string $imageId, string $equipmentId): void
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT isPrimary FROM equipment_images WHERE id = ? AND equipmentId = ? LIMIT 1');
    $stmt->execute([$imageId, $equipmentId]);
    $row = $stmt->fetch();
    if (!$row) {
        return;
    }
    $wasPrimary = !empty($row['isPrimary']);
    $pdo->prepare('DELETE FROM equipment_images WHERE id = ?')->execute([$imageId]);

    if ($wasPrimary) {
        $now = gmdate('Y-m-d H:i:s');
        $next = $pdo->prepare(
            'SELECT id FROM equipment_images WHERE equipmentId = ? ORDER BY sortOrder ASC LIMIT 1'
        );
        $next->execute([$equipmentId]);
        $nextRow = $next->fetch();
        if ($nextRow) {
            $pdo->prepare('UPDATE equipment_images SET isPrimary = 1, updatedAt = ? WHERE id = ?')
                ->execute([$now, $nextRow['id']]);
        }
    }
}

function site_equipment_all_for_admin(): array
{
    $pdo = opd_db();
    site_ensure_equipment_columns($pdo);
    site_ensure_equipment_images_table($pdo);
    $stmt = $pdo->query(
        "SELECT e.*, TRIM(CONCAT(COALESCE(u.name,''), ' ', COALESCE(u.lastName,''))) AS userName,
                u.email AS userEmail,
                ei.url AS primaryImageUrl
         FROM equipment e
         LEFT JOIN users u ON u.id = e.userId
         LEFT JOIN equipment_images ei ON ei.equipmentId = e.id AND ei.isPrimary = 1
         ORDER BY e.createdAt DESC"
    );
    return $stmt->fetchAll();
}

function site_equipment_approve(string $equipmentId): ?array
{
    $pdo = opd_db();
    site_ensure_equipment_columns($pdo);
    $equipment = site_equipment_get($equipmentId);
    if (!$equipment) {
        return null;
    }

    // Prevent double-approve
    if ($equipment['status'] === 'Active' && !empty($equipment['productId'])) {
        return ['productId' => $equipment['productId'], 'equipment' => $equipment];
    }

    $now = gmdate('Y-m-d H:i:s');

    // Build contact info for short description
    $shortParts = [];
    if (!empty($equipment['contactName'])) {
        $shortParts[] = 'Contact: ' . $equipment['contactName'];
    }
    if (!empty($equipment['contactPhone'])) {
        $shortParts[] = 'Phone: ' . $equipment['contactPhone'];
    }
    if (!empty($equipment['contactEmail'])) {
        $shortParts[] = 'Email: ' . $equipment['contactEmail'];
    }
    $shortDescription = implode("\n", $shortParts);

    // Build long description
    $longParts = [];
    if (!empty($equipment['location'])) {
        $longParts[] = 'Location: ' . $equipment['location'];
    }
    if (!empty($equipment['notes'])) {
        $longParts[] = 'Description: ' . opd_sanitize_plain_text((string) $equipment['notes'], 4000);
    }
    if (!empty($equipment['serial'])) {
        $longParts[] = 'Serial: ' . $equipment['serial'];
    }
    $longDescription = implode("\n", $longParts);

    // Create product
    $productId = opd_generate_id('prod');
    $price = $equipment['price'] !== null ? (float) $equipment['price'] : null;
    $quantity = (int) ($equipment['quantity'] ?? 1);

    $stmt = $pdo->prepare(
        'INSERT INTO products (id, name, sku, price, status, category, shortDescription,
         longDescription, inventory, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $sku = 'EQUIP-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    $stmt->execute([
        $productId,
        $equipment['name'],
        $sku,
        $price,
        'active',
        'Used Equipment',
        $shortDescription,
        $longDescription,
        $quantity,
        $now,
        $now,
    ]);

    // Copy equipment images to product images
    site_ensure_product_images_table($pdo);
    $images = site_equipment_images($equipmentId);
    foreach ($images as $img) {
        $imgId = opd_generate_id('pimg');
        $pdo->prepare(
            'INSERT INTO product_images (id, productId, url, isPrimary, sortOrder, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $imgId,
            $productId,
            $img['url'],
            $img['isPrimary'],
            $img['sortOrder'],
            $now,
            $now,
        ]);
    }

    // Set primary image on product
    if ($images) {
        $primaryImg = null;
        foreach ($images as $img) {
            if (!empty($img['isPrimary'])) {
                $primaryImg = $img;
                break;
            }
        }
        if (!$primaryImg) {
            $primaryImg = $images[0];
        }
        $pdo->prepare('UPDATE products SET imageUrl = ? WHERE id = ?')
            ->execute([$primaryImg['url'], $productId]);
    }

    // Update equipment status and link to product
    $pdo->prepare('UPDATE equipment SET status = ?, productId = ?, updatedAt = ? WHERE id = ?')
        ->execute(['Active', $productId, $now, $equipmentId]);

    return ['productId' => $productId, 'equipment' => site_equipment_get($equipmentId)];
}

function site_equipment_decline(string $equipmentId): void
{
    $pdo = opd_db();
    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare('UPDATE equipment SET status = ?, updatedAt = ? WHERE id = ?')
        ->execute(['Declined', $now, $equipmentId]);
}

function site_equipment_sold_quantity(string $productId): int
{
    try {
        $pdo = opd_db();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(oi.quantity), 0) AS sold
             FROM order_items oi
             JOIN orders o ON o.id = oi.orderId
             WHERE oi.productId = ? AND o.status != 'cancelled'"
        );
        $stmt->execute([$productId]);
        $row = $stmt->fetch();
        return (int) ($row['sold'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function site_get_my_equipment_text(): string
{
    $pdo = opd_db();
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'my_equipment_text' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    return (string) ($row['value'] ?? '');
}

function site_get_client_record(string $userId, string $clientId): ?array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND userId = ? LIMIT 1');
    $stmt->execute([$clientId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function site_client_is_billable(array $client): bool
{
    $status = strtolower(trim((string) ($client['status'] ?? '')));
    if ($status === 'declined') {
        return false;
    }
    if ($status !== 'requested') {
        return true;
    }

    $linkedUserId = trim((string) ($client['linkedUserId'] ?? ''));
    $ownerId = trim((string) ($client['userId'] ?? ''));
    if ($linkedUserId === '' || $ownerId === '') {
        return false;
    }
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        "SELECT id FROM vendors WHERE userId = ? AND linkedUserId = ? AND status = 'active' LIMIT 1"
    );
    $stmt->execute([$linkedUserId, $ownerId]);
    return (bool) $stmt->fetch();
}

function site_find_user_by_email(string $email): ?array
{
    $email = trim($email);
    if ($email === '') {
        return null;
    }
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$email]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function site_client_exists_for_user_email(string $userId, string $email): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT id FROM clients WHERE userId = ? AND LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$userId, $email]);
    return (bool) $stmt->fetch();
}

function site_client_exists_for_user_linked(string $userId, string $linkedUserId, string $email = ''): bool
{
    if ($linkedUserId === '') {
        return false;
    }
    $pdo = opd_db();
    if ($email !== '') {
        $stmt = $pdo->prepare(
            'SELECT id FROM clients WHERE userId = ? AND (linkedUserId = ? OR LOWER(email) = LOWER(?)) LIMIT 1'
        );
        $stmt->execute([$userId, $linkedUserId, $email]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM clients WHERE userId = ? AND linkedUserId = ? LIMIT 1');
        $stmt->execute([$userId, $linkedUserId]);
    }
    return (bool) $stmt->fetch();
}

function site_vendor_exists_for_user_email(string $userId, string $email): bool
{
    $email = trim($email);
    if ($email === '') {
        return false;
    }
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT id FROM vendors WHERE userId = ? AND LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$userId, $email]);
    return (bool) $stmt->fetch();
}

function site_vendor_exists_for_user_linked(string $userId, string $linkedUserId, string $email = ''): bool
{
    if ($linkedUserId === '') {
        return false;
    }
    $pdo = opd_db();
    if ($email !== '') {
        $stmt = $pdo->prepare(
            'SELECT id FROM vendors WHERE userId = ? AND (linkedUserId = ? OR LOWER(email) = LOWER(?)) LIMIT 1'
        );
        $stmt->execute([$userId, $linkedUserId, $email]);
    } else {
        $stmt = $pdo->prepare('SELECT id FROM vendors WHERE userId = ? AND linkedUserId = ? LIMIT 1');
        $stmt->execute([$userId, $linkedUserId]);
    }
    return (bool) $stmt->fetch();
}

/**
 * After a new user registers, link any pending vendor/client invitations
 * that were created before the user had an account.
 */
function site_link_pending_invitations(string $newUserId, string $email): array
{
    $email = trim($email);
    if ($email === '' || $newUserId === '') {
        return ['linkedClients' => 0, 'linkedVendors' => 0];
    }

    $pdo = opd_db();
    $now = gmdate('Y-m-d H:i:s');
    $linkedClients = 0;
    $linkedVendors = 0;

    // Find vendors records where someone invited this email as a vendor
    // (e.g. Client A used dashboard-vendors.php to add B's email, but B wasn't registered)
    $stmt = $pdo->prepare(
        "SELECT id, userId FROM vendors WHERE LOWER(email) = LOWER(?) AND (linkedUserId IS NULL OR linkedUserId = '')"
    );
    $stmt->execute([$email]);
    $pendingVendorRecords = $stmt->fetchAll();

    foreach ($pendingVendorRecords as $record) {
        $inviterId = (string) ($record['userId'] ?? '');
        if ($inviterId === '' || $inviterId === $newUserId) {
            continue;
        }

        // Update vendor record: link to the new user and set status to 'requested'
        $update = $pdo->prepare('UPDATE vendors SET linkedUserId = ?, status = ?, updatedAt = ? WHERE id = ?');
        $update->execute([$newUserId, 'requested', $now, $record['id']]);

        // Create a clients record under the new user so they see the inviter
        if (!site_client_exists_for_user_linked($newUserId, $inviterId, '')) {
            $inviterStmt = $pdo->prepare('SELECT name, email, companyName, cellPhone FROM users WHERE id = ? LIMIT 1');
            $inviterStmt->execute([$inviterId]);
            $inviter = $inviterStmt->fetch();

            $inviterName = '';
            $inviterEmail = '';
            if ($inviter) {
                $companyName = trim((string) ($inviter['companyName'] ?? ''));
                $userName = trim((string) ($inviter['name'] ?? ''));
                $inviterName = $companyName !== '' ? $companyName : $userName;
                $inviterEmail = trim((string) ($inviter['email'] ?? ''));
            }

            site_simple_create('clients', $newUserId, [
                'name' => $inviterName,
                'email' => $inviterEmail,
                'linkedUserId' => $inviterId,
                'phone' => '',
                'status' => 'pending',
                'notes' => ''
            ]);
            $linkedClients++;
        }
    }

    // Find clients records where someone invited this email as a client
    $stmt = $pdo->prepare(
        "SELECT id, userId FROM clients WHERE LOWER(email) = LOWER(?) AND (linkedUserId IS NULL OR linkedUserId = '')"
    );
    $stmt->execute([$email]);
    $pendingClientRecords = $stmt->fetchAll();

    foreach ($pendingClientRecords as $record) {
        $inviterId = (string) ($record['userId'] ?? '');
        if ($inviterId === '' || $inviterId === $newUserId) {
            continue;
        }

        // Update client record: link to the new user and set status to 'requested'
        $update = $pdo->prepare('UPDATE clients SET linkedUserId = ?, status = ?, updatedAt = ? WHERE id = ?');
        $update->execute([$newUserId, 'requested', $now, $record['id']]);

        // Create a vendors record under the new user
        if (!site_vendor_exists_for_user_linked($newUserId, $inviterId, '')) {
            $inviterStmt = $pdo->prepare('SELECT name, email, companyName, cellPhone FROM users WHERE id = ? LIMIT 1');
            $inviterStmt->execute([$inviterId]);
            $inviter = $inviterStmt->fetch();

            $vendorName = '';
            $vendorContact = '';
            $vendorEmail = '';
            $vendorPhone = '';
            if ($inviter) {
                $companyName = trim((string) ($inviter['companyName'] ?? ''));
                $userName = trim((string) ($inviter['name'] ?? ''));
                $vendorName = $companyName !== '' ? $companyName : $userName;
                $vendorContact = $userName;
                $vendorEmail = trim((string) ($inviter['email'] ?? ''));
                $vendorPhone = trim((string) ($inviter['cellPhone'] ?? ''));
            }

            site_simple_create('vendors', $newUserId, [
                'name' => $vendorName,
                'contact' => $vendorContact,
                'email' => $vendorEmail,
                'linkedUserId' => $inviterId,
                'phone' => $vendorPhone,
                'status' => 'pending',
                'purchaseLimitOrder' => null,
                'purchaseLimitDay' => null,
                'purchaseLimitMonth' => null,
                'limitNone' => 0,
                'autoApprove' => 0,
                'paymentMethodId' => null,
                'smsConsent' => 0
            ]);
            $linkedVendors++;
        }
    }

    return ['linkedClients' => $linkedClients, 'linkedVendors' => $linkedVendors];
}

function site_get_accounting_structure(string $userId): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM accounting_codes WHERE userId = ? ORDER BY COALESCE(position,0), createdAt');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    // build tree per category
    $byId = [];
    foreach ($rows as $r) {
        $node = [
            'id' => $r['id'],
            'label' => $r['code'] ?? '',
            'category' => $r['category'] ?? '',
            'parentId' => $r['parentId'] ?? null,
            'children' => []
        ];
        $desc = $r['description'] ?? '';
        if (($r['category'] ?? '') === 'location' && is_string($desc) && trim($desc) !== '') {
            $decoded = json_decode($desc, true);
            if (is_array($decoded)) {
                if (!empty($decoded['zip'])) {
                    $node['zip'] = (string) $decoded['zip'];
                }
                if (!empty($decoded['coordinate'])) {
                    $node['coordinate'] = (string) $decoded['coordinate'];
                }
            }
        }
        $byId[$r['id']] = $node;
    }
    $roots = ['location' => [], 'code1' => [], 'code2' => []];
    foreach ($byId as $id => $node) {
        $parent = $node['parentId'];
        if ($parent && isset($byId[$parent])) {
            $byId[$parent]['children'][] = &$byId[$id];
        } else {
            $cat = $node['category'] ?: 'location';
            if (!isset($roots[$cat])) $roots[$cat] = [];
            $roots[$cat][] = &$byId[$id];
        }
    }

    // Load requireSub settings
    $requireSub = [];
    foreach (['location', 'code1', 'code2'] as $cat) {
        $key = 'accounting_require_sub_' . $cat . '_' . $userId;
        $value = site_get_setting_value($key);
        $requireSub[$cat] = $value === '1';
    }
    $roots['requireSub'] = $requireSub;

    return $roots;
}

function site_get_accounting_structure_for_client(string $userId, ?string $clientId): array
{
    if (!$clientId) {
        return site_get_accounting_structure($userId);
    }
    $client = site_get_client_record($userId, $clientId);
    $pdo = opd_db();
    if (!$client) {
        // Support cases where the client id is actually the linked user id.
        $clientLookup = $pdo->prepare('SELECT * FROM clients WHERE userId = ? AND linkedUserId = ? LIMIT 1');
        $clientLookup->execute([$userId, $clientId]);
        $client = $clientLookup->fetch() ?: null;
    }
    if (!$client) {
        // Support cases where the client id points at a vendor record or client user id.
        $vendorLookup = $pdo->prepare('SELECT userId FROM vendors WHERE id = ? AND linkedUserId = ? LIMIT 1');
        $vendorLookup->execute([$clientId, $userId]);
        $vendorRow = $vendorLookup->fetch();
        if ($vendorRow && !empty($vendorRow['userId'])) {
            return site_get_accounting_structure((string) $vendorRow['userId']);
        }
        $vendorCheck = $pdo->prepare('SELECT id FROM vendors WHERE userId = ? AND linkedUserId = ? LIMIT 1');
        $vendorCheck->execute([$clientId, $userId]);
        if ($vendorCheck->fetch()) {
            return site_get_accounting_structure($clientId);
        }
        return ['location' => [], 'code1' => [], 'code2' => []];
    }
    $linkedUserId = trim((string) ($client['linkedUserId'] ?? ''));
    if ($linkedUserId === '') {
        $email = trim((string) ($client['email'] ?? ''));
        if ($email !== '') {
            $linkedUser = site_find_user_by_email($email);
            if ($linkedUser) {
                $linkedUserId = (string) ($linkedUser['id'] ?? '');
            }
        }
    }
    if ($linkedUserId === '') {
        $check = $pdo->prepare('SELECT id FROM accounting_codes WHERE userId = ? LIMIT 1');
        $check->execute([$clientId]);
        if ($check->fetch()) {
            $linkedUserId = $clientId;
        }
    }
    if ($linkedUserId === '') {
        return ['location' => [], 'code1' => [], 'code2' => []];
    }
    return site_get_accounting_structure($linkedUserId);
}

function site_find_vendor_record_for_client(string $clientUserId, string $vendorUserId, string $vendorEmail): ?array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        'SELECT * FROM vendors WHERE userId = ? AND (linkedUserId = ? OR LOWER(email) = LOWER(?)) LIMIT 1'
    );
    $stmt->execute([$clientUserId, $vendorUserId, $vendorEmail]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function site_check_vendor_limits(array $vendorRecord, float $orderTotal, string $clientUserId, string $vendorUserId): array
{
    $limitNone = !empty($vendorRecord['limitNone']);
    if ($limitNone) {
        return ['error' => null, 'monthlyLimitExceeded' => false];
    }

    $limitOrder = $vendorRecord['purchaseLimitOrder'] ?? null;
    if ($limitOrder !== null && is_numeric($limitOrder) && $orderTotal > (float) $limitOrder) {
        return ['error' => 'Order exceeds the per-order limit set by this client.', 'monthlyLimitExceeded' => false];
    }

    $pdo = opd_db();
    $dayStart = gmdate('Y-m-d 00:00:00');
    $monthStart = gmdate('Y-m-01 00:00:00');

    $limitDay = $vendorRecord['purchaseLimitDay'] ?? null;
    if ($limitDay !== null && is_numeric($limitDay)) {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(total), 0) AS total
             FROM orders
             WHERE userId = ? AND clientUserId = ? AND createdAt >= ?'
        );
        $stmt->execute([$vendorUserId, $clientUserId, $dayStart]);
        $row = $stmt->fetch();
        $used = (float) ($row['total'] ?? 0);
        if ($used + $orderTotal > (float) $limitDay) {
            return ['error' => 'Order exceeds the daily limit set by this client.', 'monthlyLimitExceeded' => false];
        }
    }

    $limitMonth = $vendorRecord['purchaseLimitMonth'] ?? null;
    if ($limitMonth !== null && is_numeric($limitMonth)) {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(total), 0) AS total
             FROM orders
             WHERE userId = ? AND clientUserId = ? AND createdAt >= ?'
        );
        $stmt->execute([$vendorUserId, $clientUserId, $monthStart]);
        $row = $stmt->fetch();
        $used = (float) ($row['total'] ?? 0);
        if ($used + $orderTotal > (float) $limitMonth) {
            return ['error' => null, 'monthlyLimitExceeded' => true];
        }
    }

    return ['error' => null, 'monthlyLimitExceeded' => false];
}

function site_save_accounting_structure(string $userId, array $structure): void
{
    $pdo = opd_db();
    $now = gmdate('Y-m-d H:i:s');
    $pdo->beginTransaction();
    try {
        // remove existing for this user
        $del = $pdo->prepare('DELETE FROM accounting_codes WHERE userId = ?');
        $del->execute([$userId]);

        $insert = $pdo->prepare('INSERT INTO accounting_codes (id, userId, code, description, status, parentId, category, position, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

        $posCounters = ['location' => 0, 'code1' => 0, 'code2' => 0];

        $insertNode = function($node, $category, $parentId = null, $depth = 0) use (&$insert, &$posCounters, $userId, $now, &$insertNode, $pdo) {
            $id = opd_generate_id('ac');
            $pos = $posCounters[$category]++;
            $label = $node['label'] ?? '';
            $description = null;
            if ($category === 'location') {
                $meta = [];
                $zip = trim((string) ($node['zip'] ?? ''));
                $coordinate = trim((string) ($node['coordinate'] ?? ''));
                if ($zip !== '') {
                    $meta['zip'] = $zip;
                }
                if ($coordinate !== '') {
                    $meta['coordinate'] = $coordinate;
                }
                if ($meta) {
                    $description = json_encode($meta);
                }
            }
            $insert->execute([$id, $userId, $label, $description, 'active', $parentId, $category, $pos, $now, $now]);
            if (!empty($node['children']) && is_array($node['children'])) {
                foreach ($node['children'] as $child) {
                    $insertNode($child, $category, $id, $depth + 1);
                }
            }
        };

        foreach (['location','code1','code2'] as $cat) {
            $list = $structure[$cat] ?? [];
            foreach ($list as $item) {
                $insertNode($item, $cat, null, 0);
            }
        }

        $pdo->commit();

        // Save requireSub settings outside transaction
        $requireSub = $structure['requireSub'] ?? [];
        if (is_array($requireSub)) {
            foreach (['location', 'code1', 'code2'] as $cat) {
                $key = 'accounting_require_sub_' . $cat . '_' . $userId;
                $value = !empty($requireSub[$cat]) ? '1' : '0';
                site_set_setting_value($key, $value);
            }
        }
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function site_get_setting_value(string $key): ?string
{
    $key = trim($key);
    if ($key === '') {
        return null;
    }
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT value FROM settings WHERE `key` = ? ORDER BY updatedAt DESC LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $value = $row['value'] ?? null;
    return $value === null ? null : (string) $value;
}

function site_get_setting_float(string $key, float $default = 0.0): float
{
    $raw = site_get_setting_value($key);
    if ($raw === null || !is_numeric($raw)) {
        return $default;
    }
    return (float) $raw;
}

function site_get_delivery_zone(string $zip): ?int
{
    $zip = trim($zip);
    if ($zip === '') {
        return null;
    }
    static $zones = null;
    if ($zones === null) {
        $zones = require __DIR__ . '/delivery_zones.php';
    }
    return $zones[$zip] ?? null;
}

function site_cart_has_large_delivery(array $items): bool
{
    foreach ($items as $item) {
        if (!empty($item['largeDelivery'])) {
            return true;
        }
        if (!empty($item['productLargeDelivery'])) {
            return true;
        }
        if (!empty($item['variantLargeDelivery'])) {
            return true;
        }
    }
    return false;
}

function site_get_same_day_delivery_cost(string $zip, array $items): array
{
    $zone = site_get_delivery_zone($zip);
    if ($zone === null) {
        return ['error' => 'Sorry, we do not deliver outside Oklahoma', 'cost' => 0.0, 'zone' => null, 'class' => null];
    }
    $isLarge = site_cart_has_large_delivery($items);
    $class = $isLarge ? 'large' : 'small';
    $key = $isLarge ? 'delivery_large_zone' . $zone : 'delivery_small_zone' . $zone;
    $cost = site_get_setting_float($key, 0.0);
    return ['error' => null, 'cost' => max(0.0, $cost), 'zone' => $zone, 'class' => $class];
}

function site_get_shipping_zone_for_state(string $state): ?int
{
    $state = strtoupper(trim($state));
    if ($state === '') {
        return null;
    }
    for ($zone = 1; $zone <= 3; $zone++) {
        $raw = site_get_setting_value('shipping_zone' . $zone . '_states');
        if ($raw === null) {
            continue;
        }
        $states = array_map(function ($s) {
            return strtoupper(trim($s));
        }, explode(',', $raw));
        if (in_array($state, $states, true)) {
            return $zone;
        }
    }
    return null;
}

function site_cart_total_weight(array $items): float
{
    $totalWeight = 0.0;
    foreach ($items as $item) {
        $weight = 0.0;
        if (!empty($item['variantWeight']) && is_numeric($item['variantWeight'])) {
            $weight = (float) $item['variantWeight'];
        } elseif (!empty($item['wgt']) && is_numeric($item['wgt'])) {
            $weight = (float) $item['wgt'];
        } elseif (!empty($item['productWeight']) && is_numeric($item['productWeight'])) {
            $weight = (float) $item['productWeight'];
        }
        $qty = (int) ($item['quantity'] ?? 1);
        $totalWeight += $weight * $qty;
    }
    return $totalWeight;
}

function site_calculate_standard_shipping(string $state, array $items): array
{
    $zone = site_get_shipping_zone_for_state($state);
    if ($zone === null) {
        return ['error' => 'Sorry we do not ship outside the continental United States', 'cost' => 0.0, 'zone' => null];
    }
    $flat = site_get_setting_float('shipping_zone' . $zone . '_flat', 0.0);
    $perLb = site_get_setting_float('shipping_zone' . $zone . '_per_lb', 0.0);
    $totalWeight = site_cart_total_weight($items);
    $cost = $flat + ($totalWeight * $perLb);
    return ['error' => null, 'cost' => max(0.0, $cost), 'zone' => $zone];
}

function site_set_setting_value(string $key, ?string $value): void
{
    $key = trim($key);
    if ($key === '') {
        return;
    }
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT id FROM settings WHERE `key` = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $now = gmdate('Y-m-d H:i:s');
    if ($row) {
        $update = $pdo->prepare('UPDATE settings SET value = ?, updatedAt = ? WHERE id = ?');
        $update->execute([$value, $now, $row['id']]);
        return;
    }
    $insert = $pdo->prepare('INSERT INTO settings (id, `key`, value, updatedAt) VALUES (?, ?, ?, ?)');
    $insert->execute(['set-' . bin2hex(random_bytes(8)), $key, $value, $now]);
}

function site_get_base_url(): string
{
    return opd_site_base_url();
}

function site_build_invite_message(string $template, string $link, array $context = []): string
{
    $template = trim($template);
    $replacements = [
        '{link}' => $link,
        '{inviter}' => (string) ($context['inviter'] ?? ''),
        '{company}' => (string) ($context['company'] ?? ''),
        '{recipient}' => (string) ($context['recipient'] ?? ''),
    ];
    $message = str_replace(array_keys($replacements), array_values($replacements), $template);
    if ($link !== '' && strpos($template, '{link}') === false) {
        if ($message !== '') {
            $message .= ' ';
        }
        $message .= $link;
    }
    $message = preg_replace('/\s+/', ' ', $message);
    return trim((string) $message);
}

function site_send_invite_sms(string $phoneDigits, string $message, string $rateKey): array
{
    if (!preg_match('/^\d{10}$/', $phoneDigits)) {
        return ['ok' => false, 'error' => 'A valid 10-digit US phone number is required.'];
    }
    if (trim($message) === '') {
        return ['ok' => false, 'error' => 'SMS message is missing.'];
    }
    $limit = opd_check_rate_limit($rateKey, 'sms_invite');
    if (!$limit['allowed']) {
        return ['ok' => false, 'error' => 'Too many invites sent. Please try again later.'];
    }
    $to = '1' . $phoneDigits;
    $response = experttexting_send_sms($to, $message);
    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error'] ?? 'SMS failed'];
    }
    opd_reset_rate_limit($rateKey, 'sms_invite');
    return ['ok' => true, 'data' => $response['data'] ?? []];
}
