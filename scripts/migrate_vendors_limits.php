<?php

declare(strict_types=1);

require __DIR__ . '/../src/db_conn.php';

$pdo = opd_db();
$existing = $pdo->query('SHOW COLUMNS FROM vendors')->fetchAll(PDO::FETCH_COLUMN);
$existing = array_flip($existing);

$columns = [
    'purchaseLimitOrder' => 'DECIMAL(12,2) NULL',
    'purchaseLimitDay' => 'DECIMAL(12,2) NULL',
    'purchaseLimitMonth' => 'DECIMAL(12,2) NULL',
    'limitNone' => 'TINYINT(1) NULL',
    'paymentMethodId' => 'VARCHAR(64) NULL',
    'smsConsent' => 'TINYINT(1) NULL',
];

$adds = [];
$addedNames = [];
foreach ($columns as $name => $definition) {
    if (!isset($existing[$name])) {
        $adds[] = "ADD COLUMN {$name} {$definition}";
        $addedNames[] = $name;
    }
}

if (!$adds) {
    echo "[ok] vendors columns already present" . PHP_EOL;
    exit;
}

$sql = 'ALTER TABLE vendors ' . implode(', ', $adds);
$pdo->exec($sql);
echo '[ok] added columns: ' . implode(', ', $addedNames) . PHP_EOL;
