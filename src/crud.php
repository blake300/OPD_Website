<?php

declare(strict_types=1);

require_once __DIR__ . '/db_conn.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/auth.php';

function opd_table_columns(string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    $pdo = opd_db();
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ?'
    );
    $stmt->execute([$table]);
    $columns = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
        if (is_string($name) && $name !== '') {
            $columns[$name] = true;
        }
    }
    $cache[$table] = $columns;
    return $columns;
}

function opd_handle_crud(
    string $table,
    string $idPrefix,
    array $columns,
    bool $hasCreatedAt = false,
    array $readRoles = ['admin', 'manager'],
    array $writeRoles = ['admin'],
    array $validators = []
): void
{
    $pdo = opd_db();
    $method = $_SERVER['REQUEST_METHOD'];
    $availableColumns = opd_table_columns($table);
    if ($availableColumns) {
        $columns = array_values(array_filter($columns, fn($column) => isset($availableColumns[$column])));
    }
    $hasCreatedAt = $hasCreatedAt && isset($availableColumns['createdAt']);
    $hasUpdatedAt = isset($availableColumns['updatedAt']);

    if ($method === 'GET') {
        opd_require_role($readRoles);
        $orderBy = '';
        if ($hasUpdatedAt) {
            $orderBy = ' ORDER BY updatedAt DESC';
        } elseif (isset($availableColumns['id'])) {
            $orderBy = ' ORDER BY id DESC';
        }
        // Only select specified columns plus id to avoid exposing sensitive fields
        $selectColumns = array_merge(['id'], $columns);
        if ($hasUpdatedAt) {
            $selectColumns[] = 'updatedAt';
        }
        if ($hasCreatedAt) {
            $selectColumns[] = 'createdAt';
        }
        $selectColumns = array_unique($selectColumns);
        $selectList = implode(', ', array_map(fn($c) => '`' . $c . '`', $selectColumns));
        $stmt = $pdo->query("SELECT {$selectList} FROM `{$table}`{$orderBy}");
        $items = $stmt->fetchAll();
        opd_json_response(['items' => $items, 'total' => count($items)]);
    }

    if ($method === 'POST') {
        opd_require_role($writeRoles);
        opd_require_csrf();
        $payload = opd_read_json();
        opd_validate_payload($payload, $validators);
        $id = $idPrefix . '-' . bin2hex(random_bytes(16));
        $now = gmdate('Y-m-d H:i:s');

        $insertColumns = array_merge(['id'], $columns);
        if ($hasUpdatedAt) {
            $insertColumns[] = 'updatedAt';
        }
        if ($hasCreatedAt) {
            $insertColumns[] = 'createdAt';
        }
        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
        $escapedCols = implode(',', array_map(fn($c) => '`' . $c . '`', $insertColumns));
        $sql = sprintf('INSERT INTO `%s` (%s) VALUES (%s)', $table, $escapedCols, $placeholders);

        $values = [$id];
        foreach ($columns as $column) {
            $values[] = $payload[$column] ?? null;
        }
        if ($hasUpdatedAt) {
            $values[] = $now;
        }
        if ($hasCreatedAt) {
            $values[] = $now;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $row = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $row->execute([$id]);
        opd_json_response($row->fetch() ?: [], 201);
    }

    if ($method === 'PUT') {
        opd_require_role($writeRoles);
        opd_require_csrf();
        $payload = opd_read_json();
        opd_validate_payload($payload, $validators);
        $id = $_GET['id'] ?? ($payload['id'] ?? '');
        if ($id === '') {
            opd_json_response(['error' => 'Missing id'], 400);
        }
        $now = gmdate('Y-m-d H:i:s');

        // Only update fields that are explicitly provided to avoid nulling unknown/new columns
        $setParts = [];
        $values = [];
        foreach ($columns as $column) {
            if (array_key_exists($column, $payload)) {
                $setParts[] = "`{$column}` = ?";
                $values[] = $payload[$column];
            }
        }
        if (!$setParts) {
            opd_json_response(['error' => 'No writable columns'], 400);
        }
        if ($hasUpdatedAt) {
            $setParts[] = "updatedAt = ?";
            $values[] = $now;
        }
        $values[] = $id;

        $sql = sprintf('UPDATE `%s` SET %s WHERE id = ?', $table, implode(', ', $setParts));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        if ($stmt->rowCount() === 0) {
            opd_json_response(['error' => 'Not found'], 404);
        }
        $row = $pdo->prepare("SELECT * FROM `{$table}` WHERE id = ?");
        $row->execute([$id]);
        opd_json_response($row->fetch() ?: []);
    }

    if ($method === 'DELETE') {
        opd_require_role($writeRoles);
        opd_require_csrf();
        $id = $_GET['id'] ?? '';
        if ($id === '') {
            opd_json_response(['error' => 'Missing id'], 400);
        }
        $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE id = ?");
        $stmt->execute([$id]);
        opd_json_response(['ok' => $stmt->rowCount() > 0]);
    }

    opd_json_response(['error' => 'Method Not Allowed'], 405);
}

function opd_validate_payload(array $payload, array $validators): void
{
    if (!$validators) {
        return;
    }
    foreach ($validators as $field => $rules) {
        $value = $payload[$field] ?? null;
        if (!empty($rules['required']) && ($value === null || $value === '')) {
            opd_json_response(['error' => sprintf('Field "%s" is required', $field)], 400);
        }
        if (!empty($rules['allowed']) && $value !== null && $value !== '') {
            if (!in_array($value, $rules['allowed'], true)) {
                opd_json_response(['error' => sprintf('Invalid value for "%s"', $field)], 400);
            }
        }
    }
}
