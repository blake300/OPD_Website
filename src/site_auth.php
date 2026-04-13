<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/security_init.php';
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

// ────────────────────────────────────────────────────────────────────────────
// Remember-Me (DB-backed tokens)
// ────────────────────────────────────────────────────────────────────────────

function site_ensure_remember_table(): void
{
    $pdo = opd_db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS remember_me_tokens (
            id VARCHAR(64) PRIMARY KEY,
            userId VARCHAR(64) NOT NULL,
            tokenHash VARCHAR(128) NOT NULL,
            expiresAt DATETIME NOT NULL,
            createdAt DATETIME NOT NULL,
            INDEX idx_remember_user (userId),
            INDEX idx_remember_expires (expiresAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

/**
 * Issue a persistent remember-me cookie backed by a DB token.
 * Call after successful login when "Keep me signed in" is checked.
 */
function site_remember_me(): void
{
    $user = site_current_user();
    if (!$user) {
        return;
    }

    site_ensure_remember_table();
    $pdo = opd_db();

    $lifetime = 60 * 60 * 24 * 30; // 30 days
    $selector = bin2hex(random_bytes(12));   // public lookup key
    $validator = bin2hex(random_bytes(32));  // secret, hashed in DB
    $tokenHash = hash('sha256', $validator);
    $expiresAt = gmdate('Y-m-d H:i:s', time() + $lifetime);
    $now = gmdate('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO remember_me_tokens (id, userId, tokenHash, expiresAt, createdAt)
         VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$selector, $user['id'], $tokenHash, $expiresAt, $now]);

    // Cookie value: selector:validator
    $cookieValue = $selector . ':' . $validator;
    $isSecure = site_detect_https();
    if (PHP_VERSION_ID >= 70300) {
        setcookie('opd_remember', $cookieValue, [
            'expires' => time() + $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    } else {
        setcookie('opd_remember', $cookieValue, time() + $lifetime, '/', '', $isSecure, true);
    }
}

/**
 * Check for a valid remember-me cookie and restore the session.
 * Called automatically during site_current_user() when no session exists.
 */
function site_check_remember_token(): ?array
{
    $cookie = $_COOKIE['opd_remember'] ?? '';
    if ($cookie === '' || strpos($cookie, ':') === false) {
        return null;
    }

    [$selector, $validator] = explode(':', $cookie, 2);
    if (strlen($selector) !== 24 || strlen($validator) !== 64) {
        return null;
    }

    site_ensure_remember_table();
    $pdo = opd_db();

    $stmt = $pdo->prepare(
        'SELECT * FROM remember_me_tokens WHERE id = ? AND expiresAt > UTC_TIMESTAMP() LIMIT 1'
    );
    $stmt->execute([$selector]);
    $token = $stmt->fetch();

    if (!$token) {
        site_clear_remember_cookie();
        return null;
    }

    // Timing-safe comparison of the validator hash
    if (!hash_equals($token['tokenHash'], hash('sha256', $validator))) {
        // Invalid validator — possible token theft, revoke all tokens for this user
        $pdo->prepare('DELETE FROM remember_me_tokens WHERE userId = ?')->execute([$token['userId']]);
        site_clear_remember_cookie();
        return null;
    }

    // Token is valid — load the user
    $userStmt = $pdo->prepare('SELECT id, name, email, role, status FROM users WHERE id = ? AND status = ? LIMIT 1');
    $userStmt->execute([$token['userId'], 'active']);
    $user = $userStmt->fetch();

    if (!$user) {
        $pdo->prepare('DELETE FROM remember_me_tokens WHERE id = ?')->execute([$selector]);
        site_clear_remember_cookie();
        return null;
    }

    // Rotate token: delete old, issue new (prevents replay attacks)
    $pdo->prepare('DELETE FROM remember_me_tokens WHERE id = ?')->execute([$selector]);

    // Restore session
    session_regenerate_id(true);
    $_SESSION['site_user'] = [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
    $_SESSION['site_csrf'] = bin2hex(random_bytes(32));

    // Issue fresh remember-me token
    site_remember_me();

    return $_SESSION['site_user'];
}

/**
 * Clear the remember-me cookie.
 */
function site_clear_remember_cookie(): void
{
    $isSecure = site_detect_https();
    if (PHP_VERSION_ID >= 70300) {
        setcookie('opd_remember', '', [
            'expires' => time() - 42000,
            'path' => '/',
            'domain' => '',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    } else {
        setcookie('opd_remember', '', time() - 42000, '/', '', $isSecure, true);
    }
}

/**
 * Revoke all remember-me tokens for a user (call on password change, logout, etc.)
 */
function site_revoke_remember_tokens(string $userId): void
{
    site_ensure_remember_table();
    $pdo = opd_db();
    $pdo->prepare('DELETE FROM remember_me_tokens WHERE userId = ?')->execute([$userId]);
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
    if (is_array($user)) {
        return $user;
    }
    // No session — try remember-me token
    return site_check_remember_token();
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
    // Accept email or 10-digit US cell phone as the identifier.
    $identifier = trim($email);
    $phone = opd_normalize_us_phone($identifier);
    $isPhone = $phone !== null && !filter_var($identifier, FILTER_VALIDATE_EMAIL);

    // Rate limit key: use normalized phone or lowercased email so variants collapse to one bucket.
    $rateLimitKey = $isPhone ? $phone : strtolower($identifier);
    $rateLimitIdentifiers = opd_login_rate_limit_identifiers($rateLimitKey);
    $rateLimitCheck = opd_check_rate_limits($rateLimitIdentifiers, 'customer_login');
    if (!$rateLimitCheck['allowed']) {
        return null;
    }

    $pdo = opd_db();
    if ($isPhone) {
        // Normalize stored cellPhone values (which may contain spaces, dashes, parens, dots, or a leading +1)
        // and compare against the 10-digit input or 11-digit (1+10) form.
        $stmt = $pdo->prepare(
            "SELECT * FROM users
             WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cellPhone,''),' ',''),'-',''),'(',''),')',''),'.',''),'+','') IN (?, ?)
             LIMIT 1"
        );
        $stmt->execute([$phone, '1' . $phone]);
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$identifier]);
    }
    $user = $stmt->fetch();

    // SECURITY: Always verify password even if user doesn't exist (prevents timing attacks)
    // Use a precomputed dummy hash if user not found to maintain consistent timing without extra CPU work.
    $hash = ($user && !empty($user['passwordHash'])) ? $user['passwordHash'] : opd_dummy_password_hash();
    $validPassword = password_verify($password, $hash);

    // Check all conditions
    $validUser = $user && $validPassword && (!empty($user['status']) && $user['status'] === 'active');

    if (!$validUser) {
        // Record failed attempt for rate limiting
        opd_record_failed_attempts_for_identifiers($rateLimitIdentifiers, 'customer_login');
        return null;
    }

    // Reset rate limit on successful login
    opd_reset_rate_limits_for_identifiers($rateLimitIdentifiers, 'customer_login');

    // If the user signed in via their cell phone and we don't yet have a cellPhone
    // on record, persist the normalized 10-digit number so it appears on their account page.
    if ($isPhone && empty(trim((string) ($user['cellPhone'] ?? '')))) {
        try {
            $pdo->prepare('UPDATE users SET cellPhone = ?, updatedAt = ? WHERE id = ?')
                ->execute([$phone, gmdate('Y-m-d H:i:s'), $user['id']]);
            $user['cellPhone'] = $phone;
        } catch (\Throwable $e) {
            error_log('site_login: failed to auto-populate cellPhone: ' . $e->getMessage());
        }
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
            site_add_to_cart(
                $item['productId'],
                (int) $item['quantity'],
                $item['variantId'] ?? null,
                $item['arrivalDate'] ?? null,
                $item['associationSourceProductId'] ?? null
            );
        }
        unset($_SESSION['site_cart']);
    }

    return $_SESSION['site_user'];
}

