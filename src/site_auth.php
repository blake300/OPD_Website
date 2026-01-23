<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function site_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function site_csrf_token(): string
{
    site_start_session();
    if (empty($_SESSION['site_csrf'])) {
        $_SESSION['site_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['site_csrf'];
}

function site_require_csrf(): void
{
    site_start_session();
    $token = $_POST['_csrf'] ?? '';
    $sessionToken = $_SESSION['site_csrf'] ?? '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        echo 'Invalid CSRF token';
        exit;
    }
}

function site_current_user(): ?array
{
    site_start_session();
    $user = $_SESSION['site_user'] ?? null;
    return is_array($user) ? $user : null;
}

function site_require_auth(): array
{
    $user = site_current_user();
    if (!$user) {
        header('Location: /login.php');
        exit;
    }
    return $user;
}

function site_login(string $email, string $password): ?array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user || empty($user['passwordHash'])) {
        return null;
    }
    if (!password_verify($password, $user['passwordHash'])) {
        return null;
    }
    if (!empty($user['status']) && $user['status'] !== 'active') {
        return null;
    }

    site_start_session();
    session_regenerate_id(true);
    $_SESSION['site_user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
    $_SESSION['site_csrf'] = bin2hex(random_bytes(32));

    $sessionCart = $_SESSION['site_cart'] ?? [];
    if ($sessionCart) {
        require_once __DIR__ . '/store.php';
        foreach ($sessionCart as $item) {
            site_add_to_cart($item['productId'], (int) $item['quantity'], $item['variantId'] ?? null);
        }
        unset($_SESSION['site_cart']);
    }

    return $_SESSION['site_user'];
}

function site_register(string $name, string $email, string $password): array
{
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return ['error' => 'Email already registered'];
    }

    $id = 'user-' . random_int(1000, 99999);
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO users (id, name, email, passwordHash, role, status, lastLogin, updatedAt) VALUES (?, ?, ?, ?, ?, ?, NULL, ?)'
    );
    $insert->execute([$id, $name, $email, $hash, 'customer', 'active', $now]);

    return ['id' => $id, 'name' => $name, 'email' => $email, 'role' => 'customer'];
}

function site_logout(): void
{
    site_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
