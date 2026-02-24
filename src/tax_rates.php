<?php

declare(strict_types=1);

function opd_normalize_state_code(string $state): string
{
    $state = strtoupper(trim($state));
    if ($state === 'OKLAHOMA') {
        return 'OK';
    }
    if (strlen($state) > 2 && substr($state, 0, 2) === 'OK') {
        return 'OK';
    }
    if (strlen($state) === 2) {
        return $state;
    }
    return '';
}

function opd_normalize_zip(string $zip): string
{
    $digits = preg_replace('/\D+/', '', $zip);
    if (!$digits || strlen($digits) < 5) {
        return '';
    }
    return substr($digits, 0, 5);
}

function opd_load_ok_tax_rates(): array
{
    static $rates = null;
    if ($rates !== null) {
        return $rates;
    }

    $rates = [];

    // Load CSV as baseline
    $path = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'sales_tax_rates_ok.csv';
    if (is_file($path)) {
        $handle = fopen($path, 'r');
        if ($handle !== false) {
            $header = fgetcsv($handle);
            if (is_array($header)) {
                $indexes = [];
                foreach ($header as $index => $name) {
                    $clean = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $name)));
                    $indexes[$clean] = $index;
                }

                $zipIndex = $indexes['zip/postcode'] ?? null;
                $rateIndex = $indexes['rate %'] ?? null;
                $stateIndex = $indexes['state code'] ?? null;

                if ($zipIndex !== null && $rateIndex !== null) {
                    while (($row = fgetcsv($handle)) !== false) {
                        if (!is_array($row)) {
                            continue;
                        }
                        $state = $stateIndex !== null ? (string) ($row[$stateIndex] ?? '') : 'OK';
                        if ($stateIndex !== null && opd_normalize_state_code($state) !== 'OK') {
                            continue;
                        }
                        $zip = opd_normalize_zip((string) ($row[$zipIndex] ?? ''));
                        if ($zip === '') {
                            continue;
                        }
                        $rateRaw = trim((string) ($row[$rateIndex] ?? ''));
                        if ($rateRaw === '') {
                            continue;
                        }
                        $rateRaw = str_replace('%', '', $rateRaw);
                        if (!is_numeric($rateRaw)) {
                            continue;
                        }
                        $rate = (float) $rateRaw / 100;
                        $rates[$zip] = $rate;
                    }
                }
            }
            fclose($handle);
        }
    }

    // Overlay DB-managed rates on top (DB takes precedence)
    try {
        require_once __DIR__ . '/db_conn.php';
        $pdo = opd_db();
        $stmt = $pdo->query(
            'SELECT trz.zip, trg.rate FROM tax_rate_zips trz JOIN tax_rate_groups trg ON trg.id = trz.groupId'
        );
        $rows = $stmt->fetchAll();
        foreach ($rows as $row) {
            $zip = (string) ($row['zip'] ?? '');
            $rate = (float) ($row['rate'] ?? 0);
            if ($zip !== '') {
                $rates[$zip] = $rate;
            }
        }
    } catch (\Throwable $e) {
        // DB not available — CSV rates still used
    }

    return $rates;
}

function opd_calculate_ok_sales_tax(float $subtotal, string $state, string $zip): array
{
    $subtotal = max(0.0, $subtotal);
    $stateCode = opd_normalize_state_code($state);
    $zipCode = opd_normalize_zip($zip);
    $allowState = ($stateCode === 'OK' || $stateCode === '');
    $taxable = ($zipCode !== '' && $allowState);
    $rateFound = false;
    $rate = 0.0;

    if ($taxable) {
        $rates = opd_load_ok_tax_rates();
        if (isset($rates[$zipCode])) {
            $rateFound = true;
            $rate = (float) $rates[$zipCode];
            if ($stateCode === '') {
                $stateCode = 'OK';
            }
        }
    }

    $tax = ($taxable && $rateFound) ? round($subtotal * $rate, 2) : 0.0;

    return [
        'tax' => $tax,
        'rate' => $rate,
        'ratePercent' => $rate * 100,
        'state' => $stateCode,
        'zip' => $zipCode,
        'taxable' => $taxable,
        'rateFound' => $rateFound,
    ];
}
