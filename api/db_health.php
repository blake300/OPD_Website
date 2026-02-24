<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/db_conn.php';
require_once __DIR__ . '/../src/api_helpers.php';

opd_require_role(['admin']);

$config = require __DIR__ . '/../config/config.php';
$dbName = $config['db_name'] ?? '';
if ($dbName === '') {
    opd_json_response(['error' => 'Missing database name'], 500);
}

function opd_schema_expected(string $schemaPath): array
{
    if (!file_exists($schemaPath)) {
        return [];
    }
    $schemaText = file_get_contents($schemaPath);
    if ($schemaText === false) {
        return [];
    }

    $blocks = [];
    $current = [];
    $inCreate = false;
    $endPattern = '/^\\)\\s*ENGINE\\b.*;\\s*$/i';
    $tablePattern = '/^CREATE TABLE(?: IF NOT EXISTS)?\\s+`?([\\w_]+)`?\\s*\\(/i';

    foreach (preg_split('/\\R/', $schemaText) as $line) {
        $trimmed = trim($line);
        if (!$inCreate && stripos($trimmed, 'CREATE TABLE') === 0) {
            $inCreate = true;
        }
        if ($inCreate) {
            $current[] = rtrim($line);
            if ($trimmed === ');' || preg_match($endPattern, $trimmed)) {
                $blocks[] = implode(PHP_EOL, $current);
                $current = [];
                $inCreate = false;
            }
        }
    }

    $expected = [];
    foreach ($blocks as $block) {
        $lines = preg_split('/\\R/', $block);
        if (!$lines) {
            continue;
        }
        if (!preg_match($tablePattern, trim($lines[0]), $match)) {
            continue;
        }
        $table = $match[1];
        $columns = [];
        foreach (array_slice($lines, 1) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed === ');' || str_starts_with($trimmed, ')')) {
                continue;
            }
            $trimmed = rtrim($trimmed, ',');
            $upper = strtoupper($trimmed);
            if (
                str_starts_with($upper, 'PRIMARY KEY') ||
                str_starts_with($upper, 'INDEX ') ||
                str_starts_with($upper, 'KEY ') ||
                str_starts_with($upper, 'UNIQUE ') ||
                str_starts_with($upper, 'CONSTRAINT ') ||
                str_starts_with($upper, 'FULLTEXT ') ||
                str_starts_with($upper, 'FOREIGN KEY')
            ) {
                continue;
            }
            $parts = preg_split('/\\s+/', $trimmed, 2);
            if (!$parts || count($parts) < 2) {
                continue;
            }
            $column = trim($parts[0], '`');
            if ($column !== '') {
                $columns[] = $column;
            }
        }
        if ($columns) {
            $expected[$table] = $columns;
        }
    }

    return $expected;
}

$schemaPath = __DIR__ . '/../database/schema.sql';
$expected = opd_schema_expected($schemaPath);
if (!$expected) {
    opd_json_response(['error' => 'Unable to read schema.sql'], 500);
}

try {
    $pdo = opd_db();
    $stmt = $pdo->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.columns WHERE table_schema = ?'
    );
    $stmt->execute([$dbName]);
    $rows = $stmt->fetchAll();

    $available = [];
    foreach ($rows as $row) {
        $table = (string) ($row['TABLE_NAME'] ?? '');
        $column = (string) ($row['COLUMN_NAME'] ?? '');
        if ($table === '' || $column === '') {
            continue;
        }
        if (!isset($available[$table])) {
            $available[$table] = [];
        }
        $available[$table][$column] = true;
    }

    $missingTables = [];
    $missingColumns = [];

    foreach ($expected as $table => $columns) {
        if (!isset($available[$table])) {
            $missingTables[] = $table;
            continue;
        }
        $missing = [];
        foreach ($columns as $column) {
            if (!isset($available[$table][$column])) {
                $missing[] = $column;
            }
        }
        if ($missing) {
            $missingColumns[$table] = $missing;
        }
    }

    $ok = empty($missingTables) && empty($missingColumns);
    opd_json_response([
        'ok' => $ok,
        'missingTables' => $missingTables,
        'missingColumns' => $missingColumns,
        'checkedAt' => gmdate('Y-m-d H:i:s'),
    ]);
} catch (Throwable $e) {
    opd_json_response([
        'error' => 'Failed to inspect schema',
        'detail' => $e->getMessage(),
    ], 500);
}
