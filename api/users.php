<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/crud.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = opd_db();
$availableColumns = opd_table_columns('users');

$writeColumns = [
    'name',
    'lastName',
    'email',
    'companyName',
    'cellPhone',
    'address',
    'address2',
    'city',
    'state',
    'zip',
    'shippingFirstName',
    'shippingLastName',
    'shippingCompany',
    'shippingAddress1',
    'shippingAddress2',
    'shippingCity',
    'shippingState',
    'shippingPostcode',
    'shippingPhone',
    'bioNotes',
    'role',
    'status',
];
$writeColumns = array_values(array_filter(
    $writeColumns,
    fn(string $column): bool => isset($availableColumns[$column])
));

function users_normalize_value($value)
{
    if ($value === null) {
        return null;
    }
    if (is_string($value)) {
        $value = trim($value);
        return $value === '' ? null : $value;
    }
    return $value;
}

function users_split_name(string $fullName): array
{
    $fullName = trim($fullName);
    if ($fullName === '') {
        return ['', ''];
    }
    $parts = preg_split('/\s+/', $fullName, 2) ?: [];
    $first = $parts[0] ?? '';
    $last = $parts[1] ?? '';
    return [$first, $last];
}

function users_build_write_data(array $payload, array $writeColumns, array $availableColumns, bool $isUpdate): array
{
    $data = [];
    $firstName = trim((string) ($payload['firstName'] ?? ''));
    $lastName = trim((string) ($payload['lastName'] ?? ''));
    $fullName = trim($firstName . ' ' . $lastName);

    if (in_array('name', $writeColumns, true)) {
        if ($fullName !== '') {
            $data['name'] = $fullName;
        } elseif (array_key_exists('name', $payload)) {
            $candidate = users_normalize_value($payload['name']);
            if ($candidate !== null || !$isUpdate) {
                $data['name'] = $candidate;
            }
        } elseif (!$isUpdate) {
            $data['name'] = null;
        }
    }

    foreach ($writeColumns as $column) {
        if ($column === 'name') {
            continue;
        }
        $data[$column] = users_normalize_value($payload[$column] ?? null);
    }

    $password = trim((string) ($payload['password'] ?? ''));
    if ($password !== '' && isset($availableColumns['passwordHash'])) {
        $data['passwordHash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    return $data;
}

if ($method === 'GET') {
    opd_require_role(['admin', 'manager']);
    $orderBy = isset($availableColumns['updatedAt']) ? ' ORDER BY updatedAt DESC' : '';
    $stmt = $pdo->query("SELECT * FROM users{$orderBy}");
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        unset($item['passwordHash']);
        $fullName = (string) ($item['name'] ?? '');
        [$firstName, $lastFromName] = users_split_name($fullName);
        $storedLast = trim((string) ($item['lastName'] ?? ''));
        $item['firstName'] = $firstName;
        $item['lastName'] = $storedLast !== '' ? $storedLast : $lastFromName;
        $shippingFirst = trim((string) ($item['shippingFirstName'] ?? ''));
        if ($shippingFirst === '') {
            $item['shippingFirstName'] = $firstName;
        }
        $shippingLast = trim((string) ($item['shippingLastName'] ?? ''));
        if ($shippingLast === '') {
            $item['shippingLastName'] = $item['lastName'] ?? '';
        }
        if (trim((string) ($item['shippingCompany'] ?? '')) === '') {
            $item['shippingCompany'] = $item['companyName'] ?? '';
        }
        if (trim((string) ($item['shippingPhone'] ?? '')) === '') {
            $item['shippingPhone'] = $item['cellPhone'] ?? '';
        }
        if (trim((string) ($item['shippingAddress1'] ?? '')) === '') {
            $item['shippingAddress1'] = $item['address'] ?? '';
        }
        if (trim((string) ($item['shippingAddress2'] ?? '')) === '') {
            $item['shippingAddress2'] = $item['address2'] ?? '';
        }
        if (trim((string) ($item['shippingCity'] ?? '')) === '') {
            $item['shippingCity'] = $item['city'] ?? '';
        }
        if (trim((string) ($item['shippingState'] ?? '')) === '') {
            $item['shippingState'] = $item['state'] ?? '';
        }
        if (trim((string) ($item['shippingPostcode'] ?? '')) === '') {
            $item['shippingPostcode'] = $item['zip'] ?? '';
        }
    }
    unset($item);
    opd_json_response(['items' => $items, 'total' => count($items)]);
}

if ($method === 'POST') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $id = 'user-' . bin2hex(random_bytes(16));
    $now = gmdate('Y-m-d H:i:s');

    $data = users_build_write_data($payload, $writeColumns, $availableColumns, false);
    if (isset($availableColumns['updatedAt'])) {
        $data['updatedAt'] = $now;
    }
    $data = array_merge(['id' => $id], $data);

    if (empty($data)) {
        opd_json_response(['error' => 'No writable fields provided'], 400);
    }

    $columns = array_keys($data);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = sprintf('INSERT INTO users (%s) VALUES (%s)', implode(',', $columns), $placeholders);
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($data));

    $row = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $row->execute([$id]);
    $result = $row->fetch() ?: [];
    unset($result['passwordHash']);
    opd_json_response($result, 201);
}

if ($method === 'PUT') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $payload = opd_read_json();
    $id = $_GET['id'] ?? ($payload['id'] ?? '');
    if ($id === '') {
        opd_json_response(['error' => 'Missing id'], 400);
    }

    $now = gmdate('Y-m-d H:i:s');
    $data = users_build_write_data($payload, $writeColumns, $availableColumns, true);
    if (isset($availableColumns['updatedAt'])) {
        $data['updatedAt'] = $now;
    }

    if (!$data) {
        opd_json_response(['error' => 'No writable fields provided'], 400);
    }

    $setParts = [];
    $values = [];
    foreach ($data as $column => $value) {
        $setParts[] = "{$column} = ?";
        $values[] = $value;
    }
    $values[] = $id;
    $sql = sprintf('UPDATE users SET %s WHERE id = ?', implode(', ', $setParts));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($values);
    if ($stmt->rowCount() === 0) {
        opd_json_response(['error' => 'Not found'], 404);
    }

    $row = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $row->execute([$id]);
    $result = $row->fetch() ?: [];
    unset($result['passwordHash']);
    opd_json_response($result);
}

if ($method === 'DELETE') {
    opd_require_role(['admin']);
    opd_require_csrf();
    $id = $_GET['id'] ?? '';
    if ($id === '') {
        opd_json_response(['error' => 'Missing id'], 400);
    }
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    opd_json_response(['ok' => $stmt->rowCount() > 0]);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);
