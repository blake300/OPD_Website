<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/tax_rates.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    opd_json_response(['error' => 'Method not allowed'], 405);
}

$payload = [];
if ($method === 'POST') {
    $payload = opd_read_json();
} else {
    $payload = $_GET;
}

$subtotal = isset($payload['subtotal']) && is_numeric($payload['subtotal']) ? (float) $payload['subtotal'] : 0.0;
$state = trim((string) ($payload['state'] ?? ''));
$postal = trim((string) ($payload['postal'] ?? $payload['zip'] ?? ''));
$shippingMethod = trim((string) ($payload['shippingMethod'] ?? ''));

// Pickup orders always use the store zip (74820) for tax
if ($shippingMethod === 'pickup') {
    $state = 'OK';
    $postal = '74820';
}

$taxData = opd_calculate_ok_sales_tax($subtotal, $state, $postal);

opd_json_response([
    'subtotal' => round($subtotal, 2),
    'tax' => $taxData['tax'],
    'taxRate' => $taxData['ratePercent'],
    'taxable' => $taxData['taxable'],
    'rateFound' => $taxData['rateFound'],
    'state' => $taxData['state'],
    'zip' => $taxData['zip'],
]);
