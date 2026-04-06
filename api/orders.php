<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

function opd_export_csv_value($value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if (is_array($value)) {
        return json_encode($value);
    }
    return (string) $value;
}

function opd_format_order_item_line(array $item): string
{
    $parts = [];
    $name = trim((string) ($item['name'] ?? ''));
    if ($name !== '') {
        $parts[] = $name;
    }
    $qty = (int) ($item['quantity'] ?? 0);
    $parts[] = "Qty: {$qty}";
    if (is_numeric($item['price'] ?? null)) {
        $parts[] = 'Price: ' . number_format((float) $item['price'], 2, '.', '');
    }
    if (is_numeric($item['total'] ?? null)) {
        $parts[] = 'Total: ' . number_format((float) $item['total'], 2, '.', '');
    }
    $arrival = $item['arrivalDate'] ?? null;
    if ($arrival !== null && $arrival !== '') {
        $parts[] = 'Arrival: ' . $arrival;
    }
    $productId = $item['productId'] ?? null;
    if ($productId) {
        $parts[] = 'Product: ' . $productId;
    }
    $variantId = $item['variantId'] ?? null;
    if ($variantId) {
        $parts[] = 'Variant: ' . $variantId;
    }
    return implode(' | ', $parts);
}

function opd_export_orders_csv(array $orders, array $orderItemsByOrder): void
{
    $columns = [
        ['key' => 'id', 'label' => 'Order ID'],
        ['key' => 'number', 'label' => 'Order #'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'customerName', 'label' => 'Customer Name'],
        ['key' => 'customerEmail', 'label' => 'Customer Email'],
        ['key' => 'customerPhone', 'label' => 'Customer Phone'],
        ['key' => 'address1', 'label' => 'Address 1'],
        ['key' => 'address2', 'label' => 'Address 2'],
        ['key' => 'city', 'label' => 'City'],
        ['key' => 'state', 'label' => 'State'],
        ['key' => 'postal', 'label' => 'Postcode'],
        ['key' => 'country', 'label' => 'Country'],
        ['key' => 'billingFirstName', 'label' => 'Billing First Name'],
        ['key' => 'billingLastName', 'label' => 'Billing Last Name'],
        ['key' => 'billingCompany', 'label' => 'Billing Company'],
        ['key' => 'billingAddress1', 'label' => 'Billing Address 1'],
        ['key' => 'billingAddress2', 'label' => 'Billing Address 2'],
        ['key' => 'billingCity', 'label' => 'Billing City'],
        ['key' => 'billingStateCode', 'label' => 'Billing State'],
        ['key' => 'billingEmail', 'label' => 'Billing Email'],
        ['key' => 'billingPhone', 'label' => 'Billing Phone'],
        ['key' => 'billingPostcode', 'label' => 'Billing Postcode'],
        ['key' => 'shippingFirstName', 'label' => 'Shipping First Name'],
        ['key' => 'shippingLastName', 'label' => 'Shipping Last Name'],
        ['key' => 'shippingCompany', 'label' => 'Shipping Company'],
        ['key' => 'shippingAddress1', 'label' => 'Shipping Address 1'],
        ['key' => 'shippingAddress2', 'label' => 'Shipping Address 2'],
        ['key' => 'shippingCity', 'label' => 'Shipping City'],
        ['key' => 'shippingStateCode', 'label' => 'Shipping State'],
        ['key' => 'shippingPhone', 'label' => 'Shipping Phone'],
        ['key' => 'shippingPostcode', 'label' => 'Shipping Postcode'],
        ['key' => 'notes', 'label' => 'Customer Notes'],
        ['key' => 'userId', 'label' => 'User ID'],
        ['key' => 'clientId', 'label' => 'Client ID'],
        ['key' => 'clientUserId', 'label' => 'Client User ID'],
        ['key' => 'orderAmount', 'label' => 'Order Amount'],
        ['key' => 'tax', 'label' => 'Order Tax Amount'],
        ['key' => 'shipping', 'label' => 'Order Shipping Amount'],
        ['key' => 'refundAmount', 'label' => 'Order Refund Amount'],
        ['key' => 'total', 'label' => 'Order Total Amount'],
        ['key' => 'totalAfterRefund', 'label' => 'Order Total Amount (Minus Refund)'],
        ['key' => 'currency', 'label' => 'Currency'],
        ['key' => 'shippingMethod', 'label' => 'Shipping Method'],
        ['key' => 'paymentStatus', 'label' => 'Payment Status'],
        ['key' => 'fulfillmentStatus', 'label' => 'Fulfillment Status'],
        ['key' => 'paymentMethod', 'label' => 'Payment Method'],
        ['key' => 'capturedAt', 'label' => 'Captured At'],
        ['key' => 'carrier', 'label' => 'Carrier'],
        ['key' => 'tracking', 'label' => 'Tracking'],
        ['key' => 'shipStatus', 'label' => 'Ship Status'],
        ['key' => 'shippedAt', 'label' => 'Shipped At'],
        ['key' => 'eta', 'label' => 'ETA'],
        ['key' => 'arrivalDate', 'label' => 'Arrival Dates'],
        ['key' => 'serviceArrivalDate', 'label' => 'Service Arrival Dates'],
        ['key' => 'createdAt', 'label' => 'Created At'],
        ['key' => 'updatedAt', 'label' => 'Updated At'],
        ['key' => 'itemsCount', 'label' => 'Item Count'],
        ['key' => 'itemsQuantity', 'label' => 'Item Quantity'],
        ['key' => 'itemsTotal', 'label' => 'Items Total'],
        ['key' => 'itemsDetail', 'label' => 'Items Detail'],
    ];

    $stamp = gmdate('Ymd_His');
    $filename = "orders-export-{$stamp}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}\"");
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        exit;
    }

    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, array_map(fn($col) => $col['label'], $columns));

    foreach ($orders as $order) {
        $orderId = (string) ($order['id'] ?? '');
        $orderItems = $orderItemsByOrder[$orderId] ?? [];
        $itemsCount = count($orderItems);
        $itemsQuantity = 0;
        $itemsTotal = 0.0;
        $itemLines = [];

        foreach ($orderItems as $item) {
            $itemsQuantity += (int) ($item['quantity'] ?? 0);
            $itemsTotal += (float) ($item['total'] ?? 0);
            $itemLines[] = opd_format_order_item_line($item);
        }

        $row = [];
        foreach ($columns as $column) {
            $key = $column['key'];
            if ($key === 'itemsCount') {
                $value = $itemsCount;
            } elseif ($key === 'itemsQuantity') {
                $value = $itemsQuantity;
            } elseif ($key === 'itemsTotal') {
                $value = $itemsCount ? number_format($itemsTotal, 2, '.', '') : '';
            } elseif ($key === 'itemsDetail') {
                $value = implode("\n", array_filter($itemLines));
            } else {
                $value = $order[$key] ?? '';
            }
            $row[] = opd_export_csv_value($value);
        }

        fputcsv($out, $row);
    }

    fclose($out);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    opd_require_role(['admin', 'manager']);
    $pdo = opd_db();

    // Single-order items request: GET ?items=ORDER_ID
    $itemsForOrder = $_GET['items'] ?? null;
    if ($itemsForOrder !== null && $itemsForOrder !== '') {
        $itemStmt = $pdo->prepare(
            'SELECT oi.id, oi.productId, oi.variantId, oi.name, oi.productName, oi.variantName, oi.sku, oi.price, oi.quantity, oi.total, oi.arrivalDate,
                    COALESCE(p.service, 0) AS isService
             FROM order_items oi
             LEFT JOIN products p ON p.id = oi.productId
             WHERE oi.orderId = ?
             ORDER BY oi.createdAt ASC'
        );
        $itemStmt->execute([$itemsForOrder]);
        $rows = [];
        foreach ($itemStmt->fetchAll() as $row) {
            $price = (float) ($row['price'] ?? 0);
            $qty = (int) ($row['quantity'] ?? 0);
            $total = $row['total'] !== null ? (float) $row['total'] : $price * $qty;
            $rows[] = [
                'id' => (string) ($row['id'] ?? ''),
                'productId' => (string) ($row['productId'] ?? ''),
                'variantId' => $row['variantId'] ?? null,
                'name' => (string) ($row['name'] ?? ''),
                'productName' => (string) ($row['productName'] ?? ''),
                'variantName' => (string) ($row['variantName'] ?? ''),
                'sku' => (string) ($row['sku'] ?? ''),
                'price' => $price,
                'quantity' => $qty,
                'total' => $total,
                'arrivalDate' => $row['arrivalDate'] ?? null,
                'isService' => !empty($row['isService']),
            ];
        }
        opd_json_response(['items' => $rows]);
    }

    $exportType = strtolower((string) ($_GET['export'] ?? ''));
    $isExport = in_array($exportType, ['excel', 'csv', 'xlsx'], true);

    $stmt = $pdo->query('SELECT * FROM orders ORDER BY updatedAt DESC');
    $orders = $stmt->fetchAll();
    if (!$orders) {
        if ($isExport) {
            opd_export_orders_csv([], []);
        }
        opd_json_response(['items' => [], 'total' => 0]);
    }

    $orderIds = array_values(array_filter(array_map(fn($row) => $row['id'] ?? null, $orders)));
    $paymentsByOrder = [];
    $shipmentsByOrder = [];
    $orderItemsByOrder = [];

    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    $tableCheck = $pdo->prepare(
        "SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('payments', 'shipments')"
    );
    $tableCheck->execute();
    $tables = array_map('strtolower', $tableCheck->fetchAll(PDO::FETCH_COLUMN));

    if (in_array('payments', $tables, true)) {
        $payStmt = $pdo->prepare(
            "SELECT * FROM payments WHERE orderId IN ({$placeholders}) ORDER BY capturedAt DESC, updatedAt DESC"
        );
        $payStmt->execute($orderIds);
        foreach ($payStmt->fetchAll() as $payment) {
            $orderId = (string) ($payment['orderId'] ?? '');
            if ($orderId !== '' && !isset($paymentsByOrder[$orderId])) {
                $paymentsByOrder[$orderId] = $payment;
            }
        }
    }

    if (in_array('shipments', $tables, true)) {
        $shipStmt = $pdo->prepare(
            "SELECT * FROM shipments WHERE orderId IN ({$placeholders}) ORDER BY shippedAt DESC, updatedAt DESC"
        );
        $shipStmt->execute($orderIds);
        foreach ($shipStmt->fetchAll() as $shipment) {
            $orderId = (string) ($shipment['orderId'] ?? '');
            if ($orderId !== '' && !isset($shipmentsByOrder[$orderId])) {
                $shipmentsByOrder[$orderId] = $shipment;
            }
        }
    }

    $itemsStmt = $pdo->prepare(
        "SELECT oi.orderId, oi.id, oi.productId, oi.variantId, oi.name, oi.productName, oi.variantName, oi.sku, oi.price, oi.quantity, oi.total, oi.arrivalDate,
                COALESCE(p.service, 0) AS isService
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.productId
         WHERE oi.orderId IN ({$placeholders})
         ORDER BY oi.createdAt ASC"
    );
    $itemsStmt->execute($orderIds);
    foreach ($itemsStmt->fetchAll() as $row) {
        $orderId = (string) ($row['orderId'] ?? '');
        if ($orderId === '') {
            continue;
        }
        if (!isset($orderItemsByOrder[$orderId])) {
            $orderItemsByOrder[$orderId] = [];
        }
        $price = (float) ($row['price'] ?? 0);
        $qty = (int) ($row['quantity'] ?? 0);
        $total = $row['total'] !== null ? (float) $row['total'] : $price * $qty;
        $orderItemsByOrder[$orderId][] = [
            'id' => (string) ($row['id'] ?? ''),
            'productId' => (string) ($row['productId'] ?? ''),
            'variantId' => $row['variantId'] ?? null,
            'name' => (string) ($row['name'] ?? ''),
            'productName' => (string) ($row['productName'] ?? ''),
            'variantName' => (string) ($row['variantName'] ?? ''),
            'sku' => (string) ($row['sku'] ?? ''),
            'price' => $price,
            'quantity' => $qty,
            'total' => $total,
            'arrivalDate' => $row['arrivalDate'] ?? null,
            'isService' => !empty($row['isService']),
        ];
    }

    $items = [];
    foreach ($orders as $order) {
        $orderId = (string) ($order['id'] ?? '');
        $payment = $paymentsByOrder[$orderId] ?? [];
        $shipment = $shipmentsByOrder[$orderId] ?? [];
        $arrivalList = $orderItemsByOrder[$orderId] ?? [];
        $arrivalLabel = null;
        $serviceArrivalLabel = null;
        if ($arrivalList) {
            $parts = [];
            $serviceParts = [];
            foreach ($arrivalList as $entry) {
                $date = $entry['arrivalDate'] ?? null;
                $label = $date ? (string) $date : 'TBD';
                $name = trim((string) ($entry['name'] ?? 'Item'));
                $parts[] = $name !== '' ? "{$name}: {$label}" : $label;
                if (!empty($entry['isService'])) {
                    $serviceParts[] = $name !== '' ? "{$name}: {$label}" : $label;
                }
            }
            $arrivalLabel = implode("\n", $parts);
            if ($serviceParts) {
                $serviceArrivalLabel = implode("\n", $serviceParts);
            }
        }
        $totalAfterRefund = null;
        $orderTotal = $order['total'] ?? null;
        if ($orderTotal !== null && $orderTotal !== '') {
            $refundAmount = $order['refundAmount'] ?? null;
            $refundValue = is_numeric($refundAmount) ? (float) $refundAmount : 0.0;
            $totalAfterRefund = (float) $orderTotal - $refundValue;
        }
        $items[] = array_merge($order, [
            'paymentMethod' => $payment['method'] ?? null,
            'capturedAt' => $payment['capturedAt'] ?? null,
            'carrier' => $shipment['carrier'] ?? null,
            'tracking' => $shipment['tracking'] ?? null,
            'shipStatus' => $shipment['status'] ?? null,
            'shippedAt' => $shipment['shippedAt'] ?? null,
            'eta' => $shipment['eta'] ?? null,
            'arrivalDate' => $arrivalLabel,
            'serviceArrivalDate' => $serviceArrivalLabel,
            'totalAfterRefund' => $totalAfterRefund,
        ]);
    }

    if ($isExport) {
        opd_export_orders_csv($items, $orderItemsByOrder);
    }

    opd_json_response(['items' => $items, 'total' => count($items)]);
}

