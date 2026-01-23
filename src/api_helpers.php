<?php

declare(strict_types=1);

function opd_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

function opd_read_json(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function opd_require_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        opd_json_response(['error' => 'Method Not Allowed'], 405);
    }
}