/**
 * Bot protection: check honeypot, timing, and registration rate limit.
 * Call from form handlers before site_register().
 * Returns null if OK, or an error string if blocked.
 */
function site_check_bot(string $honeypot, string $formLoadedAt): ?string
{
    // 1. Honeypot: hidden field should be empty
    if ($honeypot !== '') {
        return 'Registration failed. Please try again.';
    }

    // 2. Timing: form submitted too fast (< 3 seconds)
    if ($formLoadedAt !== '') {
        $elapsed = time() - (int) $formLoadedAt;
        if ($elapsed < 3) {
            return 'Registration failed. Please try again.';
        }
    }

    // 3. Rate limit registration by IP: max 5 per hour
    $ip = opd_client_ip();
    $rateLimitCheck = opd_check_rate_limit($ip, 'registration');
    if (!$rateLimitCheck['allowed']) {
        return 'Too many registration attempts. Please try again later.';
    }

    return null;
}

/**
 * Record a registration attempt for IP-based rate limiting.
 */
function site_record_registration_attempt(): void
{
    $ip = opd_client_ip();
    opd_record_failed_attempt($ip, 'registration');
}

function site_register(string $name, string $email, string $password, string $phone = ''): array
{
    // Record the attempt for IP rate limiting
    site_record_registration_attempt();

    $email = trim($email);
    $phone = trim($phone);
    $normalizedPhone = $phone !== '' ? opd_normalize_us_phone($phone) : null;

    // Require at least one valid contact method (email or 10-digit US cell phone).
    if ($email === '' && $normalizedPhone === null) {
        return ['error' => 'Please enter an email address or a 10-digit cell phone number.'];
    }
    if ($email !== '' && !opd_validate_email($email)) {
        return ['error' => 'Invalid email address'];
    }
    if ($phone !== '' && $normalizedPhone === null) {
        return ['error' => 'Cell phone must be 10 digits.'];
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
    if ($email !== '') {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['error' => 'Registration failed. Please try again or contact support.'];
        }
    }
    if ($normalizedPhone !== null) {
        $phoneCheck = $pdo->prepare(
            "SELECT id FROM users
             WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cellPhone,''),' ',''),'-',''),'(',''),')',''),'.',''),'+','') IN (?, ?)
             LIMIT 1"
        );
        $phoneCheck->execute([$normalizedPhone, '1' . $normalizedPhone]);
        if ($phoneCheck->fetch()) {
            return ['error' => 'Registration failed. Please try again or contact support.'];
        }
    }

    $id = 'user-' . bin2hex(random_bytes(16));
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $now = gmdate('Y-m-d H:i:s');
    $insert = $pdo->prepare(
        'INSERT INTO users (id, name, email, cellPhone, passwordHash, role, status, lastLogin, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)'
    );
    $insert->execute([
        $id,
        $name,
        $email !== '' ? $email : null,
        $normalizedPhone,
        $hash,
        'customer',
        'active',
        $now,
    ]);

    return [
        'id' => $id,
        'name' => $name,
        'email' => $email,
        'cellPhone' => $normalizedPhone,
        'role' => 'customer',
    ];
}

