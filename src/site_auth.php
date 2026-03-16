<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/db_conn.php';
require_once __DIR__ . '/rate_limit.php';
require_once __DIR__ . '/validation.php';

function site_detect_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    return false;
}

function site_set_session_cookie_params(bool $isSecure, int $lifetime = 0): void
{
    if (headers_sent()) {
        return;
    }

    if (PHP_VERSION_ID >= 70300) {
        @session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        return;
    }
    @session_set_cookie_params($lifetime, '/', '', $isSecure, true);
}

function site_start_session(int $lifetime = 0): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $isSecure = site_detect_https();
        site_set_session_cookie_params($isSecure, $lifetime);
        @session_start();
    }
}

/**
 * Extend the current session cookie for remember-me.
 * Must be called after login to re-send the cookie with a longer lifetime.
 */
function site_remember_me(): void
{
    $lifetime = 60 * 60 * 24 * 30; // 30 days
    $isSecure = site_detect_https();
    ini_set('session.gc_maxlifetime', (string) $lifetime);
    $params = [
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Strict',
    ];
    if (PHP_VERSION_ID >= 70300) {
        setcookie(session_name(), session_id(), [
            'expires' => time() + $lifetime,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'],
        ]);
    } else {
        setcookie(session_name(), session_id(), time() + $lifetime, '/', '', $isSecure, true);
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

// ────────────────────────────────────────────────────────────────────────────
// Password Reset
// ────────────────────────────────────────────────────────────────────────────

function site_ensure_password_reset_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = opd_db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id VARCHAR(64) PRIMARY KEY,
            userId VARCHAR(64) NOT NULL,
            tokenHash VARCHAR(128) NOT NULL,
            used TINYINT(1) DEFAULT 0,
            expiresAt DATETIME NOT NULL,
            createdAt DATETIME NOT NULL,
            INDEX idx_token_hash (tokenHash),
            INDEX idx_user_id (userId)
        )'
    );
}

/**
 * Request a password reset. Generates token, stores hash, sends email.
 * Always returns success-like response to prevent email enumeration.
 */
function site_request_password_reset(string $email): array
{
    site_ensure_password_reset_table();
    $email = trim($email);
    if ($email === '') {
        return ['ok' => true]; // Don't reveal anything
    }

    // Rate limit: max 5 reset requests per email per hour
    $rateLimitCheck = opd_check_rate_limit($email, 'password_reset');
    if (!$rateLimitCheck['allowed']) {
        return ['error' => 'Too many reset attempts. Please try again later.'];
    }

    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT id, email, name, status FROM users WHERE email = ? AND status = ? LIMIT 1');
    $stmt->execute([$email, 'active']);
    $user = $stmt->fetch();

    if (!$user) {
        // Record attempt but don't reveal user doesn't exist
        opd_record_failed_attempt($email, 'password_reset');
        return ['ok' => true];
    }

    // Check for admin lock (too many reset attempts)
    $checkLock = $pdo->prepare('SELECT resetAdminLocked FROM users WHERE id = ? LIMIT 1');
    $checkLock->execute([$user['id']]);
    $lockRow = $checkLock->fetch();
    if ($lockRow && !empty($lockRow['resetAdminLocked'])) {
        return ['error' => 'Password reset is locked for this account. Please contact an administrator.'];
    }

    // Invalidate any existing tokens for this user
    $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE userId = ? AND used = 0')->execute([$user['id']]);

    // Generate token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $id = 'prt-' . bin2hex(random_bytes(16));
    $now = gmdate('Y-m-d H:i:s');
    $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+30 minutes'));

    $insert = $pdo->prepare(
        'INSERT INTO password_reset_tokens (id, userId, tokenHash, used, expiresAt, createdAt)
         VALUES (?, ?, ?, 0, ?, ?)'
    );
    $insert->execute([$id, $user['id'], $tokenHash, $expiresAt, $now]);

    // Send email
    $resetUrl = (site_detect_https() ? 'https' : 'http') . '://'
        . ($_SERVER['HTTP_HOST'] ?? 'localhost')
        . '/reset-password.php?token=' . urlencode($rawToken);

    // Try to load custom email template from system settings
    $emailBody = '';
    require_once __DIR__ . '/store.php';
    $template = site_get_setting_value('emailForgotPassword');
    if ($template) {
        $emailBody = str_replace(
            ['{name}', '{resetLink}', '{resetUrl}'],
            [htmlspecialchars($user['name'] ?? 'Customer'), '<a href="' . htmlspecialchars($resetUrl) . '">Reset Your Password</a>', htmlspecialchars($resetUrl)],
            $template
        );
    } else {
        $userName = htmlspecialchars($user['name'] ?? 'Customer');
        $safeUrl = htmlspecialchars($resetUrl);
        $siteName = htmlspecialchars(opd_site_name());
        $emailBody = "<p>Hi {$userName},</p>"
            . "<p>You requested a password reset for your {$siteName} account.</p>"
            . "<p><a href=\"{$safeUrl}\" style=\"display:inline-block;padding:12px 24px;background:#c0392b;color:#fff;text-decoration:none;border-radius:4px;\">Reset Your Password</a></p>"
            . "<p>Or copy this link: {$safeUrl}</p>"
            . "<p>This link expires in 30 minutes.</p>"
            . "<p>If you didn't request this, you can safely ignore this email.</p>";
    }

    require_once __DIR__ . '/email_service.php';
    if (function_exists('opd_email_shell')) {
        $emailBody = opd_email_shell($emailBody);
    }
    opd_send_email($user['email'], 'Reset Your Password - ' . opd_site_name(), $emailBody);

    return ['ok' => true];
}

