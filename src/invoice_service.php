<?php

declare(strict_types=1);

require_once __DIR__ . '/db_conn.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/../config/config.php';

define('FPDF_FONTPATH', __DIR__ . '/../vendor/fpdf/font/');
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

/**
 * Ensure invoices + sequence tables exist.
 */
function opd_ensure_invoice_tables(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;
    $pdo = opd_db();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS invoices (
            id VARCHAR(64) PRIMARY KEY,
            orderId VARCHAR(64) NOT NULL,
            userId VARCHAR(64),
            invoiceNumber VARCHAR(50) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            status VARCHAR(20) DEFAULT \'pending\',
            dueDate DATE NOT NULL,
            pdfPath VARCHAR(255),
            paidAt DATETIME,
            createdAt DATETIME,
            updatedAt DATETIME,
            UNIQUE KEY uq_invoice_number (invoiceNumber),
            INDEX idx_order_id (orderId),
            INDEX idx_user_id (userId),
            INDEX idx_status (status)
        )'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS invoice_sequence (
            id INT PRIMARY KEY DEFAULT 1,
            nextNumber INT NOT NULL DEFAULT 1
        )'
    );
    $pdo->exec('INSERT IGNORE INTO invoice_sequence (id, nextNumber) VALUES (1, 1)');
}

/**
 * Get next invoice number and increment the counter.
 */
function opd_next_invoice_number(): string
{
    opd_ensure_invoice_tables();
    $pdo = opd_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->query('SELECT nextNumber FROM invoice_sequence WHERE id = 1 FOR UPDATE');
        $row = $stmt->fetch();
        $num = (int) ($row['nextNumber'] ?? 1);
        $pdo->exec('UPDATE invoice_sequence SET nextNumber = nextNumber + 1 WHERE id = 1');
        $pdo->commit();
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $year = date('Y');
    return sprintf('INV-%s-%04d', $year, $num);
}

/**
 * Create an invoice record for an order.
 */
