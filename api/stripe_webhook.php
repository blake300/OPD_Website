<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/stripe_service.php';
require_once __DIR__ . '/../src/db_conn.php';
require_once __DIR__ . '/../src/api_helpers.php';

function stripe_store_payment_method(string $userId, string $paymentMethodId, string $labelHint = ''): void
{
    $pmResponse = stripe_retrieve_payment_method($paymentMethodId);
    if (!$pmResponse['ok']) {
        stripe_log('Stripe webhook: payment method fetch failed', ['paymentMethodId' => $paymentMethodId]);
        return;
    }

    $pm = $pmResponse['data'] ?? [];
    if (($pm['type'] ?? '') !== 'card') {
        return;
    }
    $card = $pm['card'] ?? [];
    $last4 = (string) ($card['last4'] ?? '');
    $expMonth = (int) ($card['exp_month'] ?? 0);
    $expYear = (int) ($card['exp_year'] ?? 0);
    $brand = (string) ($card['brand'] ?? 'Card');
    $label = $labelHint !== '' ? $labelHint : sprintf('%s ending %s', ucfirst($brand), $last4);

    try {
        $pdo = opd_db();
        $exists = $pdo->prepare('SELECT id FROM payment_methods WHERE userId = ? AND stripePaymentMethodId = ? LIMIT 1');
        $exists->execute([$userId, $paymentMethodId]);
        if ($exists->fetch()) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $insert = $pdo->prepare(
            'INSERT INTO payment_methods (id, userId, label, type, brand, last4, stripePaymentMethodId, expMonth, expYear, createdAt, updatedAt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            opd_generate_id('pm'),
            $userId,
            $label,
            'card',
            $brand,
            $last4,
            $paymentMethodId,
            $expMonth ?: null,
            $expYear ?: null,
            $now,
            $now,
        ]);
    } catch (Throwable $e) {
        stripe_log('Stripe webhook: payment method store failed', ['error' => $e->getMessage()]);
    }
}

function stripe_handle_setup_intent(array $intent): void
{
    $metadata = is_array($intent['metadata'] ?? null) ? $intent['metadata'] : [];
    $userId = (string) ($metadata['userId'] ?? '');
    if ($userId === '') {
        return;
    }

    $customerId = (string) ($intent['customer'] ?? '');
    if ($customerId !== '') {
        $existing = stripe_get_customer_id_for_user($userId);
        if (!$existing) {
            stripe_set_customer_id_for_user($userId, $customerId);
        }
    }

    $paymentMethodId = (string) ($intent['payment_method'] ?? '');
    if ($paymentMethodId === '') {
        return;
    }

    $label = (string) ($metadata['label'] ?? '');
    stripe_store_payment_method($userId, $paymentMethodId, $label);
}

function stripe_handle_payment_intent(array $intent, bool $success): void
{
    $paymentIntentId = (string) ($intent['id'] ?? '');
    if ($paymentIntentId === '') {
        return;
    }

    $metadata = is_array($intent['metadata'] ?? null) ? $intent['metadata'] : [];
    $orderId = (string) ($metadata['orderId'] ?? '');
    $amountCents = (int) ($intent['amount_received'] ?? $intent['amount'] ?? 0);
    $amount = $amountCents / 100;
    $status = $success ? 'succeeded' : 'failed';
    $now = gmdate('Y-m-d H:i:s');

    $pdo = opd_db();
    try {
        $check = $pdo->prepare('SELECT id, status FROM payments WHERE externalId = ? LIMIT 1');
        $check->execute([$paymentIntentId]);
        $existing = $check->fetch();
        $capturedAt = $success ? $now : null;

        if ($existing) {
            $currentStatus = (string) ($existing['status'] ?? '');
            if ($success || $currentStatus !== 'succeeded') {
                $update = $pdo->prepare(
                    'UPDATE payments SET amount = ?, status = ?, capturedAt = ?, updatedAt = ? WHERE id = ?'
                );
                $update->execute([
                    $amount,
                    $status,
                    $capturedAt,
                    $now,
                    $existing['id'],
                ]);
            }
        } else {
            $insert = $pdo->prepare(
                'INSERT INTO payments (id, orderId, method, externalId, amount, status, capturedAt, updatedAt)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $insert->execute([
                opd_generate_id('pay'),
                $orderId !== '' ? $orderId : null,
                'stripe',
                $paymentIntentId,
                $amount,
                $status,
                $capturedAt,
                $now,
            ]);
        }
    } catch (Throwable $e) {
        stripe_log('Stripe webhook: payment upsert failed', ['error' => $e->getMessage()]);
    }

    if ($orderId !== '') {
        try {
            $order = $pdo->prepare('SELECT paymentStatus FROM orders WHERE id = ? LIMIT 1');
            $order->execute([$orderId]);
            $row = $order->fetch();
            if ($row) {
                $currentStatus = (string) ($row['paymentStatus'] ?? '');
                if ($success || $currentStatus !== 'paid') {
                    $update = $pdo->prepare('UPDATE orders SET paymentStatus = ?, updatedAt = ? WHERE id = ?');
                    $update->execute([$success ? 'paid' : 'failed', $now, $orderId]);
                }
            }
        } catch (Throwable $e) {
            stripe_log('Stripe webhook: order update failed', ['error' => $e->getMessage()]);
        }
    }
}

$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
$secret = stripe_webhook_secret();

if ($secret === '') {
    http_response_code(500);
    echo 'Stripe webhook secret not configured';
    exit;
}

if (!stripe_verify_signature($payload, (string) $signature, $secret)) {
    http_response_code(400);
    echo 'Invalid signature';
    exit;
}

$event = json_decode($payload, true);
if (!is_array($event)) {
    http_response_code(400);
    echo 'Invalid payload';
    exit;
}

$type = (string) ($event['type'] ?? '');
$object = $event['data']['object'] ?? null;

try {
    if ($type === 'setup_intent.succeeded' && is_array($object)) {
        stripe_handle_setup_intent($object);
    } elseif ($type === 'payment_intent.succeeded' && is_array($object)) {
        stripe_handle_payment_intent($object, true);
    } elseif ($type === 'payment_intent.payment_failed' && is_array($object)) {
        stripe_handle_payment_intent($object, false);
    }
} catch (Throwable $e) {
    stripe_log('Stripe webhook: unhandled error processing ' . $type, ['error' => $e->getMessage()]);
}

http_response_code(200);
echo 'ok';