/**
 * Validate a raw reset token. Returns ['valid' => bool, 'error' => string].
 */
function site_validate_reset_token(string $rawToken): array
{
    site_ensure_password_reset_table();
    if ($rawToken === '') {
        return ['valid' => false, 'error' => 'Missing reset token.'];
    }

    $tokenHash = hash('sha256', $rawToken);
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        'SELECT * FROM password_reset_tokens WHERE tokenHash = ? AND used = 0 LIMIT 1'
    );
    $stmt->execute([$tokenHash]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['valid' => false, 'error' => 'Invalid or expired reset link.'];
    }

    $expiresAt = new DateTime($row['expiresAt'], new DateTimeZone('UTC'));
    $now = new DateTime('now', new DateTimeZone('UTC'));
    if ($now > $expiresAt) {
        return ['valid' => false, 'error' => 'This reset link has expired. Please request a new one.'];
    }

    return ['valid' => true, 'userId' => $row['userId']];
}

/**
 * Apply password reset: validate token, update password, invalidate all tokens.
 */
function site_apply_password_reset(string $rawToken, string $newPassword): array
{
    site_ensure_password_reset_table();
    $check = site_validate_reset_token($rawToken);
    if (!$check['valid']) {
        return ['error' => $check['error']];
    }

    // Validate password strength
    $passwordErrors = opd_validate_password($newPassword);
    if (!empty($passwordErrors)) {
        return ['error' => implode('. ', $passwordErrors)];
    }

    $userId = $check['userId'];
    $pdo = opd_db();

    // Update password
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $now = gmdate('Y-m-d H:i:s');
    $pdo->prepare('UPDATE users SET passwordHash = ?, updatedAt = ? WHERE id = ?')->execute([$hash, $now, $userId]);

    // Invalidate ALL tokens for this user
    $pdo->prepare('UPDATE password_reset_tokens SET used = 1 WHERE userId = ?')->execute([$userId]);

    // Reset any rate limits
    $userStmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $userRow = $userStmt->fetch();
    if ($userRow) {
        opd_reset_rate_limit($userRow['email'], 'password_reset');
        opd_reset_rate_limit($userRow['email'], 'customer_login');
    }

    return ['ok' => true];
}
