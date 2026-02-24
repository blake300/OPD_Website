<?php

declare(strict_types=1);

/**
 * Security initialization - Call this at the top of every public page
 * Enforces HTTPS and sets security headers
 */

// Enforce HTTPS (except for localhost development)
function opd_enforce_https(): void
{
    $isLocalhost = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1', '::1']);

    if (!$isLocalhost) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || ($_SERVER['SERVER_PORT'] ?? 80) == 443
                   || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if (!$isHttps) {
            $redirect = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
            header('Location: ' . $redirect, true, 301);
            exit;
        }
    }
}

// Set security headers
function opd_set_security_headers(): void
{
    // Prevent clickjacking
    header('X-Frame-Options: DENY');

    // Enable browser XSS protection
    header('X-XSS-Protection: 1; mode=block');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');

    // Content Security Policy (adjust as needed for your site)
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:");

    // HTTPS Strict Transport Security (HSTS) - only set if on HTTPS
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
