<?php

declare(strict_types=1);

require_once __DIR__ . '/db_conn.php';

function stripe_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $config = require __DIR__ . '/../config/config.php';
    return is_array($config) ? $config : [];
}

function stripe_secret_key(): string
{
    return trim((string) (stripe_config()['stripe_secret_key'] ?? ''));
}

function stripe_publishable_key(): string
{
    return trim((string) (stripe_config()['stripe_publishable_key'] ?? ''));
}

function stripe_webhook_secret(): string
{
    return trim((string) (stripe_config()['stripe_webhook_secret'] ?? ''));
}

function stripe_log(string $message, array $context = []): void
{
    if ($context) {
        $message .= ' ' . json_encode($context);
    }
    error_log($message);
}

function stripe_request(string $method, string $path, array $params = [], array $options = []): array
{
    $secret = stripe_secret_key();
    if ($secret === '') {
        return ['ok' => false, 'status' => 500, 'error' => 'Stripe secret key is not configured'];
    }

    $method = strtoupper($method);
    $url = 'https://api.stripe.com/v1' . $path;
    $headers = [
        'Authorization: Bearer ' . $secret,
        'Content-Type: application/x-www-form-urlencoded',
    ];

    if (!empty($options['idempotency_key'])) {
        $headers[] = 'Idempotency-Key: ' . $options['idempotency_key'];
    }

    $body = '';
    if ($method === 'GET' && $params) {
        $url .= '?' . http_build_query($params);
    } elseif ($method !== 'GET') {
        $body = http_build_query($params);
    }

    if (!function_exists('curl_init')) {
        $headerString = implode("\r\n", $headers);
        $options = [
            'http' => [
                'method' => $method,
                'header' => $headerString,
                'timeout' => (int) ($options['timeout'] ?? 30),
                'ignore_errors' => true,
            ],
        ];
        if ($method !== 'GET') {
            $options['http']['content'] = $body;
        }
        $context = stream_context_create($options);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $headerLine) {
                if (preg_match('/HTTP\\/\\d\\.\\d\\s+(\\d{3})/', $headerLine, $matches)) {
                    $status = (int) $matches[1];
                    break;
                }
            }
        }
        if ($response === false) {
            $error = error_get_last();
            stripe_log('Stripe request failed', [
                'path' => $path,
                'error' => $error['message'] ?? 'stream error'
            ]);
            return ['ok' => false, 'status' => $status, 'error' => 'Stripe request failed'];
        }
        $data = json_decode((string) $response, true);
        if (!is_array($data)) {
            stripe_log('Stripe response parse failed', ['path' => $path, 'status' => $status]);
            return ['ok' => false, 'status' => $status, 'error' => 'Invalid Stripe response'];
        }
        if ($status < 200 || $status >= 300) {
            $message = $data['error']['message'] ?? 'Stripe error';
            stripe_log('Stripe API error', ['path' => $path, 'status' => $status, 'message' => $message]);
            return ['ok' => false, 'status' => $status, 'error' => $message, 'data' => $data];
        }
        return ['ok' => true, 'status' => $status, 'data' => $data];
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => (int) ($options['timeout'] ?? 30),
        CURLOPT_CONNECTTIMEOUT => (int) ($options['connect_timeout'] ?? 10),
    ]);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorNumber = curl_errno($ch);
    $errorMessage = curl_error($ch);
    curl_close($ch);

    if ($errorNumber) {
        stripe_log('Stripe request failed', ['path' => $path, 'error' => $errorMessage]);
        return ['ok' => false, 'status' => 0, 'error' => 'Stripe request failed'];
    }

    $data = json_decode((string) $response, true);
    if (!is_array($data)) {
        stripe_log('Stripe response parse failed', ['path' => $path, 'status' => $status]);
        return ['ok' => false, 'status' => $status, 'error' => 'Invalid Stripe response'];
    }

    if ($status < 200 || $status >= 300) {
        $message = $data['error']['message'] ?? 'Stripe error';
        stripe_log('Stripe API error', ['path' => $path, 'status' => $status, 'message' => $message]);
        return ['ok' => false, 'status' => $status, 'error' => $message, 'data' => $data];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data];
}

function stripe_get_customer_id_for_user(string $userId): ?string
{
    $pdo = opd_db();
    stripe_ensure_customer_column($pdo);
    $stmt = $pdo->prepare('SELECT stripeCustomerId FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $customerId = $row['stripeCustomerId'] ?? null;
    return is_string($customerId) && $customerId !== '' ? $customerId : null;
}

function stripe_set_customer_id_for_user(string $userId, string $customerId): void
{
    $pdo = opd_db();
    stripe_ensure_customer_column($pdo);
    $stmt = $pdo->prepare('UPDATE users SET stripeCustomerId = ?, updatedAt = ? WHERE id = ?');
    $stmt->execute([$customerId, gmdate('Y-m-d H:i:s'), $userId]);
}

function stripe_ensure_customer_column(PDO $pdo): void
{
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'stripeCustomerId'"
    );
    $stmt->execute();
    $exists = (int) $stmt->fetchColumn();
    if ($exists === 0) {
        $pdo->exec('ALTER TABLE users ADD COLUMN stripeCustomerId VARCHAR(255)');
    }
}

