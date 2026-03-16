<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schemaPath = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
$outputPath = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema_sync.sql';
$exactOutputPath = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema_sync_exact.sql';
$preserveOutputPath = $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema_sync_preserve.sql';

if (!file_exists($schemaPath)) {
    fwrite(STDERR, "[error] schema.sql not found at {$schemaPath}" . PHP_EOL);
    exit(1);
}

$schemaText = file_get_contents($schemaPath);
if ($schemaText === false) {
    fwrite(STDERR, '[error] failed to read schema.sql' . PHP_EOL);
    exit(1);
}

$blocks = [];
$current = [];
$nonTableLines = [];
$inCreate = false;
$endPattern = '/^\\)\\s*ENGINE\\b.*;\\s*$/i';
$tablePattern = '/^CREATE TABLE(?: IF NOT EXISTS)?\\s+`?([\\w_]+)`?\\s*\\(/i';

$extractColumns = static function (array $lines): array {
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
        $columns[] = trim($parts[0], '`');
    }

    return $columns;
};

$stripTrailingSemicolon = static function (string $value): string {
    return preg_replace('/;\\s*$/', '', rtrim($value));
};

$escapeSqlLiteral = static function (string $value): string {
    return str_replace("'", "''", $value);
};

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
    } else {
        $nonTableLines[] = rtrim($line);
    }
}

$out = [];
$out[] = '-- OPD schema sync (idempotent)';
$out[] = '-- Re-run safely to create missing tables/columns.';
$out[] = 'SET @db := DATABASE();';
$out[] = '';

if ($blocks) {
    $out[] = '-- Base tables';
    foreach ($blocks as $block) {
        $out[] = $block;
    }
    $out[] = '';
}

$out[] = '-- Ensure missing columns exist';
$tables = [];
$tableColumns = [];

foreach ($blocks as $block) {
    $lines = preg_split('/\\R/', $block);
    if (!$lines) {
        continue;
    }
    if (preg_match($tablePattern, trim($lines[0]), $match)) {
        $table = $match[1];
        $tables[] = $table;
        $tableColumns[$table] = $extractColumns($lines);
    }
}

foreach ($blocks as $block) {
    $lines = preg_split('/\\R/', $block);
    if (!$lines) {
        continue;
    }
    if (!preg_match($tablePattern, trim($lines[0]), $match)) {
        continue;
    }
    $table = $match[1];

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
        $definition = $parts[1];
        $definition = preg_replace('/\\bPRIMARY\\s+KEY\\b/i', '', $definition);
        $definition = trim(preg_replace('/\\s+/', ' ', (string) $definition));
        if ($definition === '') {
            continue;
        }
        $definitionSql = $escapeSqlLiteral($definition);

        $out[] = sprintf(
            "SET @col_exists := (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = '%s' AND column_name = '%s');",
            $table,
            $column
        );
        $out[] = sprintf(
            "SET @sql := IF(@col_exists = 0, 'ALTER TABLE `%s` ADD COLUMN `%s` %s', 'SELECT 1');",
            $table,
            $column,
            $definitionSql
        );
        $out[] = 'PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;';
        $out[] = '';
    }
}

$outExact = [];
$outExact[] = '-- OPD schema sync (exact, destructive)';
$outExact[] = '-- Drops and recreates tables to match schema.sql exactly.';
$outExact[] = 'SET FOREIGN_KEY_CHECKS=0;';
$outExact[] = '';

foreach ($tables as $table) {
    $outExact[] = sprintf('DROP TABLE IF EXISTS `%s`;', $table);
}

if ($tables) {
    $outExact[] = '';
}

foreach ($blocks as $block) {
    $blockExact = preg_replace('/^(\\s*)CREATE TABLE IF NOT EXISTS/i', '$1CREATE TABLE', $block);
    $outExact[] = $blockExact;
    $outExact[] = '';
}

$outExact[] = 'SET FOREIGN_KEY_CHECKS=1;';

$outPreserve = [];
$outPreserve[] = '-- OPD schema sync (preserve data)';
$outPreserve[] = '-- Rebuilds tables to match schema.sql and keeps old tables as backups.';
$outPreserve[] = 'SET @db := DATABASE();';
$outPreserve[] = 'SET FOREIGN_KEY_CHECKS=0;';
$outPreserve[] = 'SET SESSION group_concat_max_len = 10240;';
$outPreserve[] = '';

$stamp = date('Ymd_His');

