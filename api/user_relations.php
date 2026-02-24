<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/db_conn.php';

opd_require_role(['admin', 'manager']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    opd_json_response(['error' => 'Method Not Allowed'], 405);
}

$userId = trim((string) ($_GET['userId'] ?? ''));
if ($userId === '') {
    opd_json_response(['error' => 'Missing userId'], 400);
}

$pdo = opd_db();

$vendors = [];
$clients = [];
function opd_month_window(): array
{
    $localTz = new DateTimeZone('America/Chicago');
    $startLocal = new DateTime('first day of this month 00:00:00', $localTz);
    $nextLocal = clone $startLocal;
    $nextLocal->modify('+1 month');

    $utcTz = new DateTimeZone('UTC');
    $startUtc = clone $startLocal;
    $startUtc->setTimezone($utcTz);
    $nextUtc = clone $nextLocal;
    $nextUtc->setTimezone($utcTz);

    return [$startUtc->format('Y-m-d H:i:s'), $nextUtc->format('Y-m-d H:i:s')];
}

[$monthStart, $monthEnd] = opd_month_window();

$vendorTotals = [];
$clientTotals = [];

try {
    $vendorStmt = $pdo->prepare('SELECT * FROM vendors WHERE userId = ? ORDER BY updatedAt DESC');
    $vendorStmt->execute([$userId]);
    $vendors = $vendorStmt->fetchAll();
} catch (Throwable $e) {
    $vendors = [];
}

try {
    $clientStmt = $pdo->prepare('SELECT * FROM clients WHERE userId = ? ORDER BY updatedAt DESC');
    $clientStmt->execute([$userId]);
    $clients = $clientStmt->fetchAll();
} catch (Throwable $e) {
    $clients = [];
}

try {
    $vendorTotalStmt = $pdo->prepare(
        'SELECT o.userId AS vendorUserId, COALESCE(SUM(o.total), 0) AS total
         FROM orders o
         LEFT JOIN clients c ON c.id = o.clientId
         WHERE o.createdAt >= ? AND o.createdAt < ?
           AND (
             o.clientUserId = ?
             OR (c.linkedUserId = ? AND c.userId = o.userId)
           )
         GROUP BY o.userId'
    );
    $vendorTotalStmt->execute([$monthStart, $monthEnd, $userId, $userId]);
    foreach ($vendorTotalStmt->fetchAll() as $row) {
        $vendorId = (string) ($row['vendorUserId'] ?? '');
        if ($vendorId === '') {
            continue;
        }
        $vendorTotals[$vendorId] = (float) ($row['total'] ?? 0);
    }
} catch (Throwable $e) {
    $vendorTotals = [];
}

try {
    $clientTotalStmt = $pdo->prepare(
        'SELECT c.id AS clientId, COALESCE(SUM(o.total), 0) AS total
         FROM clients c
         LEFT JOIN orders o
           ON o.userId = ?
           AND o.createdAt >= ? AND o.createdAt < ?
           AND (o.clientId = c.id OR (c.linkedUserId <> "" AND o.clientUserId = c.linkedUserId))
         WHERE c.userId = ?
         GROUP BY c.id'
    );
    $clientTotalStmt->execute([$userId, $monthStart, $monthEnd, $userId]);
    foreach ($clientTotalStmt->fetchAll() as $row) {
        $clientId = (string) ($row['clientId'] ?? '');
        if ($clientId === '') {
            continue;
        }
        $clientTotals[$clientId] = (float) ($row['total'] ?? 0);
    }
} catch (Throwable $e) {
    $clientTotals = [];
}

foreach ($vendors as &$vendor) {
    $linkedUserId = trim((string) ($vendor['linkedUserId'] ?? ''));
    $vendor['monthCumulative'] = $linkedUserId !== '' ? (float) ($vendorTotals[$linkedUserId] ?? 0) : 0.0;
}
unset($vendor);

foreach ($clients as &$client) {
    $clientId = trim((string) ($client['id'] ?? ''));
    $client['monthCumulative'] = $clientId !== '' ? (float) ($clientTotals[$clientId] ?? 0) : 0.0;
}
unset($client);

opd_json_response([
    'vendors' => $vendors,
    'clients' => $clients,
]);
