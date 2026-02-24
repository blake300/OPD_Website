<?php

declare(strict_types=1);

require_once __DIR__ . '/db_conn.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/validation.php';

function opd_set_session_cookie_params(bool $isSecure): void
{
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        return;
    }
    session_set_cookie_params(0, '/', '', $isSecure, true);
}

function opd_start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isSecure = false;
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $isSecure = true;
        } elseif (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
            $isSecure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
            $isSecure = true;
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
            $isSecure = true;
        }
        opd_set_session_cookie_params($isSecure);
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
    // Check rate limiting first
    $rateLimitCheck = opd_check_rate_limit($email, 'admin_login');
    if (!$rateLimitCheck['allowed']) {
        return null;
    }

    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // SECURITY: Always verify password even if user doesn't exist (prevents timing attacks)
    // Use dummy hash if user not found to maintain consistent timing
    $hash = ($user && !empty($user['passwordHash'])) ? $user['passwordHash'] : password_hash('dummy', PASSWORD_DEFAULT);
    $validPassword = password_verify($password, $hash);

    // Check all conditions including role (admin login should be for admin/manager only)
    $validUser = $user
                 && $validPassword
                 && (!empty($user['status']) && $user['status'] === 'active')
                 && in_array($user['role'] ?? '', ['admin', 'manager'], true);

    if (!$validUser) {
        // Record failed attempt for rate limiting
        opd_record_failed_attempt($email, 'admin_login');
        return null;
    }

    // Reset rate limit on successful login
    opd_reset_rate_limit($email, 'admin_login');

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
