<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/db_conn.php';
require_once __DIR__ . '/../src/stripe_service.php';

$user = opd_require_role(['admin']);
opd_require_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    opd_json_response(['error' => 'Method Not Allowed'], 405);
}

$payload = opd_read_json();

$orderId = trim((string) ($payload['orderId'] ?? ''));
$refundAmount = (float) ($payload['refundAmount'] ?? 0);
$refundMethod = trim((string) ($payload['refundMethod'] ?? ''));
$returnToInventory = !empty($payload['returnToInventory']);
$itemAdjustments = $payload['itemAdjustments'] ?? null;

if ($orderId === '') {
    opd_json_response(['error' => 'Missing orderId'], 400);
}
if ($refundAmount <= 0) {
    opd_json_response(['error' => 'Refund amount must be greater than zero'], 400);
}
if (!in_array($refundMethod, ['stripe', 'manual'], true)) {
    opd_json_response(['error' => 'refundMethod must be "stripe" or "manual"'], 400);
}

$pdo = opd_db();

// Get the order
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    opd_json_response(['error' => 'Order not found'], 404);
}

$currentRefund = (float) ($order['refundAmount'] ?? 0);
$orderTotal = (float) ($order['total'] ?? 0);
$newTotalRefund = $currentRefund + $refundAmount;

if ($newTotalRefund > $orderTotal) {
    opd_json_response(['error' => sprintf(
        'Refund amount ($%.2f) would exceed order total ($%.2f). Already refunded: $%.2f',
        $refundAmount,
        $orderTotal,
        $currentRefund
    )], 400);
}

// Process Stripe refund if requested
$stripeRefundId = null;
if ($refundMethod === 'stripe') {
    // Find payment intent for this order
    $tableCheck = $pdo->prepare(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'payments'"
    );
    $tableCheck->execute();
    if (!$tableCheck->fetch()) {
        opd_json_response(['error' => 'Payments table not found'], 500);
    }

    $payStmt = $pdo->prepare(
        'SELECT externalId, method FROM payments WHERE orderId = ? AND status = ? ORDER BY capturedAt DESC LIMIT 1'
    );
    $payStmt->execute([$orderId, 'succeeded']);
    $payment = $payStmt->fetch();

    if (!$payment || empty($payment['externalId'])) {
        opd_json_response(['error' => 'No successful Stripe payment found for this order. Use manual refund instead.'], 400);
    }

    $paymentIntentId = (string) $payment['externalId'];
    $amountCents = (int) round($refundAmount * 100);

    $result = stripe_create_refund($paymentIntentId, $amountCents, [
        'orderId' => $orderId,
        'adminEmail' => $user['email'] ?? '',
    ]);

    if (!$result['ok']) {
        $errorMsg = $result['error'] ?? 'Stripe refund failed';
        opd_json_response(['error' => 'Stripe refund failed: ' . $errorMsg], 400);
    }

    $stripeRefundId = $result['data']['id'] ?? null;
}

// Return items to inventory if requested
if ($returnToInventory && is_array($itemAdjustments)) {
    foreach ($itemAdjustments as $adj) {
        $itemId = $adj['itemId'] ?? '';
        $refundQty = (int) ($adj['refundQty'] ?? 0);
        if ($itemId === '' || $refundQty <= 0) {
            continue;
        }

        // Get the order item to find product/variant
        $itemStmt = $pdo->prepare('SELECT productId, variantId FROM order_items WHERE id = ? LIMIT 1');
        $itemStmt->execute([$itemId]);
        $orderItem = $itemStmt->fetch();
        if (!$orderItem) {
            continue;
        }

        // Return to variant inventory if applicable, otherwise product
        $variantId = $orderItem['variantId'] ?? null;
        if ($variantId) {
            $pdo->prepare('UPDATE product_variants SET inventory = inventory + ?, updatedAt = ? WHERE id = ?')
                ->execute([$refundQty, gmdate('Y-m-d H:i:s'), $variantId]);
        } else {
            $productId = $orderItem['productId'] ?? null;
            if ($productId) {
                $pdo->prepare('UPDATE products SET inventory = inventory + ?, updatedAt = ? WHERE id = ?')
                    ->execute([$refundQty, gmdate('Y-m-d H:i:s'), $productId]);
            }
        }
    }
}

// Update order refund amount
$now = gmdate('Y-m-d H:i:s');
$pdo->prepare('UPDATE orders SET refundAmount = ?, updatedAt = ? WHERE id = ?')
    ->execute([$newTotalRefund, $now, $orderId]);

opd_json_response([
    'ok' => true,
    'refundAmount' => round($refundAmount, 2),
    'totalRefunded' => round($newTotalRefund, 2),
    'orderTotal' => round($orderTotal, 2),
    'method' => $refundMethod,
    'stripeRefundId' => $stripeRefundId,
    'returnedToInventory' => $returnToInventory,
]);
