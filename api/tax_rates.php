<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/db_conn.php';
require_once __DIR__ . '/../src/tax_rates.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// ── GET: list all tax rate groups with their zips ──
if ($method === 'GET') {
    opd_require_role(['admin', 'manager']);

    try {
        $pdo = opd_db();
        $groups = $pdo->query(
            'SELECT id, name, rate, createdAt, updatedAt FROM tax_rate_groups ORDER BY rate ASC'
        )->fetchAll();

        $items = [];
        foreach ($groups as $g) {
            $stmt = $pdo->prepare('SELECT zip FROM tax_rate_zips WHERE groupId = ? ORDER BY zip ASC');
            $stmt->execute([$g['id']]);
            $zips = $stmt->fetchAll(\PDO::FETCH_COLUMN);

            $items[] = [
                'id'          => $g['id'],
                'name'        => $g['name'] ?? '',
                'rate'        => (float) $g['rate'],
                'ratePercent' => (float) $g['rate'] * 100,
                'zips'        => $zips,
                'createdAt'   => $g['createdAt'] ?? '',
                'updatedAt'   => $g['updatedAt'] ?? '',
            ];
        }

        opd_json_response(['items' => $items, 'total' => count($items)]);
    } catch (\Throwable $e) {
        opd_json_response(['items' => [], 'total' => 0]);
    }
}

// ── POST: create a new rate group ──
if ($method === 'POST') {
    opd_require_role(['admin']);
    opd_require_csrf();

    $payload = opd_read_json();
    $name = trim((string) ($payload['name'] ?? ''));
    $rateRaw = $payload['rate'] ?? null;
    $zipsRaw = trim((string) ($payload['zips'] ?? ''));

    if ($rateRaw === null || !is_numeric($rateRaw)) {
        opd_json_response(['error' => 'Rate is required and must be a number.'], 400);
    }
    $ratePercent = (float) $rateRaw;
    if ($ratePercent < 0 || $ratePercent > 100) {
        opd_json_response(['error' => 'Rate must be between 0 and 100.'], 400);
    }
    $rate = $ratePercent / 100;

    $zips = opd_parse_zip_list($zipsRaw);
    if (empty($zips)) {
        opd_json_response(['error' => 'At least one valid zip code is required.'], 400);
    }

    $pdo = opd_db();
    $groupId = opd_generate_id('txgrp');
    $now = gmdate('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        // Check for duplicate zips in other groups
        $dupeError = opd_check_duplicate_zips($pdo, $zips, null);
        if ($dupeError) {
            $pdo->rollBack();
            opd_json_response(['error' => $dupeError], 400);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO tax_rate_groups (id, name, rate, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$groupId, $name, $rate, $now, $now]);

        opd_insert_zips($pdo, $groupId, $zips);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        opd_json_response(['error' => 'Failed to create tax rate group: ' . $e->getMessage()], 500);
    }

    opd_json_response([
        'id'          => $groupId,
        'name'        => $name,
        'rate'        => $rate,
        'ratePercent' => $ratePercent,
        'zips'        => $zips,
        'createdAt'   => $now,
        'updatedAt'   => $now,
    ], 201);
}

