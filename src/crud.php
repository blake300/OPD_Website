<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/api_helpers.php';
require_once __DIR__ . '/auth.php';

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

    if ($method === 'GET') {
        opd_require_role($readRoles);
        $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY updatedAt DESC");
        $items = $stmt->fetchAll();
        opd_json_response(['items' => $items, 'total' => count($items)]);
    }

    if ($method === 'POST') {
        opd_require_role($writeRoles);
        opd_require_csrf();
        $payload = opd_read_json();
        opd_validate_payload($payload, $validators);
        $id = $idPrefix . '-' . random_int(1000, 99999);
        $now = gmdate('Y-m-d H:i:s');

        $insertColumns = array_merge(['id'], $columns, ['updatedAt']);
        if ($hasCreatedAt) {
            $insertColumns[] = 'createdAt';
        }
        $placeholders = implode(',', array_fill(0, count($insertColumns), '?'));
        $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(',', $insertColumns), $placeholders);

        $values = [$id];
        foreach ($columns as $column) {
            $values[] = $payload[$column] ?? null;
        }
        $values[] = $now;
        if ($hasCreatedAt) {
            $values[] = $now;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        $row = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
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

        $setParts = [];
        $values = [];
        foreach ($columns as $column) {
            $setParts[] = "{$column} = ?";
            $values[] = $payload[$column] ?? null;
        }
        $setParts[] = "updatedAt = ?";
        $values[] = $now;
        $values[] = $id;

        $sql = sprintf('UPDATE %s SET %s WHERE id = ?', $table, implode(', ', $setParts));
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        if ($stmt->rowCount() === 0) {
            opd_json_response(['error' => 'Not found'], 404);
        }
        $row = $pdo->prepare("SELECT * FROM {$table} WHERE id = ?");
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
        $stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
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