function site_logout(): void
{
    site_start_session();
    $user = $_SESSION['site_user'] ?? null;
    // Revoke all remember-me tokens for this user
    if (is_array($user) && !empty($user['id'])) {
        site_revoke_remember_tokens($user['id']);
    }
    site_clear_remember_cookie();
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

    // Add `used` column to older table variants that were created without it.
    try {
        $col = $pdo->query("SHOW COLUMNS FROM password_reset_tokens LIKE 'used'")->fetch();
        if (!$col) {
            $pdo->exec('ALTER TABLE password_reset_tokens ADD COLUMN used TINYINT(1) DEFAULT 0');
        }
    } catch (\Throwable $e) {
        // Ignore — if the query fails we'll surface the original error on read.
    }
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

    // Send email — use config-based URL to prevent host header poisoning
    $resetUrl = opd_site_base_url() . '/reset-password.php?token=' . urlencode($rawToken);

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
 * Request a password reset via SMS to the user's cellPhone.
 * Always returns success-like response to prevent phone enumeration.
 */
function site_request_password_reset_sms(string $phone): array
{
    site_ensure_password_reset_table();
    require_once __DIR__ . '/validation.php';
    require_once __DIR__ . '/experttexting_service.php';

    $normalized = opd_normalize_us_phone($phone);
    if ($normalized === null) {
        return ['error' => 'Cell phone must be 10 digits.'];
    }

    // Rate limit: max 5 reset requests per phone per hour
    $rateLimitCheck = opd_check_rate_limit($normalized, 'password_reset');
    if (!$rateLimitCheck['allowed']) {
        return ['error' => 'Too many reset attempts. Please try again later.'];
    }

    $pdo = opd_db();
    // Match stored cellPhone after stripping common formatting characters.
    $stmt = $pdo->prepare(
        "SELECT id, email, name, status, cellPhone
         FROM users
         WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cellPhone,''),' ',''),'-',''),'(',''),')',''),'.',''),'+','') IN (?, ?)
         AND status = ?
         LIMIT 1"
    );
    $stmt->execute([$normalized, '1' . $normalized, 'active']);
    $user = $stmt->fetch();

    if (!$user) {
        opd_record_failed_attempt($normalized, 'password_reset');
        return ['ok' => true];
    }

    // Check for admin lock
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

    $resetUrl = opd_site_base_url() . '/reset-password.php?token=' . urlencode($rawToken);
    $siteName = opd_site_name();
    $smsBody = $siteName . ': Reset your password using this link (expires in 30 min): ' . $resetUrl
        . ' If you did not request this, ignore this message.';

    $result = experttexting_send_sms($normalized, $smsBody);
    if (empty($result['ok'])) {
        error_log('[password_reset_sms] Failed to send SMS: ' . json_encode($result));
        // Don't leak the failure — still return ok to avoid enumeration.
    }

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