// Handle cancelled orders — return items to inventory
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'PUT') {
    $raw = file_get_contents('php://input');
    $_SERVER['_CACHED_INPUT'] = $raw;
    $payload = json_decode($raw, true) ?: [];
    $newStatus = trim((string) ($payload['status'] ?? ''));
    $id = $_GET['id'] ?? ($payload['id'] ?? '');

    if ($newStatus === 'Cancelled' && $id !== '') {
        $pdo = opd_db();
        // Check current status to avoid double-returning
        $curStmt = $pdo->prepare('SELECT status FROM orders WHERE id = ? LIMIT 1');
        $curStmt->execute([$id]);
        $curOrder = $curStmt->fetch();
        if ($curOrder && $curOrder['status'] !== 'Cancelled') {
            // Return all order items to inventory
            $itemStmt = $pdo->prepare('SELECT productId, variantId, quantity FROM order_items WHERE orderId = ?');
            $itemStmt->execute([$id]);
            $now = gmdate('Y-m-d H:i:s');
            foreach ($itemStmt->fetchAll() as $oi) {
                $qty = (int) ($oi['quantity'] ?? 0);
                if ($qty <= 0) continue;
                if (!empty($oi['variantId'])) {
                    $pdo->prepare('UPDATE product_variants SET inventory = inventory + ?, updatedAt = ? WHERE id = ?')
                        ->execute([$qty, $now, $oi['variantId']]);
                } elseif (!empty($oi['productId'])) {
                    $pdo->prepare('UPDATE products SET inventory = inventory + ?, updatedAt = ? WHERE id = ?')
                        ->execute([$qty, $now, $oi['productId']]);
                }
            }
        }
    }
}

opd_handle_crud(
    'orders',
    'ord',
    [
        'number',
        'status',
        'approvalStatus',
        'customerName',
        'orderAmount',
        'total',
        'tax',
        'shipping',
        'refundAmount',
        'currency',
        'shippingMethod',
        'deliveryZone',
        'deliveryClass',
        'paymentStatus',
        'fulfillmentStatus',
        'billingFirstName',
        'billingLastName',
        'billingCompany',
        'billingAddress1',
        'billingAddress2',
        'billingCity',
        'billingStateCode',
        'billingEmail',
        'billingPhone',
        'billingPostcode',
        'shippingFirstName',
        'shippingLastName',
        'shippingCompany',
        'shippingAddress1',
        'shippingAddress2',
        'shippingCity',
        'shippingStateCode',
        'shippingPhone',
        'shippingPostcode',
        'notes'
    ],
    true,
    ['admin', 'manager'],
    ['admin']
);
