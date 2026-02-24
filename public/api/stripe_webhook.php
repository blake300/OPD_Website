<?php

/**
 * Proxy to the real Stripe webhook handler.
 * Includes path diagnostics so if a require fails, Stripe gets a useful response
 * instead of a generic Apache 500.
 */

// Register a shutdown handler to catch fatal errors (like missing require files)
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR], true)) {
        // Only send headers if not already sent
        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: application/json');
        }
        echo json_encode([
            'error' => 'fatal',
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
        ]);
    }
});

$target = __DIR__ . '/../../api/stripe_webhook.php';

if (!file_exists($target)) {
    // The real webhook handler file is not at the expected path.
    // Check what paths exist so we can fix the deployment.
    $diag = [
        'error' => 'Webhook handler not found',
        'expected_path' => realpath(__DIR__ . '/../..') . '/api/stripe_webhook.php',
        '__DIR__' => __DIR__,
        'parent_exists' => is_dir(__DIR__ . '/../..'),
        'api_dir_exists' => is_dir(__DIR__ . '/../../api'),
        'src_dir_exists' => is_dir(__DIR__ . '/../../src'),
        'config_dir_exists' => is_dir(__DIR__ . '/../../config'),
    ];
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode($diag);
    exit;
}

require $target;
