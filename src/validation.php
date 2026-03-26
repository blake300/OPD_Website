<?php

declare(strict_types=1);

/**
 * Validates email format
 */
function opd_validate_email(string $email): bool
{
    if (strlen($email) > 254) {
        return false;
    }
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validates password strength
 * Requirements: min 12 chars, uppercase, lowercase, number, special char
 */
function opd_validate_password(string $password): array
{
    $errors = [];

    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character';
    }

    return $errors;
}

/**
 * Sanitizes name input (removes dangerous characters, preserves spaces and hyphens)
 */
function opd_sanitize_name(string $name): string
{
    // Remove any HTML tags
    $name = strip_tags($name);
    // Allow letters, spaces, hyphens, apostrophes (for names like O'Brien)
    $name = preg_replace('/[^a-zA-Z\s\-\'.]/', '', $name);
    // Trim whitespace
    $name = trim($name);
    // Limit length
    return substr($name, 0, 100);
}

/**
 * Validates name input
 */
function opd_validate_name(string $name): bool
{
    $sanitized = opd_sanitize_name($name);
    return strlen($sanitized) >= 2 && strlen($sanitized) <= 100;
}

/**
 * Sanitizes free-form text that should remain plain text.
 */
function opd_sanitize_plain_text(string $value, int $maxLength = 5000): string
{
    $value = strip_tags($value);
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[^\P{C}\n\t]+/u', '', $value) ?? '';
    $value = trim($value);
    if ($maxLength > 0) {
        $value = function_exists('mb_substr')
            ? mb_substr($value, 0, $maxLength, 'UTF-8')
            : substr($value, 0, $maxLength);
    }
    return $value;
}

/**
 * Normalizes a US phone number to 10 digits (returns null if invalid)
 */
function opd_normalize_us_phone(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (!is_string($digits)) {
        return null;
    }
    if (strlen($digits) === 11 && strpos($digits, '1') === 0) {
        $digits = substr($digits, 1);
    }
    if (strlen($digits) !== 10) {
        return null;
    }
    return $digits;
}

/**
 * Validates US phone numbers (10 digits)
 */
function opd_validate_us_phone(string $phone): bool
{
    return opd_normalize_us_phone($phone) !== null;
}
