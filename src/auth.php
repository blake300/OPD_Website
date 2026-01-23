<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_helpers.php';

function opd_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function opd_is_api_request(): bool
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    return str_starts_with($uri, '/api/');
}

function opd_current_user(): ?array
{
    opd_start_session();
    $user = $_SESSION['opd_user'] ?? null;
    return is_array($user) ? $user : null;
}

function opd_csrf_token(): string
{
    opd_start_session();
    if (empty($_SESSION['opd_csrf'])) {
        $_SESSION['opd_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['opd_csrf'];
}

function opd_require_csrf(): void
{
    opd_start_session();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['_csrf'] ?? '');
    $sessionToken = $_SESSION['opd_csrf'] ?? '';
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        if (opd_is_api_request()) {
            opd_json_response(['error' => 'Invalid CSRF token'], 403);
        }
        http_response_code(403);
        echo 'Invalid CSRF token';
        exit;
    }
}

function opd_require_auth(): array
{
    $user = opd_current_user();
    if (!$user) {
        if (opd_is_api_request()) {
            opd_json_response(['error' => 'Unauthorized'], 401);
        }
        header('Location: /admin-login.php');
        exit;
    }
    return $user;
}

function opd_require_role(array $allowedRoles): array
{
    $user = opd_require_auth();
    if (!in_array($user['role'] ?? '', $allowedRoles, true)) {
        if (opd_is_api_request()) {
            opd_json_response(['error' => 'Forbidden'], 403);
        }
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
    return $user;
}

function opd_login(string $email, string $password): ?array
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

    opd_start_session();
    session_regenerate_id(true);
    $_SESSION['opd_user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
    $_SESSION['opd_csrf'] = bin2hex(random_bytes(32));

    $now = gmdate('Y-m-d H:i:s');
    $update = $pdo->prepare('UPDATE users SET lastLogin = ?, updatedAt = ? WHERE id = ?');
    $update->execute([$now, $now, $user['id']]);

    return $_SESSION['opd_user'];
}

function opd_logout(): void
{
    opd_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
