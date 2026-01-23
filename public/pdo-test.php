<?php

header('Content-Type: text/plain');

echo "PHP: " . PHP_VERSION . PHP_EOL;
echo "PDO drivers: " . implode(', ', PDO::getAvailableDrivers()) . PHP_EOL;
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=opd_admin', 'root', '');
    echo "PDO connection: OK" . PHP_EOL;
} catch (Throwable $e) {
    echo "PDO connection error: " . $e->getMessage() . PHP_EOL;
}