function stripe_get_or_create_customer(array $user): ?string
{
    $userId = (string) ($user['id'] ?? '');
    if ($userId === '') {
        return null;
    }

    $existing = stripe_get_customer_id_for_user($userId);
    if ($existing) {
        return $existing;
    }

    $payload = [
        'email' => (string) ($user['email'] ?? ''),
        'name' => (string) ($user['name'] ?? ''),
        'metadata[userId]' => $userId,
    ];
    $response = stripe_request('POST', '/customers', $payload);
    if (!$response['ok']) {
        return null;
    }

    $customerId = $response['data']['id'] ?? null;
    if (!is_string($customerId) || $customerId === '') {
        return null;
    }
    stripe_set_customer_id_for_user($userId, $customerId);
    return $customerId;
}

/**
 * Create a Stripe customer for guest checkout (not saved to database)
 */
function stripe_create_guest_customer(string $email, string $name = ''): ?string
{
    if ($email === '') {
        return null;
    }

    $payload = [
        'email' => $email,
        'metadata[isGuest]' => 'true',
    ];
    if ($name !== '') {
        $payload['name'] = $name;
    }

    $response = stripe_request('POST', '/customers', $payload);
    if (!$response['ok']) {
        return null;
    }

    $customerId = $response['data']['id'] ?? null;
    if (!is_string($customerId) || $customerId === '') {
        return null;
    }

    return $customerId;
}

function stripe_create_setup_intent(string $customerId, array $metadata = []): array
{
    $payload = [
        'customer' => $customerId,
        'usage' => 'off_session',
        'payment_method_types[]' => 'card',
    ];
    foreach ($metadata as $key => $value) {
        $payload['metadata[' . $key . ']'] = (string) $value;
    }
    return stripe_request('POST', '/setup_intents', $payload);
}

function stripe_retrieve_payment_method(string $paymentMethodId): array
{
    return stripe_request('GET', '/payment_methods/' . urlencode($paymentMethodId));
}

function stripe_attach_payment_method(string $paymentMethodId, string $customerId): array
{
    return stripe_request('POST', '/payment_methods/' . urlencode($paymentMethodId) . '/attach', [
        'customer' => $customerId,
    ]);
}

function stripe_detach_payment_method(string $paymentMethodId): array
{
    return stripe_request('POST', '/payment_methods/' . urlencode($paymentMethodId) . '/detach');
}

function stripe_create_payment_intent(string $customerId, string $paymentMethodId, int $amount, string $currency, array $metadata = [], ?string $idempotencyKey = null): array
{
    $payload = [
        'amount' => $amount,
        'currency' => $currency,
        'customer' => $customerId,
        'payment_method' => $paymentMethodId,
        'confirm' => 'true',
        'off_session' => 'true',
    ];
    foreach ($metadata as $key => $value) {
        $payload['metadata[' . $key . ']'] = (string) $value;
    }
    $options = [];
    if ($idempotencyKey) {
        $options['idempotency_key'] = $idempotencyKey;
    }
    return stripe_request('POST', '/payment_intents', $payload, $options);
}

function stripe_create_checkout_intent(string $customerId, int $amount, string $currency, array $metadata = [], ?string $idempotencyKey = null): array
{
    $payload = [
        'amount' => $amount,
        'currency' => $currency,
        'customer' => $customerId,
        'payment_method_types[]' => 'card',
    ];
    foreach ($metadata as $key => $value) {
        $payload['metadata[' . $key . ']'] = (string) $value;
    }
    $options = [];
    if ($idempotencyKey) {
        $options['idempotency_key'] = $idempotencyKey;
    }
    return stripe_request('POST', '/payment_intents', $payload, $options);
}

function stripe_retrieve_payment_intent(string $paymentIntentId): array
{
    return stripe_request('GET', '/payment_intents/' . urlencode($paymentIntentId));
}

function stripe_verify_signature(string $payload, string $signatureHeader, string $secret, int $toleranceSeconds = 300): bool
{
    if ($payload === '' || $signatureHeader === '' || $secret === '') {
        return false;
    }

    $parts = [];
    foreach (explode(',', $signatureHeader) as $piece) {
        $pair = explode('=', trim($piece), 2);
        if (count($pair) === 2) {
            $parts[$pair[0]] = $parts[$pair[0]] ?? [];
            $parts[$pair[0]][] = $pair[1];
        }
    }

    $timestamp = $parts['t'][0] ?? null;
    if (!$timestamp || !ctype_digit($timestamp)) {
        return false;
    }

    $signedPayload = $timestamp . '.' . $payload;
    $expected = hash_hmac('sha256', $signedPayload, $secret);
    $signatures = $parts['v1'] ?? [];
    $validSignature = false;
    foreach ($signatures as $signature) {
        if (hash_equals($expected, $signature)) {
            $validSignature = true;
            break;
        }
    }
    if (!$validSignature) {
        return false;
    }

    $age = time() - (int) $timestamp;
    return $age >= 0 && $age <= $toleranceSeconds;
}
