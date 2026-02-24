<?php

declare(strict_types=1);

function experttexting_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }
    $config = require __DIR__ . '/../config/config.php';
    return is_array($config) ? $config : [];
}

function experttexting_username(): string
{
    return trim((string) (experttexting_config()['experttexting_username'] ?? ''));
}

function experttexting_password(): string
{
    return trim((string) (experttexting_config()['experttexting_password'] ?? ''));
}

function experttexting_api_key(): string
{
    return trim((string) (experttexting_config()['experttexting_api_key'] ?? ''));
}

function experttexting_from(): string
{
    $from = trim((string) (experttexting_config()['experttexting_from'] ?? ''));
    return $from !== '' ? $from : 'DEFAULT';
}

function experttexting_log(string $message, array $context = []): void
{
    if ($context) {
        $message .= ' ' . json_encode($context);
    }
    error_log($message);
}

function experttexting_send_sms(string $to, string $message, ?string $from = null): array
{
    $username = experttexting_username();
    $password = experttexting_password();
    $apiKey = experttexting_api_key();
    if ($username === '' || $password === '' || $apiKey === '') {
        return ['ok' => false, 'status' => 500, 'error' => 'ExpertTexting is not configured'];
    }

    $payload = [
        'username' => $username,
        'password' => $password,
        'api_key' => $apiKey,
        'FROM' => $from !== null && $from !== '' ? $from : experttexting_from(),
        'to' => $to,
        'text' => $message,
    ];

    $url = 'https://www.experttexting.com/ExptRestApi/sms/json/Message/Send';
    $body = http_build_query($payload);

    if (!function_exists('curl_init')) {
        $options = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ];
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
            experttexting_log('ExpertTexting request failed', ['status' => $status, 'error' => $error['message'] ?? 'stream error']);
            return ['ok' => false, 'status' => $status, 'error' => 'ExpertTexting request failed'];
        }
        return experttexting_parse_response((string) $response, $status);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errorNumber = curl_errno($ch);
    $errorMessage = curl_error($ch);
    curl_close($ch);

    if ($errorNumber) {
        experttexting_log('ExpertTexting request failed', ['status' => $status, 'error' => $errorMessage]);
        return ['ok' => false, 'status' => $status, 'error' => 'ExpertTexting request failed'];
    }

    return experttexting_parse_response((string) $response, $status);
}

function experttexting_parse_response(string $response, int $status): array
{
    $data = json_decode($response, true);
    if (!is_array($data)) {
        experttexting_log('ExpertTexting response parse failed', ['status' => $status, 'response' => substr($response, 0, 200)]);
        return ['ok' => false, 'status' => $status, 'error' => 'Invalid ExpertTexting response'];
    }

    // Log the full response for debugging
    experttexting_log('ExpertTexting response received', ['status' => $status, 'data' => $data]);

    $statusText = strtolower((string) ($data['Status'] ?? $data['status'] ?? ''));

    // Check HTTP status code first
    if ($status < 200 || $status >= 300) {
        $message = (string) ($data['Message'] ?? $data['message'] ?? 'ExpertTexting error');
        experttexting_log('ExpertTexting API error (HTTP)', ['status' => $status, 'message' => $message]);
        return ['ok' => false, 'status' => $status, 'error' => $message, 'data' => $data];
    }

    // Check for explicit error status in response
    // Treat as success if status is empty or is a success-like value
    $errorStatuses = ['error', 'failed', 'failure', 'invalid'];
    if ($statusText !== '' && in_array($statusText, $errorStatuses, true)) {
        $message = (string) ($data['Message'] ?? $data['message'] ?? 'ExpertTexting error');
        experttexting_log('ExpertTexting API error (Status)', ['status' => $status, 'statusText' => $statusText, 'message' => $message]);
        return ['ok' => false, 'status' => $status, 'error' => $message, 'data' => $data];
    }

    experttexting_log('ExpertTexting success', ['status' => $status, 'statusText' => $statusText]);
    return ['ok' => true, 'status' => $status, 'data' => $data];
}
