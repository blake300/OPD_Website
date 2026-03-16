<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/db_conn.php';

opd_require_role(['admin', 'manager']);

$type = $_GET['type'] ?? '';
$pdo = opd_db();

// Parse date range from query params
$period = $_GET['period'] ?? 'last7';
$startDate = null;
$endDate = null;

switch ($period) {
    case 'last7':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        $endDate = date('Y-m-d');
        break;
    case 'thisMonth':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-d');
        break;
    case 'lastMonth':
        $startDate = date('Y-m-01', strtotime('first day of last month'));
        $endDate = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-m-d');
        break;
    case 'custom':
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;
        if (!$startDate || !$endDate) {
            opd_json_response(['error' => 'Custom period requires startDate and endDate'], 400);
        }
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)) {
            opd_json_response(['error' => 'Dates must be in YYYY-MM-DD format'], 400);
        }
        break;
    default:
        opd_json_response(['error' => 'Invalid period'], 400);
}

// ---- Sales Volume Report ----
if ($type === 'sales_volume') {
    // Count days in the period for averages
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $days = max((int) $start->diff($end)->days + 1, 1);

    // Gross sales = sum of orderAmount for all orders in period
    // Net sales = gross - refunds
    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CAST(orderAmount AS DECIMAL(12,2))), 0) AS grossSales,
            COALESCE(SUM(CAST(refundAmount AS DECIMAL(12,2))), 0) AS totalRefunds,
            COALESCE(SUM(CAST(shipping AS DECIMAL(12,2))), 0) AS shippingCharged,
            COUNT(*) AS ordersPlaced
         FROM orders
         WHERE createdAt >= ? AND createdAt < DATE_ADD(?, INTERVAL 1 DAY)"
    );
    $stmt->execute([$startDate, $endDate]);
    $row = $stmt->fetch();

    $grossSales = (float) $row['grossSales'];
    $totalRefunds = (float) $row['totalRefunds'];
    $netSales = $grossSales - $totalRefunds;
    $ordersPlaced = (int) $row['ordersPlaced'];
    $shippingCharged = (float) $row['shippingCharged'];

    // Items sold
    $itemStmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CAST(oi.quantity AS UNSIGNED)), 0) AS itemsSold
         FROM order_items oi
         JOIN orders o ON o.id = oi.orderId
         WHERE o.createdAt >= ? AND o.createdAt < DATE_ADD(?, INTERVAL 1 DAY)"
    );
    $itemStmt->execute([$startDate, $endDate]);
    $itemsSold = (int) $itemStmt->fetchColumn();

    opd_json_response([
        'period' => $period,
        'startDate' => $startDate,
        'endDate' => $endDate,
        'days' => $days,
        'grossSales' => round($grossSales, 2),
        'avgGrossDaily' => round($grossSales / $days, 2),
        'netSales' => round($netSales, 2),
        'avgNetDaily' => round($netSales / $days, 2),
        'ordersPlaced' => $ordersPlaced,
        'itemsSold' => $itemsSold,
        'refundedAmount' => round($totalRefunds, 2),
        'shippingCharged' => round($shippingCharged, 2),
    ]);
}

// ---- Product Sales Report ----
if ($type === 'product_sales') {
    $search = trim((string) ($_GET['search'] ?? ''));

    if ($search === '') {
        opd_json_response(['error' => 'Search query is required'], 400);
    }

    // Find matching products by name or SKU
    $prodStmt = $pdo->prepare(
        "SELECT id, name, sku, price, category, status
         FROM products
         WHERE name LIKE ? OR sku LIKE ?
         ORDER BY name ASC
         LIMIT 50"
    );
    $likeSearch = '%' . $search . '%';
    $prodStmt->execute([$likeSearch, $likeSearch]);
    $products = $prodStmt->fetchAll();

    if (!$products) {
        opd_json_response([
            'products' => [],
            'period' => $period,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    $productIds = array_map(fn($p) => $p['id'], $products);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));

    // Get sales totals per product in the date range
    $salesStmt = $pdo->prepare(
        "SELECT oi.productId,
                COALESCE(SUM(CAST(oi.quantity AS UNSIGNED)), 0) AS totalQty,
                COALESCE(SUM(CAST(oi.total AS DECIMAL(12,2))), 0) AS totalRevenue,
                COUNT(DISTINCT oi.orderId) AS orderCount
         FROM order_items oi
         JOIN orders o ON o.id = oi.orderId
         WHERE oi.productId IN ({$placeholders})
           AND o.createdAt >= ? AND o.createdAt < DATE_ADD(?, INTERVAL 1 DAY)
         GROUP BY oi.productId"
    );
    $salesStmt->execute(array_merge($productIds, [$startDate, $endDate]));
    $salesByProduct = [];
    foreach ($salesStmt->fetchAll() as $row) {
        $salesByProduct[$row['productId']] = $row;
    }

    $results = [];
    foreach ($products as $product) {
        $sales = $salesByProduct[$product['id']] ?? null;
        $results[] = [
            'productId' => $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'price' => (float) $product['price'],
            'category' => $product['category'],
            'status' => $product['status'],
            'totalQty' => $sales ? (int) $sales['totalQty'] : 0,
            'totalRevenue' => $sales ? round((float) $sales['totalRevenue'], 2) : 0,
            'orderCount' => $sales ? (int) $sales['orderCount'] : 0,
        ];
    }

    opd_json_response([
        'products' => $results,
        'period' => $period,
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);
}

// ---- Product Order Detail ----
if ($type === 'product_orders') {
    $productId = $_GET['productId'] ?? '';
    if ($productId === '') {
        opd_json_response(['error' => 'productId is required'], 400);
    }

    $stmt = $pdo->prepare(
        "SELECT o.id AS orderId, o.number, o.customerName, o.status, o.createdAt,
                oi.quantity, oi.price, oi.total
         FROM order_items oi
         JOIN orders o ON o.id = oi.orderId
         WHERE oi.productId = ?
           AND o.createdAt >= ? AND o.createdAt < DATE_ADD(?, INTERVAL 1 DAY)
         ORDER BY o.createdAt DESC"
    );
    $stmt->execute([$productId, $startDate, $endDate]);
    $orders = [];
    foreach ($stmt->fetchAll() as $row) {
        $orders[] = [
            'orderId' => $row['orderId'],
            'orderNumber' => $row['number'],
            'customerName' => $row['customerName'],
            'status' => $row['status'],
            'createdAt' => $row['createdAt'],
            'quantity' => (int) $row['quantity'],
            'price' => (float) $row['price'],
            'lineTotal' => round((float) $row['total'], 2),
        ];
    }

    opd_json_response([
        'orders' => $orders,
        'productId' => $productId,
        'period' => $period,
        'startDate' => $startDate,
        'endDate' => $endDate,
    ]);
}

opd_json_response(['error' => 'Invalid report type. Use: sales_volume, product_sales, product_orders'], 400);
