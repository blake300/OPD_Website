<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];

function security_read_file(string $root, string $relativePath, array &$failures): ?string
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($path)) {
        $failures[] = $relativePath . ': expected file to exist.';
        return null;
    }
    $contents = file_get_contents($path);
    if ($contents === false) {
        $failures[] = $relativePath . ': failed to read file.';
        return null;
    }
    return $contents;
}

function security_expect_contains(string $root, string $relativePath, string $needle, array &$failures): void
{
    $contents = security_read_file($root, $relativePath, $failures);
    if ($contents === null) {
        return;
    }
    if (strpos($contents, $needle) === false) {
        $failures[] = $relativePath . ': expected to find "' . $needle . '".';
    }
}

function security_expect_not_contains(string $root, string $relativePath, string $needle, array &$failures): void
{
    $contents = security_read_file($root, $relativePath, $failures);
    if ($contents === null) {
        return;
    }
    if (strpos($contents, $needle) !== false) {
        $failures[] = $relativePath . ': unexpected "' . $needle . '" remained.';
    }
}

function security_expect_missing(string $root, string $relativePath, array &$failures): void
{
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (file_exists($path)) {
        $failures[] = $relativePath . ': debug artifact should not exist.';
    }
}

security_expect_missing($root, 'public/checkout-debug.php', $failures);
security_expect_missing($root, 'public/checkout-test.php', $failures);
security_expect_not_contains($root, 'public/robots.txt', 'checkout-debug.php', $failures);
security_expect_not_contains($root, 'public/robots.txt', 'checkout-test.php', $failures);
security_expect_not_contains($root, 'public/checkout.php', 'UPDATE orders SET paymentMethod = ?, paymentStatus = ?, updatedAt = ? WHERE id = ?', $failures);
security_expect_contains($root, 'public/checkout.php', "UPDATE orders SET paymentStatus = ?, updatedAt = ? WHERE id = ?", $failures);
security_expect_contains($root, 'public/checkout.php', 'Invoice email not sent for order', $failures);

security_expect_not_contains($root, 'public/cart.php', "display_errors", $failures);
security_expect_not_contains($root, 'public/cart.php', "set_error_handler(", $failures);
security_expect_not_contains($root, 'public/cart.php', "getTraceAsString()", $failures);
security_expect_not_contains($root, 'public/cart.php', "Cart Debug Error", $failures);

security_expect_contains($root, 'src/store.php', 'function site_storefront_visibility_filter', $failures);
security_expect_contains($root, 'src/store.php', 'function site_public_product_payload', $failures);
security_expect_contains($root, 'src/store.php', 'function site_get_public_product', $failures);
security_expect_not_contains($root, 'src/store.php', "\$_SERVER['HTTP_HOST']", $failures);
security_expect_contains($root, 'src/invoice_service.php', 'function opd_resolve_invoice_recipient', $failures);
security_expect_contains($root, 'src/invoice_service.php', 'clientUserId', $failures);

security_expect_contains($root, 'api/category_products.php', 'site_public_product_payload($item)', $failures);
security_expect_not_contains($root, 'api/category_products.php', 'array_merge($item', $failures);
security_expect_contains($root, 'api/invoices.php', 'clientUserId', $failures);

security_expect_contains($root, 'public/category.php', 'site_get_public_product(', $failures);
security_expect_contains($root, 'public/product.php', 'site_get_public_product(', $failures);
security_expect_contains($root, 'public/product.php', 'site_is_storefront_sellable_product(', $failures);

security_expect_contains($root, 'src/auth.php', 'opd_login_rate_limit_identifiers(', $failures);
security_expect_contains($root, 'src/auth.php', 'opd_dummy_password_hash()', $failures);
security_expect_contains($root, 'src/site_auth.php', 'opd_login_rate_limit_identifiers(', $failures);
security_expect_contains($root, 'src/site_auth.php', 'opd_dummy_password_hash()', $failures);
security_expect_not_contains($root, 'src/site_auth.php', "\$_SERVER['HTTP_HOST']", $failures);

security_expect_contains($root, 'src/seo.php', 'return opd_site_base_url();', $failures);
security_expect_not_contains($root, 'src/seo.php', 'HTTP_HOST', $failures);

security_expect_contains($root, 'public/assets/js/admin.js', "escapeHtml(p.name || '')", $failures);
security_expect_contains($root, 'public/assets/js/admin.js', "escapeHtml(p.sku || '')", $failures);

if ($failures) {
    fwrite(STDERR, "Security regression checks failed:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "Security regression checks passed.\n");
