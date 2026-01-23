<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/site_auth.php';
require_once __DIR__ . '/catalog.php';

function site_get_categories(): array
{
    $pdo = opd_db();
    $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category");
    $rows = $stmt->fetchAll();
    $dbCategories = array_map(fn($row) => $row['category'], $rows);
    $categories = opd_product_categories();
    foreach ($dbCategories as $category) {
        if ($category !== '' && !in_array($category, $categories, true)) {
            $categories[] = $category;
        }
    }
    return $categories;
}

function site_get_products(?string $category = null, ?string $search = null, int $limit = 24): array
{
    $pdo = opd_db();
    $conditions = [];
    $params = [];
    if ($category) {
        $conditions[] = 'category = ?';
        $params[] = $category;
    }
    if ($search) {
        $conditions[] = '(name LIKE ? OR sku LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $sql = 'SELECT * FROM products';
    if ($conditions) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }
    $sql .= ' ORDER BY updatedAt DESC LIMIT ' . (int) $limit;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function site_get_product(string $id): ?array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function site_get_product_variants(string $productId): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM product_variants WHERE productId = ? ORDER BY name');
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function site_get_related_products(string $productId, int $limit = 6): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        'SELECT p.*
         FROM product_associations pa
         JOIN products p ON p.id = pa.relatedProductId
         WHERE pa.productId = ?
         ORDER BY p.updatedAt DESC
         LIMIT ' . (int) $limit
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
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
    foreach ($cart as $key => $item) {
        $product = site_get_product($item['productId']);
        if (!$product) {
            continue;
        }
        $items[] = [
            'key' => $key,
            'productId' => $item['productId'],
            'variantId' => $item['variantId'],
            'name' => $product['name'],
            'price' => (float) $product['price'],
            'quantity' => (int) $item['quantity'],
            'imageUrl' => $product['imageUrl'] ?? null,
        ];
    }
    return $items;
}

function site_cart_items_for_user(string $userId): array
{
    $pdo = opd_db();
    $cartId = site_get_cart_id($userId);
    if (!$cartId) {
        return [];
    }
    $stmt = $pdo->prepare(
        'SELECT ci.id as `key`, ci.productId, ci.variantId, ci.quantity, p.name, p.price, p.imageUrl
         FROM cart_items ci
         JOIN products p ON p.id = ci.productId
         WHERE ci.cartId = ?'
    );
    $stmt->execute([$cartId]);
    return $stmt->fetchAll();
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
    $cartId = 'cart-' . random_int(1000, 99999);
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare('INSERT INTO carts (id, userId, status, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?)');
    $insert->execute([$cartId, $userId, 'open', $now, $now]);
    return $cartId;
}

function site_add_to_cart(string $productId, int $quantity, ?string $variantId = null): void
{
    $quantity = max(1, $quantity);
    $user = site_current_user();
    if ($user) {
        $pdo = opd_db();
        $cartId = site_get_cart_id($user['id']);
        $stmt = $pdo->prepare('SELECT id, quantity FROM cart_items WHERE cartId = ? AND productId = ? AND variantId <=> ?');
        $stmt->execute([$cartId, $productId, $variantId]);
        $row = $stmt->fetch();
        if ($row) {
            $update = $pdo->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?');
            $update->execute([(int) $row['quantity'] + $quantity, $row['id']]);
            return;
        }
        $itemId = 'ci-' . random_int(1000, 99999);
        $insert = $pdo->prepare(
            'INSERT INTO cart_items (id, cartId, productId, variantId, quantity, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $now = gmdate('Y-m-d H:i:s');
        $insert->execute([$itemId, $cartId, $productId, $variantId, $quantity, $now, $now]);
        return;
    }

    site_start_session();
    $cart = $_SESSION['site_cart'] ?? [];
    $key = $productId . ':' . ($variantId ?? '');
    $existing = $cart[$key] ?? null;
    if ($existing) {
        $existing['quantity'] += $quantity;
        $cart[$key] = $existing;
    } else {
        $cart[$key] = ['productId' => $productId, 'variantId' => $variantId, 'quantity' => $quantity];
    }
    $_SESSION['site_cart'] = $cart;
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
        'cartacc-' . random_int(1000, 99999),
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
    $insert = $pdo->prepare(
        'INSERT INTO order_accounting (id, orderId, clientId, groupsJson, assignmentsJson, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        'ordacc-' . random_int(1000, 99999),
        $orderId,
        $clientId ?: null,
        $groupsJson,
        $assignmentsJson,
        $now,
        $now,
    ]);
}