function opd_create_invoice(string $orderId, string $userId, float $amount, int $netDays = 30): array
{
    opd_ensure_invoice_tables();
    $pdo = opd_db();

    // Check if invoice already exists for this order
    $check = $pdo->prepare('SELECT id, invoiceNumber FROM invoices WHERE orderId = ? LIMIT 1');
    $check->execute([$orderId]);
    $existing = $check->fetch();
    if ($existing) {
        return ['id' => $existing['id'], 'invoiceNumber' => $existing['invoiceNumber'], 'existing' => true];
    }

    $id = opd_generate_id('inv');
    $invoiceNumber = opd_next_invoice_number();
    $now = gmdate('Y-m-d H:i:s');
    $dueDate = gmdate('Y-m-d', strtotime("+{$netDays} days"));

    $stmt = $pdo->prepare(
        'INSERT INTO invoices (id, orderId, userId, invoiceNumber, amount, status, dueDate, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$id, $orderId, $userId, $invoiceNumber, $amount, 'pending', $dueDate, $now, $now]);

    return ['id' => $id, 'invoiceNumber' => $invoiceNumber, 'dueDate' => $dueDate, 'existing' => false];
}

function opd_invoice_lookup_user_email(string $userId): string
{
    $userId = trim($userId);
    if ($userId === '') {
        return '';
    }

    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    $email = trim((string) ($row['email'] ?? ''));

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function opd_invoice_lookup_client_email(string $orderUserId, string $clientId): string
{
    $orderUserId = trim($orderUserId);
    $clientId = trim($clientId);
    if ($orderUserId === '' || $clientId === '') {
        return '';
    }

    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT email FROM clients WHERE id = ? AND userId = ? LIMIT 1');
    $stmt->execute([$clientId, $orderUserId]);
    $row = $stmt->fetch();
    $email = trim((string) ($row['email'] ?? ''));

    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

/**
 * Resolve the best invoice recipient for self-serve and client-billed orders.
 *
 * @return array{email: string, source: string}
 */
function opd_resolve_invoice_recipient(array $orderData): array
{
    $clientUserEmail = opd_invoice_lookup_user_email((string) ($orderData['clientUserId'] ?? ''));
    if ($clientUserEmail !== '') {
        return ['email' => $clientUserEmail, 'source' => 'clientUserId'];
    }

    $clientRecordEmail = opd_invoice_lookup_client_email(
        (string) ($orderData['userId'] ?? ''),
        (string) ($orderData['clientId'] ?? '')
    );
    if ($clientRecordEmail !== '') {
        return ['email' => $clientRecordEmail, 'source' => 'clientRecord'];
    }

    foreach (['billingEmail', 'customerEmail'] as $field) {
        $email = trim((string) ($orderData[$field] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['email' => $email, 'source' => $field];
        }
    }

    $orderUserEmail = opd_invoice_lookup_user_email((string) ($orderData['userId'] ?? ''));
    if ($orderUserEmail !== '') {
        return ['email' => $orderUserEmail, 'source' => 'orderUserId'];
    }

    return ['email' => '', 'source' => 'none'];
}

function opd_invoice_item_sku(array $item): string
{
    foreach (['sku', 'variantSku', 'productSku'] as $field) {
        $sku = trim((string) ($item[$field] ?? ''));
        if ($sku !== '') {
            return $sku;
        }
    }

    return '';
}

/**
 * Generate PDF invoice and save to disk. Returns the web-accessible path.
 */
function opd_generate_invoice_pdf(string $invoiceId): string
{
    opd_ensure_invoice_tables();
    $pdo = opd_db();

    $inv = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $inv->execute([$invoiceId]);
    $invoice = $inv->fetch();
    if (!$invoice) {
        throw new \RuntimeException('Invoice not found');
    }

    $order = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
    $order->execute([$invoice['orderId']]);
    $orderData = $order->fetch();
    if (!$orderData) {
        throw new \RuntimeException('Order not found for invoice');
    }

    $items = $pdo->prepare(
        'SELECT oi.*,
                p.sku AS productSku,
                pv.sku AS variantSku
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.productId
         LEFT JOIN product_variants pv ON pv.id = oi.variantId
         WHERE oi.orderId = ?'
    );
    $items->execute([$invoice['orderId']]);
    $orderItems = $items->fetchAll();
    $siteName = opd_site_name();
    $siteEmail = opd_site_email();

    // Build PDF
    $pdf = new \FPDF('P', 'mm', 'Letter');
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();

    // Header
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->Cell(0, 10, 'INVOICE', 0, 1, 'R');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 5, $siteName, 0, 1, 'R');
    $pdf->Cell(0, 5, '12273 County Road 1560', 0, 1, 'R');
    $pdf->Cell(0, 5, 'Ada, OK 74820', 0, 1, 'R');
    $pdf->Ln(5);

    // Invoice details
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(95, 6, 'Invoice #: ' . ($invoice['invoiceNumber'] ?? ''), 0, 0);
    $pdf->Cell(95, 6, 'Date: ' . date('M d, Y', strtotime($invoice['createdAt'])), 0, 1, 'R');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(95, 6, 'Order #: ' . ($orderData['number'] ?? ''), 0, 0);
    $pdf->Cell(95, 6, 'Due: ' . date('M d, Y', strtotime($invoice['dueDate'])), 0, 1, 'R');

    $statusLabel = ucfirst((string) ($invoice['status'] ?? 'pending'));
    $pdf->Cell(95, 6, 'Status: ' . $statusLabel, 0, 0);
    $pdf->Cell(95, 6, 'Terms: Net 30', 0, 1, 'R');
    $pdf->Ln(8);

    // Bill To
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(95, 6, 'Bill To:', 0, 0);
    $pdf->Cell(95, 6, 'Ship To:', 0, 1);
    $pdf->SetFont('Helvetica', '', 10);

    $billName = trim(($orderData['billingFirstName'] ?? '') . ' ' . ($orderData['billingLastName'] ?? ''));
    if ($billName === '') {
        $billName = $orderData['customerName'] ?? '';
    }
    $shipName = trim(($orderData['shippingFirstName'] ?? '') . ' ' . ($orderData['shippingLastName'] ?? ''));
    if ($shipName === '') {
        $shipName = $orderData['customerName'] ?? '';
    }

    $pdf->Cell(95, 5, $billName, 0, 0);
    $pdf->Cell(95, 5, $shipName, 0, 1);

    if (!empty($orderData['billingCompany'])) {
        $pdf->Cell(95, 5, $orderData['billingCompany'], 0, 0);
    } else {
        $pdf->Cell(95, 5, '', 0, 0);
    }
    if (!empty($orderData['shippingCompany'])) {
        $pdf->Cell(95, 5, $orderData['shippingCompany'], 0, 1);
    } else {
        $pdf->Cell(95, 5, '', 0, 1);
    }

    $billAddr = trim(($orderData['billingAddress1'] ?? '') . ' ' . ($orderData['billingAddress2'] ?? ''));
    $shipAddr = trim(($orderData['shippingAddress1'] ?? $orderData['address1'] ?? '') . ' ' . ($orderData['shippingAddress2'] ?? $orderData['address2'] ?? ''));
    $pdf->Cell(95, 5, $billAddr, 0, 0);
    $pdf->Cell(95, 5, $shipAddr, 0, 1);

    $billCity = trim(($orderData['billingCity'] ?? '') . ', ' . ($orderData['billingStateCode'] ?? '') . ' ' . ($orderData['billingPostcode'] ?? ''));
    $shipCity = trim(($orderData['shippingCity'] ?? $orderData['city'] ?? '') . ', ' . ($orderData['shippingStateCode'] ?? $orderData['state'] ?? '') . ' ' . ($orderData['shippingPostcode'] ?? $orderData['postal'] ?? ''));
    $pdf->Cell(95, 5, $billCity, 0, 0);
    $pdf->Cell(95, 5, $shipCity, 0, 1);

    if (!empty($orderData['billingEmail'])) {
        $pdf->Cell(95, 5, $orderData['billingEmail'], 0, 0);
    } else {
        $pdf->Cell(95, 5, $orderData['customerEmail'] ?? '', 0, 0);
    }
    $pdf->Cell(95, 5, '', 0, 1);
    $pdf->Ln(8);

    // Line items table header
    $pdf->SetFillColor(45, 45, 70);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->SetFont('Helvetica', 'B', 9);
    $pdf->Cell(90, 8, '  Product', 1, 0, 'L', true);
    $pdf->Cell(25, 8, 'SKU', 1, 0, 'C', true);
    $pdf->Cell(20, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(27, 8, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(28, 8, 'Total', 1, 1, 'R', true);

    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Helvetica', '', 9);

    $fill = false;
    foreach ($orderItems as $item) {
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
        }
        $productName = ($item['productName'] ?? $item['name'] ?? 'Item');
        $variantName = $item['variantName'] ?? '';
        $qty = (int) ($item['quantity'] ?? 1);
        $price = (float) ($item['price'] ?? 0);
        $lineTotal = $qty * $price;
        $sku = opd_invoice_item_sku($item);

        $rowH = ($variantName !== '') ? 9 : 7;
        if ($variantName !== '') {
            // Two-line cell: product name on top, variant name below
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetXY($x, $y);
            $pdf->Cell(90, 4, '  ' . substr($productName, 0, 50), 'LTR', 2, 'L', $fill);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(90, 5, '  ' . substr($variantName, 0, 50), 'LBR', 0, 'L', $fill);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetXY($x + 90, $y);
        } else {
            $pdf->Cell(90, $rowH, '  ' . substr($productName, 0, 50), 1, 0, 'L', $fill);
        }
        $pdf->Cell(25, $rowH, $sku, 1, 0, 'C', $fill);
        $pdf->Cell(20, $rowH, (string) $qty, 1, 0, 'C', $fill);
        $pdf->Cell(27, $rowH, '$' . number_format($price, 2), 1, 0, 'R', $fill);
        $pdf->Cell(28, $rowH, '$' . number_format($lineTotal, 2), 1, 1, 'R', $fill);
        $fill = !$fill;
    }

    // Totals
    $pdf->Ln(3);
    $pdf->SetFont('Helvetica', '', 10);
    $subtotal = (float) ($orderData['orderAmount'] ?? 0);
    $tax = (float) ($orderData['tax'] ?? 0);
    $shipping = (float) ($orderData['shipping'] ?? 0);
    $total = (float) ($orderData['total'] ?? $invoice['amount']);
    $refund = (float) ($orderData['refundAmount'] ?? 0);

    $rightX = 135;
    $pdf->Cell($rightX, 6, '', 0, 0);
    $pdf->Cell(27, 6, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(28, 6, '$' . number_format($subtotal, 2), 0, 1, 'R');

    if ($tax > 0) {
        $pdf->Cell($rightX, 6, '', 0, 0);
        $pdf->Cell(27, 6, 'Tax:', 0, 0, 'R');
        $pdf->Cell(28, 6, '$' . number_format($tax, 2), 0, 1, 'R');
    }

    if ($shipping > 0) {
        $pdf->Cell($rightX, 6, '', 0, 0);
        $pdf->Cell(27, 6, 'Shipping:', 0, 0, 'R');
        $pdf->Cell(28, 6, '$' . number_format($shipping, 2), 0, 1, 'R');
    }

    if ($refund > 0) {
        $pdf->Cell($rightX, 6, '', 0, 0);
        $pdf->Cell(27, 6, 'Refund:', 0, 0, 'R');
        $pdf->Cell(28, 6, '-$' . number_format($refund, 2), 0, 1, 'R');
    }

    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell($rightX, 8, '', 0, 0);
    $pdf->Cell(27, 8, 'Total Due:', 0, 0, 'R');
    $amountDue = $total - $refund;
    $pdf->Cell(28, 8, '$' . number_format($amountDue, 2), 0, 1, 'R');

    // Order notes
    $orderNotes = trim((string) ($orderData['notes'] ?? ''));
    if ($orderNotes !== '') {
        $pdf->Ln(10);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 6, 'Notes:', 0, 1);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(0, 5, $orderNotes);
    }

    // Footer note
    $pdf->Ln(15);
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, 'Payment is due within 30 days of invoice date.', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Please reference invoice number ' . $invoice['invoiceNumber'] . ' with your payment.', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Questions? Contact ' . $siteEmail, 0, 1, 'C');

    // Save to disk
    $dir = __DIR__ . '/../public/uploads/invoices';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new \RuntimeException('Cannot create invoices directory');
    }

    $filename = strtolower(str_replace(' ', '-', $invoice['invoiceNumber'])) . '.pdf';
    $filePath = $dir . '/' . $filename;
    $pdf->Output('F', $filePath);

    $webPath = '/uploads/invoices/' . $filename;

    // Update invoice record with PDF path
    $pdo->prepare('UPDATE invoices SET pdfPath = ?, updatedAt = ? WHERE id = ?')
        ->execute([$webPath, gmdate('Y-m-d H:i:s'), $invoiceId]);

    return $webPath;
}

/**
 * Email the invoice PDF to the customer.
 */
function opd_email_invoice(string $invoiceId): bool
{
    opd_ensure_invoice_tables();
    $pdo = opd_db();

    $inv = $pdo->prepare('SELECT * FROM invoices WHERE id = ?');
    $inv->execute([$invoiceId]);
    $invoice = $inv->fetch();
    if (!$invoice) {
        return false;
    }

    $order = $pdo->prepare(
        'SELECT customerEmail, billingEmail, customerName, number, userId, clientId, clientUserId
         FROM orders
         WHERE id = ?'
    );
    $order->execute([$invoice['orderId']]);
    $orderData = $order->fetch();
    if (!$orderData) {
        error_log('Invoice email skipped for invoice ' . $invoiceId . ': order not found.');
        return false;
    }

    $recipient = opd_resolve_invoice_recipient($orderData);
    $recipientEmail = (string) ($recipient['email'] ?? '');
    if ($recipientEmail === '') {
        error_log('Invoice email skipped for invoice ' . $invoiceId . ': no valid recipient.');
        return false;
    }

    try {
        // Generate PDF if not yet generated
        $pdfPath = $invoice['pdfPath'] ?? '';
        $fullPath = __DIR__ . '/../public' . $pdfPath;
        if ($pdfPath === '' || !file_exists($fullPath)) {
            $pdfPath = opd_generate_invoice_pdf($invoiceId);
            $fullPath = __DIR__ . '/../public' . $pdfPath;
        }
    } catch (\Throwable $e) {
        error_log('Invoice email preparation failed for invoice ' . $invoiceId . ': ' . $e->getMessage());
        return false;
    }

    $siteName = opd_site_name();
    $customerName = htmlspecialchars($orderData['customerName'] ?? 'Customer');
    $invoiceNumber = htmlspecialchars($invoice['invoiceNumber']);
    $dueDate = date('M d, Y', strtotime($invoice['dueDate']));
    $amount = number_format((float) $invoice['amount'], 2);

    $body = "<p>Hi {$customerName},</p>"
        . "<p>Please find your invoice <strong>{$invoiceNumber}</strong> for order <strong>{$orderData['number']}</strong>.</p>"
        . "<p><strong>Amount Due:</strong> \${$amount}<br>"
        . "<strong>Due Date:</strong> {$dueDate}</p>"
        . "<p>Payment terms: Net 30</p>"
        . "<p>The invoice PDF is attached to this email.</p>";

    require_once __DIR__ . '/email_service.php';
    if (function_exists('opd_email_shell')) {
        $body = opd_email_shell($body);
    }

    $mail = opd_create_mailer();
    if (!$mail) {
        error_log('Invoice email skipped for invoice ' . $invoiceId . ': mailer unavailable.');
        return false;
    }

    try {
        $mail->addAddress($recipientEmail);
        $mail->Subject = "Invoice {$invoiceNumber} - {$siteName}";
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);
        if (file_exists($fullPath)) {
            $mail->addAttachment($fullPath, basename($fullPath));
        }
        $mail->send();
        return true;
    } catch (\Throwable $e) {
        error_log(
            'Invoice email failed for invoice '
            . $invoiceId
            . ' to '
            . $recipientEmail
            . ' ('
            . ($recipient['source'] ?? 'unknown')
            . '): '
            . $e->getMessage()
        );
        return false;
    }
}

/**
 * Mark invoice as paid.
 */
function opd_mark_invoice_paid(string $invoiceId): bool
{
    opd_ensure_invoice_tables();
    $pdo = opd_db();
    $now = gmdate('Y-m-d H:i:s');
    $stmt = $pdo->prepare('UPDATE invoices SET status = ?, paidAt = ?, updatedAt = ? WHERE id = ? AND status != ?');
    $stmt->execute(['paid', $now, $now, $invoiceId, 'paid']);

    if ($stmt->rowCount() > 0) {
        // Also update order payment status
        $inv = $pdo->prepare('SELECT orderId FROM invoices WHERE id = ?');
        $inv->execute([$invoiceId]);
        $row = $inv->fetch();
        if ($row) {
            $pdo->prepare('UPDATE orders SET paymentStatus = ?, updatedAt = ? WHERE id = ?')
                ->execute(['Paid', $now, $row['orderId']]);
        }
        return true;
    }
    return false;
}

/**
 * Get invoice by order ID.
 */
function opd_get_invoice_by_order(string $orderId): ?array
{
    opd_ensure_invoice_tables();
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE orderId = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
