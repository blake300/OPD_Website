<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

function favorites_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function favorites_require_json_csrf(): void
{
    site_start_session();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $session = $_SESSION['site_csrf'] ?? '';
    if ($token === '' || $session === '' || !hash_equals($session, $token)) {
        favorites_json_response(['error' => 'Invalid CSRF token'], 403);
    }
}

$user = site_current_user();
if (!$user) {
    favorites_json_response(['error' => 'Login required'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $categoryId = $_GET['categoryId'] ?? '';
    $productId = $_GET['productId'] ?? '';
    $variantId = $_GET['variantId'] ?? null;
    if ($categoryId !== '') {
        $items = site_get_favorite_items($user['id'], $categoryId);
        favorites_json_response(['categoryId' => $categoryId, 'items' => $items]);
    }
    $categories = site_get_favorite_categories($user['id']);
    if ($productId !== '') {
        $selected = site_get_favorite_item_category_ids($user['id'], $productId, $variantId ?: null);
        favorites_json_response([
            'categories' => $categories,
            'selectedCategoryIds' => $selected,
        ]);
    }
    favorites_json_response(['categories' => $categories]);
}

if ($method === 'POST') {
    favorites_require_json_csrf();
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload)) {
        favorites_json_response(['error' => 'Invalid JSON'], 400);
    }
    $action = $payload['action'] ?? '';
    if ($action === 'save_categories') {
        $categories = is_array($payload['categories'] ?? null) ? $payload['categories'] : [];
        $saved = site_save_favorite_categories($user['id'], $categories);
        favorites_json_response(['categories' => $saved]);
    }
    if ($action === 'save_items') {
        $updates = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        site_update_favorite_entries($user['id'], $updates);
        favorites_json_response(['ok' => true]);
    }
    if ($action === 'set_product_categories') {
        $productId = (string) ($payload['productId'] ?? '');
        $variantId = $payload['variantId'] ?? null;
        $categoryIds = is_array($payload['categoryIds'] ?? null) ? $payload['categoryIds'] : [];
        if ($productId === '') {
            favorites_json_response(['error' => 'Missing productId'], 400);
        }
        $selected = site_set_favorite_item_categories($user['id'], $productId, $variantId ?: null, $categoryIds);
        favorites_json_response(['selectedCategoryIds' => $selected]);
    }
    if ($action === 'remove_item') {
        $entryId = (string) ($payload['entryId'] ?? '');
        if ($entryId === '') {
            favorites_json_response(['error' => 'Missing entryId'], 400);
        }
        $removed = site_delete_favorite_entry($user['id'], $entryId);
        if (!$removed) {
            favorites_json_response(['error' => 'Favorite not found'], 404);
        }
        favorites_json_response(['ok' => true]);
    }
    if ($action === 'add_to_cart') {
        $entryIds = is_array($payload['entryIds'] ?? null) ? $payload['entryIds'] : [];
        $result = site_add_favorites_to_cart($user['id'], $entryIds);
        favorites_json_response($result);
    }
    favorites_json_response(['error' => 'Invalid action'], 400);
}

favorites_json_response(['error' => 'Method Not Allowed'], 405);