// ── PUT: update an existing rate group ──
if ($method === 'PUT') {
    opd_require_role(['admin']);
    opd_require_csrf();

    $groupId = trim((string) ($_GET['id'] ?? ''));
    if ($groupId === '') {
        opd_json_response(['error' => 'Missing id parameter.'], 400);
    }

    $pdo = opd_db();

    try {
        $existing = $pdo->prepare('SELECT id FROM tax_rate_groups WHERE id = ?');
        $existing->execute([$groupId]);
        if (!$existing->fetch()) {
            opd_json_response(['error' => 'Tax rate group not found.'], 404);
        }
    } catch (\Throwable $e) {
        opd_json_response(['error' => 'Failed to look up tax rate group: ' . $e->getMessage()], 500);
    }

    $payload = opd_read_json();
    $name = trim((string) ($payload['name'] ?? ''));
    $rateRaw = $payload['rate'] ?? null;
    $zipsRaw = trim((string) ($payload['zips'] ?? ''));

    if ($rateRaw === null || !is_numeric($rateRaw)) {
        opd_json_response(['error' => 'Rate is required and must be a number.'], 400);
    }
    $ratePercent = (float) $rateRaw;
    if ($ratePercent < 0 || $ratePercent > 100) {
        opd_json_response(['error' => 'Rate must be between 0 and 100.'], 400);
    }
    $rate = $ratePercent / 100;

    $zips = opd_parse_zip_list($zipsRaw);
    if (empty($zips)) {
        opd_json_response(['error' => 'At least one valid zip code is required.'], 400);
    }

    $now = gmdate('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        $dupeError = opd_check_duplicate_zips($pdo, $zips, $groupId);
        if ($dupeError) {
            $pdo->rollBack();
            opd_json_response(['error' => $dupeError], 400);
        }

        $stmt = $pdo->prepare(
            'UPDATE tax_rate_groups SET name = ?, rate = ?, updatedAt = ? WHERE id = ?'
        );
        $stmt->execute([$name, $rate, $now, $groupId]);

        $pdo->prepare('DELETE FROM tax_rate_zips WHERE groupId = ?')->execute([$groupId]);
        opd_insert_zips($pdo, $groupId, $zips);

        $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        opd_json_response(['error' => 'Failed to update tax rate group: ' . $e->getMessage()], 500);
    }

    opd_json_response([
        'id'          => $groupId,
        'name'        => $name,
        'rate'        => $rate,
        'ratePercent' => $ratePercent,
        'zips'        => $zips,
        'updatedAt'   => $now,
    ]);
}

// ── DELETE: remove a rate group ──
if ($method === 'DELETE') {
    opd_require_role(['admin']);
    opd_require_csrf();

    $groupId = trim((string) ($_GET['id'] ?? ''));
    if ($groupId === '') {
        opd_json_response(['error' => 'Missing id parameter.'], 400);
    }

    try {
        $pdo = opd_db();
        $stmt = $pdo->prepare('DELETE FROM tax_rate_groups WHERE id = ?');
        $stmt->execute([$groupId]);
    } catch (\Throwable $e) {
        opd_json_response(['error' => 'Failed to delete tax rate group: ' . $e->getMessage()], 500);
    }

    opd_json_response(['ok' => true]);
}

opd_json_response(['error' => 'Method Not Allowed'], 405);

// ── Helper: parse comma/newline-separated zip list ──
function opd_parse_zip_list(string $raw): array
{
    $parts = preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);
    $zips = [];
    $seen = [];
    foreach ($parts as $part) {
        $zip = opd_normalize_zip($part);
        if ($zip !== '' && !isset($seen[$zip])) {
            $zips[] = $zip;
            $seen[$zip] = true;
        }
    }
    return $zips;
}

// ── Helper: check for zips already assigned to other groups ──
function opd_check_duplicate_zips(\PDO $pdo, array $zips, ?string $excludeGroupId): ?string
{
    if (empty($zips)) {
        return null;
    }
    $placeholders = implode(',', array_fill(0, count($zips), '?'));
    $params = $zips;

    $sql = "SELECT trz.zip, trg.name, trg.id FROM tax_rate_zips trz
            JOIN tax_rate_groups trg ON trg.id = trz.groupId
            WHERE trz.zip IN ({$placeholders})";
    if ($excludeGroupId !== null) {
        $sql .= ' AND trz.groupId != ?';
        $params[] = $excludeGroupId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    if ($row) {
        $label = $row['name'] !== '' ? "'{$row['name']}'" : $row['id'];
        return "Zip code {$row['zip']} is already assigned to group {$label}.";
    }
    return null;
}

// ── Helper: insert zip rows for a group ──
function opd_insert_zips(\PDO $pdo, string $groupId, array $zips): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO tax_rate_zips (id, groupId, zip) VALUES (?, ?, ?)'
    );
    foreach ($zips as $zip) {
        $stmt->execute([opd_generate_id('txzip'), $groupId, $zip]);
    }
}
