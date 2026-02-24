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

return [
    'db_host' => $_ENV['OPD_DB_HOST'] ?? '127.0.0.1',
    'db_port' => $_ENV['OPD_DB_PORT'] ?? '3306',
    'db_name' => $_ENV['OPD_DB_NAME'] ?? 'opd_admin',
    'db_user' => $_ENV['OPD_DB_USER'] ?? 'root',
    'db_pass' => $_ENV['OPD_DB_PASS'] ?? '',
    'stripe_secret_key' => $_ENV['STRIPE_SECRET_KEY'] ?? '',
    'stripe_publishable_key' => $_ENV['STRIPE_PUBLISHABLE_KEY'] ?? '',
    'stripe_webhook_secret' => $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '',
    'experttexting_username' => $_ENV['EXPERTTEXTING_USERNAME'] ?? '',
    'experttexting_password' => $_ENV['EXPERTTEXTING_PASSWORD'] ?? '',
    'experttexting_api_key' => $_ENV['EXPERTTEXTING_API_KEY'] ?? '',
    'experttexting_from' => $_ENV['EXPERTTEXTING_FROM'] ?? 'DEFAULT',
];