foreach ($blocks as $block) {
    $lines = preg_split('/\\R/', $block);
    if (!$lines) {
        continue;
    }
    if (!preg_match($tablePattern, trim($lines[0]), $match)) {
        continue;
    }

    $table = $match[1];
    $columns = $tableColumns[$table] ?? [];
    $newTable = "__schema_sync_new_{$table}_{$stamp}";
    $oldTable = "__schema_sync_old_{$table}_{$stamp}";

    $createDirect = $stripTrailingSemicolon($block);
    $createNew = preg_replace(
        '/^(\\s*)CREATE TABLE(?: IF NOT EXISTS)?\\s+`?' . preg_quote($table, '/') . '`?/i',
        '$1CREATE TABLE `' . $newTable . '`',
        $block
    );
    $createNew = $stripTrailingSemicolon($createNew);

    $outPreserve[] = sprintf('-- %s', $table);
    $outPreserve[] = sprintf(
        "SET @table_exists := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = @db AND table_name = '%s');",
        $table
    );
    $outPreserve[] = sprintf(
        "SET @sql := IF(@table_exists = 0, '%s', 'SELECT 1');",
        $escapeSqlLiteral($createDirect)
    );
    $outPreserve[] = 'PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;';
    $outPreserve[] = sprintf(
        "SET @sql := IF(@table_exists = 1, 'DROP TABLE IF EXISTS `%s`', 'SELECT 1');",
        $newTable
    );
    $outPreserve[] = 'PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;';
    $outPreserve[] = sprintf(
        "SET @sql := IF(@table_exists = 1, '%s', 'SELECT 1');",
        $escapeSqlLiteral($createNew)
    );
    $outPreserve[] = 'PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;';

    if ($columns) {
        $quotedColumns = array_map(
            static fn (string $column): string => "'" . $escapeSqlLiteral($column) . "'",
            $columns
        );
        $columnsList = implode(',', $quotedColumns);

        $outPreserve[] = 'SET @cols := NULL;';
        $outPreserve[] = sprintf(
            "SELECT GROUP_CONCAT(CONCAT('`', column_name, '`') ORDER BY FIELD(column_name, %s)) INTO @cols FROM information_schema.columns WHERE table_schema = @db AND table_name = '%s' AND column_name IN (%s);",
            $columnsList,
            $table,
            $columnsList
        );
        $outPreserve[] = sprintf(
            "SET @sql := IF(@table_exists = 1 AND @cols IS NOT NULL AND @cols <> '', CONCAT('INSERT INTO `%s` (', @cols, ') SELECT ', @cols, ' FROM `%s`'), 'SELECT 1');",
            $newTable,
            $table
        );
        $outPreserve[] = 'PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;';
    }

    $outPreserve[] = sprintf(
        "SET @sql := IF(@table_exists = 1, 'RENAME TABLE `%s` TO `%s`, `%s` TO `%s`', 'SELECT 1');",
        $table,
        $oldTable,
        $newTable,
        $table
    );
    $outPreserve[] = 'PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;';
    $outPreserve[] = '';
}

$outPreserve[] = 'SET FOREIGN_KEY_CHECKS=1;';

$nonTableText = trim(implode(PHP_EOL, $nonTableLines));
if ($nonTableText !== '') {
    $outPreserve[] = '';
    $outPreserve[] = '-- Non-table objects (from schema.sql)';
    $outPreserve[] = $nonTableText;
}

$output = implode(PHP_EOL, $out);
$output = rtrim($output) . PHP_EOL;

if (file_put_contents($outputPath, $output) === false) {
    fwrite(STDERR, "[error] failed to write {$outputPath}" . PHP_EOL);
    exit(1);
}

$exactOutput = implode(PHP_EOL, $outExact);
$exactOutput = rtrim($exactOutput) . PHP_EOL;

if (file_put_contents($exactOutputPath, $exactOutput) === false) {
    fwrite(STDERR, "[error] failed to write {$exactOutputPath}" . PHP_EOL);
    exit(1);
}

$preserveOutput = implode(PHP_EOL, $outPreserve);
$preserveOutput = rtrim($preserveOutput) . PHP_EOL;

if (file_put_contents($preserveOutputPath, $preserveOutput) === false) {
    fwrite(STDERR, "[error] failed to write {$preserveOutputPath}" . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "[ok] wrote {$outputPath}" . PHP_EOL);
fwrite(STDOUT, "[ok] wrote {$exactOutputPath}" . PHP_EOL);
fwrite(STDOUT, "[ok] wrote {$preserveOutputPath}" . PHP_EOL);
