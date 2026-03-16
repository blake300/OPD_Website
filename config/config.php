<?php

declare(strict_types=1);

if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return true;
        }
        return strpos($haystack, $needle) === 0;
    }
}

if (!function_exists('opd_load_env')) {
    function opd_load_env(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }
            $parts = explode('=', $trimmed, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $value = trim($value, "\"'");
            $_ENV[$key] = $value;
        }
    }
}

$root = dirname(__DIR__);
opd_load_env($root . DIRECTORY_SEPARATOR . '.env');

if (!function_exists('opd_site_name')) {
    function opd_site_name(): string
    {
        return $_ENV['SITE_NAME'] ?? 'Oil Patch Depot';
    }
}

if (!function_exists('opd_site_email')) {
    function opd_site_email(): string
    {
        return $_ENV['SITE_EMAIL'] ?? ($_ENV['Email_Username'] ?? '');
    }
}

return [
    // Site identity
    'site_name' => $_ENV['SITE_NAME'] ?? 'Oil Patch Depot',
    'site_email' => $_ENV['SITE_EMAIL'] ?? ($_ENV['Email_Username'] ?? 'orders@oilpatchdepot.com'),

    // Database
    'db_host' => $_ENV['OPD_DB_HOST'] ?? '127.0.0.1',
    'db_port' => $_ENV['OPD_DB_PORT'] ?? '3306',
    'db_name' => $_ENV['OPD_DB_NAME'] ?? 'opd_admin',
    'db_user' => $_ENV['OPD_DB_USER'] ?? 'root',
    'db_pass' => $_ENV['OPD_DB_PASS'] ?? '',

    // Email
    'email_smtp_host' => $_ENV['Email_Outgoing_Server_SMTP'] ?? '',
    'email_smtp_port' => (int) ($_ENV['Email_Port'] ?? 465),
    'email_username' => $_ENV['Email_Username'] ?? '',
    'email_password' => $_ENV['Email_Password'] ?? '',

    // Stripe
    'stripe_secret_key' => $_ENV['STRIPE_SECRET_KEY'] ?? '',
    'stripe_publishable_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '',
    'stripe_webhook_secret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '',

    // ExpertTexting SMS
    'experttexting_username' => $_ENV['EXPERTTEXTING_USERNAME'] ?? '',
    'experttexting_password' => $_ENV['EXPERTTEXTING_PASSWORD'] ?? '',
    'experttexting_api_key' => $_ENV['EXPERTTEXTING_API_KEY'] ?? '',
    'experttexting_from' => $_ENV['EXPERTTEXTING_FROM'] ?? 'DEFAULT',
];
