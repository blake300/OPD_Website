<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/invoice_service.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = opd_db();
opd_ensure_invoice_tables();

// PDF download — supports both admin and customer access
if ($method === 'GET' && !empty($_GET['download'])) {
    $invoiceId = trim((string) ($_GET['id'] ?? ''));
    if ($invoiceId === '') {
        opd_json_response(['error' => 'Missing invoice id'], 400);
    }

    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();
    if (!$invoice) {
        opd_json_response(['error' => 'Invoice not found'], 404);
    }

    // Auth: admin or the invoice's user
    $adminUser = opd_current_user();
    $siteUser = site_current_user();
    $orderOwnerStmt = $pdo->prepare('SELECT userId, clientUserId FROM orders WHERE id = ? LIMIT 1');
    $orderOwnerStmt->execute([$invoice['orderId']]);
    $orderOwner = $orderOwnerStmt->fetch() ?: [];
    $isAdmin = $adminUser && in_array($adminUser['role'] ?? '', ['admin', 'manager'], true);
    $siteUserId = (string) ($siteUser['id'] ?? '');
    $isOwner = $siteUser && (
        $siteUserId === (string) ($invoice['userId'] ?? '')
        || $siteUserId === (string) ($orderOwner['userId'] ?? '')
        || $siteUserId === (string) ($orderOwner['clientUserId'] ?? '')
    );

    if (!$isAdmin && !$isOwner) {
        opd_json_response(['error' => 'Unauthorized'], 401);
    }

    // Generate PDF if needed
    $pdfPath = $invoice['pdfPath'] ?? '';
    $fullPath = __DIR__ . '/../public' . $pdfPath;
    if ($pdfPath === '' || !file_exists($fullPath)) {
        $pdfPath = opd_generate_invoice_pdf($invoiceId);
        $fullPath = __DIR__ . '/../public' . $pdfPath;
    }

    if (!file_exists($fullPath)) {
        opd_json_response(['error' => 'PDF not found'], 404);
    }

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($pdfPath) . '"');
    header('Content-Length: ' . filesize($fullPath));
    readfile($fullPath);
    exit;
}

// All other operations require admin
if ($method === 'GET') {
    opd_require_role(['admin', 'manager']);

    // List invoices with order info
    $status = trim((string) ($_GET['status'] ?? ''));
    $orderId = trim((string) ($_GET['orderId'] ?? ''));
    $where = [];
    $params = [];

    if ($status !== '' && in_array($status, ['pending', 'paid', 'overdue'], true)) {
        $where[] = 'i.status = ?';
        $params[] = $status;
    }
    if ($orderId !== '') {
        $where[] = 'i.orderId = ?';
        $params[] = $orderId;
    }

    $sql = 'SELECT i.*, o.number AS orderNumber, o.customerName, o.customerEmail
            FROM invoices i
            LEFT JOIN orders o ON o.id = i.orderId'
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY i.createdAt DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    // Auto-detect overdue invoices
    $now = gmdate('Y-m-d');
    foreach ($items as &$item) {
        if ($item['status'] === 'pending' && $item['dueDate'] < $now) {
            $item['status'] = 'overdue';
            $pdo->prepare('UPDATE invoices SET status = ?, updatedAt = ? WHERE id = ? AND status = ?')
                ->execute(['overdue', gmdate('Y-m-d H:i:s'), $item['id'], 'pending']);
        }
    }
    unset($item);

    opd_json_response(['items' => $items, 'total' => count($items)]);
}

if ($method === 'PUT') {
    opd_require_role(['admin']);
    opd_require_csrf();

    $id = trim((string) ($_GET['id'] ?? ''));
    $payload = opd_read_json();
    $action = trim((string) ($payload['action'] ?? ''));

    if ($id === '') {
        opd_json_response(['error' => 'Missing invoice id'], 400);
    }

    if ($action === 'mark_paid') {
        $success = opd_mark_invoice_paid($id);
        if (!$success) {
            opd_json_response(['error' => 'Invoice not found or already paid'], 404);
        }
        opd_json_response(['ok' => true]);
    }

    if ($action === 'resend') {
        $sent = opd_email_invoice($id);
        opd_json_response(['ok' => $sent, 'error' => $sent ? null : 'Failed to send email']);
    }

    if ($action === 'regenerate') {
        $path = opd_generate_invoice_pdf($id);
        opd_json_response(['ok' => true, 'pdfPath' => $path]);
    }

    opd_json_response(['error' => 'Unknown action'], 400);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
