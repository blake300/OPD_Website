<?php

declare(strict_types=1);

require_once __DIR__ . '/db_conn.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/validation.php';

function site_set_session_cookie_params(bool $isSecure): void
{
    // Only set params if headers haven't been sent yet
    if (headers_sent()) {
        return;
    }

    if (PHP_VERSION_ID >= 70300) {
        @session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        return;
    }
    @session_set_cookie_params(0, '/', '', $isSecure, true);
}

function site_start_session(): void
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
        site_set_session_cookie_params($isSecure);
        @session_start();
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
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
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
        // Clear any output buffers before redirect
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Location: /login.php');
            exit;
        }

        // If headers already sent, show error message
        echo '<!DOCTYPE html><html><head><title>Authentication Required</title></head><body>';
        echo '<h1>Authentication Required</h1>';
        echo '<p>Please <a href="/login.php">log in</a> to continue.</p>';
        echo '</body></html>';
        exit;
    }
    return $user;
}

function site_login(string $email, string $password): ?array
{
    // Check rate limiting first
    $rateLimitCheck = opd_check_rate_limit($email, 'customer_login');
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

    // Check all conditions
    $validUser = $user && $validPassword && (!empty($user['status']) && $user['status'] === 'active');

    if (!$validUser) {
        // Record failed attempt for rate limiting
        opd_record_failed_attempt($email, 'customer_login');
        return null;
    }

    // Reset rate limit on successful login
    opd_reset_rate_limit($email, 'customer_login');

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
    // Validate email format
    if (!opd_validate_email($email)) {
        return ['error' => 'Invalid email address'];
    }

    // Validate name
    if (!opd_validate_name($name)) {
        return ['error' => 'Name must be between 2 and 100 characters'];
    }

    // Validate password strength
    $passwordErrors = opd_validate_password($password);
    if (!empty($passwordErrors)) {
        return ['error' => implode('. ', $passwordErrors)];
    }

    // Sanitize name
    $name = opd_sanitize_name($name);

    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        // SECURITY: Don't reveal that email exists - use generic message
        return ['error' => 'Registration failed. Please try again or contact support.'];
    }

    $id = 'user-' . bin2hex(random_bytes(16));
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
