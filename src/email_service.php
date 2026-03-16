<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function opd_create_mailer(): ?PHPMailer
{
    require_once __DIR__ . '/api_helpers.php';
    $host = opd_config('email_smtp_host');
    $port = (int) opd_config('email_smtp_port', '465');
    $user = opd_config('email_username');
    $pass = opd_config('email_password');

    if ($host === '' || $user === '' || $pass === '') {
        error_log('Email not configured — missing SMTP credentials.');
        return null;
    }

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->SMTPAuth = true;
    $mail->Username = $user;
    $mail->Password = $pass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = $port;
    $mail->setFrom($user, opd_site_name());
    $mail->isHTML(true);
    $mail->CharSet = 'UTF-8';

    return $mail;
}

/**
 * Send a generic email. Returns true on success, false on failure.
 */
function opd_send_email(string $to, string $subject, string $htmlBody, string $plainBody = ''): bool
{
    $mail = opd_create_mailer();
    if (!$mail) {
        return false;
    }

    try {
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $plainBody ?: strip_tags($htmlBody);
        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('Email send failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Wrap body content in the standard OPD email shell.
 */
function opd_email_shell(string $bodyHtml): string
{
    require_once __DIR__ . '/api_helpers.php';
    $name = htmlspecialchars(opd_site_name(), ENT_QUOTES);
    $email = htmlspecialchars(opd_site_email(), ENT_QUOTES);
    return <<<HTML
    <div style="max-width:600px;margin:0 auto;font-family:Arial,sans-serif;color:#333;">
        <div style="background:#1a1a2e;padding:20px;text-align:center;">
            <h1 style="color:#fff;margin:0;font-size:24px;">{$name}</h1>
        </div>
        <div style="padding:24px;">
            {$bodyHtml}
        </div>
        <div style="background:#f5f5f5;padding:16px;text-align:center;font-size:12px;color:#888;">
            {$name} &bull; {$email}
        </div>
    </div>
    HTML;
}

/**
 * Send order confirmation to the customer.
 * Uses the admin-configured template for the fulfillment type (shipping/delivery/pickup).
 */
function opd_send_order_confirmation(array $order, array $items): bool
{
    $to = $order['customerEmail'] ?? '';
    if ($to === '') {
        return false;
    }

    require_once __DIR__ . '/store.php';

    $shippingMethod = strtolower(trim((string) ($order['shippingMethod'] ?? 'pickup')));
    if (in_array($shippingMethod, ['standard', 'express'], true)) {
        $templateKey = 'email_order_shipping';
    } elseif (in_array($shippingMethod, ['same_day', 'delivery'], true)) {
        $templateKey = 'email_order_delivery';
    } else {
        $templateKey = 'email_order_pickup';
    }
    $adminTemplate = site_get_setting_value($templateKey);

    $orderNumber = $order['number'] ?? $order['orderNumber'] ?? '';
    $total = number_format((float) ($order['total'] ?? 0), 2);
    $tax = number_format((float) ($order['tax'] ?? 0), 2);
    $shipping = number_format((float) ($order['shipping'] ?? 0), 2);
    $subtotal = number_format((float) ($order['orderAmount'] ?? 0), 2);
    $name = htmlspecialchars($order['customerName'] ?? 'Customer', ENT_QUOTES, 'UTF-8');

    $itemRows = '';
    foreach ($items as $item) {
        $itemName = htmlspecialchars($item['name'] ?? $item['productName'] ?? '', ENT_QUOTES, 'UTF-8');
        $qty = (int) ($item['quantity'] ?? 1);
        $price = number_format((float) ($item['price'] ?? 0), 2);
        $lineTotal = number_format($qty * (float) ($item['price'] ?? 0), 2);
        $itemRows .= "<tr>
            <td style='padding:8px 12px;border-bottom:1px solid #eee;'>{$itemName}</td>
            <td style='padding:8px 12px;border-bottom:1px solid #eee;text-align:center;'>{$qty}</td>
            <td style='padding:8px 12px;border-bottom:1px solid #eee;text-align:right;'>\${$price}</td>
            <td style='padding:8px 12px;border-bottom:1px solid #eee;text-align:right;'>\${$lineTotal}</td>
        </tr>";
    }

    $customMessage = '';
    if ($adminTemplate !== null && trim($adminTemplate) !== '') {
        $customMessage = '<p style="margin:16px 0;padding:12px;background:#f0f7ff;border-left:4px solid #1a1a2e;">'
            . nl2br(htmlspecialchars(trim($adminTemplate), ENT_QUOTES, 'UTF-8'))
            . '</p>';
    }

    $body = <<<HTML
        <h2 style="color:#1a1a2e;">Order Confirmation</h2>
        <p>Hi {$name},</p>
        <p>Thank you for your order! Here are your order details:</p>
        <p><strong>Order Number:</strong> {$orderNumber}</p>
        {$customMessage}
        <table style="width:100%;border-collapse:collapse;margin:16px 0;">
            <thead>
                <tr style="background:#f5f5f5;">
                    <th style="padding:8px 12px;text-align:left;">Item</th>
                    <th style="padding:8px 12px;text-align:center;">Qty</th>
                    <th style="padding:8px 12px;text-align:right;">Price</th>
                    <th style="padding:8px 12px;text-align:right;">Total</th>
                </tr>
            </thead>
            <tbody>
                {$itemRows}
            </tbody>
        </table>
        <table style="width:100%;margin-top:12px;">
            <tr><td style="padding:4px 12px;text-align:right;">Subtotal:</td><td style="padding:4px 12px;text-align:right;width:100px;">\${$subtotal}</td></tr>
            <tr><td style="padding:4px 12px;text-align:right;">Tax:</td><td style="padding:4px 12px;text-align:right;">\${$tax}</td></tr>
            <tr><td style="padding:4px 12px;text-align:right;">Shipping:</td><td style="padding:4px 12px;text-align:right;">\${$shipping}</td></tr>
            <tr><td style="padding:4px 12px;text-align:right;font-weight:bold;font-size:16px;">Total:</td><td style="padding:4px 12px;text-align:right;font-weight:bold;font-size:16px;">\${$total}</td></tr>
        </table>
        <p style="margin-top:24px;">If you have any questions about your order, please reply to this email or contact us.</p>
    HTML;

    return opd_send_email($to, "Order Confirmation - {$orderNumber}", opd_email_shell($body));
}

/**
 * Notify admin about a new order.
 */
function opd_send_admin_order_notification(array $order, array $items): bool
{
    $adminEmail = $_ENV['OPD_ADMIN_EMAIL'] ?? '';
    if ($adminEmail === '') {
        return false;
    }

    $orderNumber = $order['number'] ?? $order['orderNumber'] ?? '';
    $total = number_format((float) ($order['total'] ?? 0), 2);
    $name = htmlspecialchars($order['customerName'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($order['customerEmail'] ?? '', ENT_QUOTES, 'UTF-8');
    $itemCount = count($items);

    $itemList = '';
    foreach ($items as $item) {
        $itemName = htmlspecialchars($item['name'] ?? $item['productName'] ?? '', ENT_QUOTES, 'UTF-8');
        $qty = (int) ($item['quantity'] ?? 1);
        $itemList .= "<li>{$itemName} x {$qty}</li>";
    }

    $body = <<<HTML
        <h2 style="color:#1a1a2e;">New Order Received</h2>
        <p><strong>Order:</strong> {$orderNumber}</p>
        <p><strong>Customer:</strong> {$name} ({$email})</p>
        <p><strong>Total:</strong> \${$total}</p>
        <p><strong>Items ({$itemCount}):</strong></p>
        <ul>{$itemList}</ul>
    HTML;

    return opd_send_email($adminEmail, "New Order: {$orderNumber} - \${$total}", opd_email_shell($body));
}

/**
 * Send order status update email to the customer.
 */
function opd_send_order_status_email(array $order, string $newStatus): bool
{
    $to = $order['customerEmail'] ?? '';
    if ($to === '') {
        return false;
    }

    $orderNumber = $order['number'] ?? '';
    $name = htmlspecialchars($order['customerName'] ?? 'Customer', ENT_QUOTES, 'UTF-8');
    $statusDisplay = htmlspecialchars(ucwords($newStatus), ENT_QUOTES, 'UTF-8');

    $body = <<<HTML
        <h2 style="color:#1a1a2e;">Order Update</h2>
        <p>Hi {$name},</p>
        <p>Your order <strong>{$orderNumber}</strong> has been updated:</p>
        <p style="font-size:18px;padding:12px;background:#f0f7ff;border-left:4px solid #1a1a2e;margin:16px 0;">
            Status: <strong>{$statusDisplay}</strong>
        </p>
        <p>If you have any questions, please reply to this email or contact us.</p>
    HTML;

    return opd_send_email($to, "Order {$orderNumber} - {$statusDisplay}", opd_email_shell($body));
}