function site_place_order(array $data): array
{
    $user = site_current_user();
    if (!$user) {
        return ['error' => 'Login required'];
    }
    $items = site_cart_items_for_user($user['id']);
    if (!$items) {
        return ['error' => 'Cart is empty'];
    }
    $total = site_cart_total($items);
    $pdo = opd_db();
    $orderId = 'ord-' . random_int(1000, 99999);
    $orderNumber = 'OPD-' . random_int(1000, 99999);
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO orders (id, number, status, customerName, customerEmail, customerPhone, address1, address2, city, state, postal, country, notes, userId, total, currency, paymentStatus, fulfillmentStatus, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $orderId,
        $orderNumber,
        'new',
        $data['name'] ?? $user['name'],
        $data['email'] ?? $user['email'],
        $data['phone'] ?? null,
        $data['address1'] ?? null,
        $data['address2'] ?? null,
        $data['city'] ?? null,
        $data['state'] ?? null,
        $data['postal'] ?? null,
        $data['country'] ?? 'USA',
        $data['notes'] ?? null,
        $user['id'],
        $total,
        'USD',
        'unpaid',
        'unfulfilled',
        $now,
        $now
    ]);

    $itemInsert = $pdo->prepare(
        'INSERT INTO order_items (id, orderId, productId, variantId, name, price, quantity, total, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($items as $item) {
        $itemTotal = (float) $item['price'] * (int) $item['quantity'];
        $itemInsert->execute([
            'oi-' . random_int(1000, 99999),
            $orderId,
            $item['productId'],
            $item['variantId'],
            $item['name'],
            $item['price'],
            $item['quantity'],
            $itemTotal,
            $now,
            $now
        ]);
    }

    $accounting = $data['accounting'] ?? null;
    if (is_array($accounting)) {
        $groups = $accounting['groups'] ?? null;
        $assignments = $accounting['assignments'] ?? null;
        if (is_array($groups) && is_array($assignments)) {
            $clientId = $accounting['clientId'] ?? null;
            site_save_order_accounting($orderId, is_string($clientId) ? $clientId : null, $groups, $assignments);
        }
    }

    $cartId = site_get_cart_id($user['id']);
    $pdo->prepare("UPDATE carts SET status = 'converted', updatedAt = ? WHERE id = ?")->execute([$now, $cartId]);
    $pdo->prepare('DELETE FROM cart_items WHERE cartId = ?')->execute([$cartId]);

    return ['orderId' => $orderId, 'orderNumber' => $orderNumber];
}

function site_get_orders_for_user(string $userId): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM orders WHERE userId = ? ORDER BY createdAt DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function site_get_favorites(string $userId): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        'SELECT f.id, f.productId, p.name, p.price, p.category
         FROM favorites f
         JOIN products p ON p.id = f.productId
         WHERE f.userId = ?
         ORDER BY f.createdAt DESC'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function site_add_favorite(string $userId, string $productId): void
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT id FROM favorites WHERE userId = ? AND productId = ?');
    $stmt->execute([$userId, $productId]);
    if ($stmt->fetch()) {
        return;
    }
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare('INSERT INTO favorites (id, userId, productId, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?)');
    $insert->execute(['fav-' . random_int(1000, 99999), $userId, $productId, $now, $now]);
}

function site_remove_favorite(string $favoriteId): void
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('DELETE FROM favorites WHERE id = ?');
    $stmt->execute([$favoriteId]);
}

function site_get_payment_methods(string $userId): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM payment_methods WHERE userId = ? ORDER BY updatedAt DESC');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
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
    $values = ['row-' . random_int(1000, 99999), $userId];
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

function site_get_accounting_structure(string $userId): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM accounting_codes WHERE userId = ? ORDER BY COALESCE(position,0), createdAt');
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();
    // build tree per category
    $byId = [];
    foreach ($rows as $r) {
        $byId[$r['id']] = [
            'id' => $r['id'],
            'label' => $r['code'] ?? '',
            'category' => $r['category'] ?? '',
            'parentId' => $r['parentId'] ?? null,
            'children' => []
        ];
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
    return $roots;
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
            $id = 'ac-' . random_int(1000, 99999);
            $pos = $posCounters[$category]++;
            $label = $node['label'] ?? '';
            $insert->execute([$id, $userId, $label, null, 'active', $parentId, $category, $pos, $now, $now]);
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
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
