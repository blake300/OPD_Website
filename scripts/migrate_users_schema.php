<?php

declare(strict_types=1);

require __DIR__ . '/../src/db_conn.php';

$pdo = opd_db();
$existing = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
$existing = array_flip($existing);

$columns = [
    'companyName' => 'VARCHAR(255) NULL',
    'cellPhone' => 'VARCHAR(50) NULL',
    'address' => 'VARCHAR(255) NULL',
    'city' => 'VARCHAR(120) NULL',
    'state' => 'VARCHAR(120) NULL',
    'zip' => 'VARCHAR(20) NULL',
    'passwordHash' => 'VARCHAR(255) NULL',
    'stripeCustomerId' => 'VARCHAR(255) NULL',
    'role' => 'VARCHAR(50) NULL',
    'status' => 'VARCHAR(50) NULL',
    'lastLogin' => 'DATETIME NULL',
    'updatedAt' => 'DATETIME NULL',
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
    echo "[ok] users columns already present" . PHP_EOL;
    exit;
}

$sql = 'ALTER TABLE users ' . implode(', ', $adds);
$pdo->exec($sql);
echo '[ok] added columns: ' . implode(', ', $addedNames) . PHP_EOL;
