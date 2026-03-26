<?php

declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';

/**
 * Security initialization - included via site_auth.php and auth.php.
 * Enforces HTTPS, sets security headers, and generates CSP nonce.
 */

// Generate a per-request CSP nonce (available to templates via opd_csp_nonce())
if (!function_exists('opd_csp_nonce')) {
    $_SERVER['_CSP_NONCE'] = base64_encode(random_bytes(16));

    function opd_csp_nonce(): string
    {
        return $_SERVER['_CSP_NONCE'];
    }
}

// Enforce HTTPS (except for localhost development)
function opd_enforce_https(): void
{
    $isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);

    if (!$isLocalhost) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || ($_SERVER['SERVER_PORT'] ?? 80) == 443
                   || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!$isHttps) {
            $redirect = preg_replace('#^http://#i', 'https://', opd_site_base_url()) . ($_SERVER['REQUEST_URI'] ?? '/');
            header('Location: ' . $redirect, true, 301);
            exit;
        }
    }
}

// Set security headers
function opd_set_security_headers(): void
{
    $nonce = opd_csp_nonce();

    // Prevent clickjacking
    header('X-Frame-Options: DENY');

    // Enable browser XSS protection
    header('X-XSS-Protection: 1; mode=block');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Permissions policy
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

    // Content Security Policy with nonce for inline scripts
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://js.stripe.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https://*.stripe.com; font-src 'self' data:; frame-src https://js.stripe.com https://hooks.stripe.com; connect-src 'self' https://api.stripe.com");

    // HTTPS Strict Transport Security (HSTS)
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || ($_SERVER['SERVER_PORT'] ?? 80) == 443
               || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Initialize security measures
opd_enforce_https();
opd_set_security_headers();

// Set session timeout (1 hour)
ini_set('session.gc_maxlifetime', '3600');
ini_set('session.cookie_lifetime', '0'); // Expire on browser close
