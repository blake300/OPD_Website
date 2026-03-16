<?php

declare(strict_types=1);

require __DIR__ . '/../src/store.php';

function test_output(string $label, $value): void
{
    echo '[' . $label . '] ';
    if (is_bool($value)) {
        echo $value ? 'true' : 'false';
    } elseif (is_array($value)) {
        echo json_encode($value);
    } else {
        echo (string) $value;
    }
    echo PHP_EOL;
}

function simulate_dashboard_post(array $user, string $file, array $post): void
{
    site_start_session();
    $_SESSION['site_user'] = $user;
    $_SESSION['site_csrf'] = bin2hex(random_bytes(16));
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = $post;
    $_POST['_csrf'] = $_SESSION['site_csrf'];
    ob_start();
    require $file;
    ob_end_clean();
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
}

function fetch_row(PDO $pdo, string $sql, array $params): ?array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

function flatten_labels(array $nodes): array
{
    $labels = [];
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $label = trim((string) ($node['label'] ?? ''));
        if ($label !== '') {
            $labels[] = $label;
        }
        if (!empty($node['children']) && is_array($node['children'])) {
            $labels = array_merge($labels, flatten_labels($node['children']));
        }
    }
    return $labels;
}

site_start_session();

$pdo = opd_db();
$pdo->beginTransaction();

try {
    $suffix = (string) random_int(1000, 9999);
    $now = gmdate('Y-m-d H:i:s');

    $userAId = 'test-user-a-' . $suffix;
    $userBId = 'test-user-b-' . $suffix;
    $userAEmail = "test.a.$suffix@example.com";
    $userBEmail = "test.b.$suffix@example.com";

    $insertUser = $pdo->prepare('INSERT INTO users (id, name, email, role, status, updatedAt) VALUES (?, ?, ?, ?, ?, ?)');
    $insertUser->execute([$userAId, 'Test User A', $userAEmail, 'customer', 'active', $now]);
    $insertUser->execute([$userBId, 'Test User B', $userBEmail, 'customer', 'active', $now]);

    $insertCode = $pdo->prepare(
        'INSERT INTO accounting_codes (id, userId, code, description, status, parentId, category, position, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $codeA = 'LOC-A-' . $suffix;
    $codeB = 'LOC-B-' . $suffix;
    $insertCode->execute(['ac-a-' . $suffix, $userAId, $codeA, null, 'active', null, 'location', 0, $now, $now]);
    $insertCode->execute(['ac-b-' . $suffix, $userBId, $codeB, null, 'active', null, 'location', 0, $now, $now]);

    $userA = ['id' => $userAId, 'name' => 'Test User A', 'email' => $userAEmail, 'role' => 'customer'];
    $userB = ['id' => $userBId, 'name' => 'Test User B', 'email' => $userBEmail, 'role' => 'customer'];

    // Vendor invite flow: A adds vendor B (creates pending client for B)
    simulate_dashboard_post($userA, __DIR__ . '/../public/dashboard-vendors.php', [
        'action' => 'create',
        'name' => 'Vendor B',
        'contact' => 'Test User B',
        'email' => $userBEmail,
        'phone' => '',
        'status' => 'active'
    ]);

    $vendorA = fetch_row(
        $pdo,
        'SELECT * FROM vendors WHERE userId = ? AND LOWER(email) = LOWER(?) ORDER BY createdAt DESC LIMIT 1',
        [$userAId, $userBEmail]
    );
    $clientB = fetch_row(
        $pdo,
        'SELECT * FROM clients WHERE userId = ? AND linkedUserId = ? ORDER BY createdAt DESC LIMIT 1',
        [$userBId, $userAId]
    );
    $vendorAId = (string) ($vendorA['id'] ?? '');
    $clientBId = (string) ($clientB['id'] ?? '');

    test_output('vendor_requested', ($vendorA['status'] ?? '') === 'requested');
    test_output('vendor_linked', ($vendorA['linkedUserId'] ?? '') === $userBId);
    test_output('client_pending', ($clientB['status'] ?? '') === 'pending');
    test_output('client_billable_pending', $clientB ? site_client_is_billable($clientB) : false);

    // Client decline/accept on B side (should sync back to A vendor)
    simulate_dashboard_post($userB, __DIR__ . '/../public/dashboard-clients.php', [
        'action' => 'decline',
        'id' => $clientBId
    ]);
    $vendorA = fetch_row($pdo, 'SELECT status FROM vendors WHERE id = ? LIMIT 1', [$vendorAId]);
    $clientB = fetch_row($pdo, 'SELECT status FROM clients WHERE id = ? LIMIT 1', [$clientBId]);
    test_output('client_declined', ($clientB['status'] ?? '') === 'declined');
    test_output('vendor_synced_declined', ($vendorA['status'] ?? '') === 'declined');

    simulate_dashboard_post($userB, __DIR__ . '/../public/dashboard-clients.php', [
        'action' => 'accept',
        'id' => $clientBId
    ]);
    $vendorA = fetch_row($pdo, 'SELECT status FROM vendors WHERE id = ? LIMIT 1', [$vendorAId]);
    $clientB = fetch_row($pdo, 'SELECT status FROM clients WHERE id = ? LIMIT 1', [$clientBId]);
    test_output('client_accepted', ($clientB['status'] ?? '') === 'active');
    test_output('vendor_synced_active', ($vendorA['status'] ?? '') === 'active');

    // Client invite flow: A adds client B (creates pending vendor for B)
    simulate_dashboard_post($userA, __DIR__ . '/../public/dashboard-clients.php', [
        'action' => 'create',
        'name' => 'Client B',
        'email' => $userBEmail,
        'phone' => '',
        'status' => 'active',
        'notes' => ''
    ]);

    $clientA = fetch_row(
        $pdo,
        'SELECT * FROM clients WHERE userId = ? AND linkedUserId = ? ORDER BY createdAt DESC LIMIT 1',
        [$userAId, $userBId]
    );
    $vendorB = fetch_row(
        $pdo,
        'SELECT * FROM vendors WHERE userId = ? AND linkedUserId = ? ORDER BY createdAt DESC LIMIT 1',
        [$userBId, $userAId]
    );
    $clientAId = (string) ($clientA['id'] ?? '');
    $vendorBId = (string) ($vendorB['id'] ?? '');

    test_output('client_requested', ($clientA['status'] ?? '') === 'requested');
    test_output('vendor_pending', ($vendorB['status'] ?? '') === 'pending');
    test_output('client_billable_requested', $clientA ? site_client_is_billable($clientA) : false);

    simulate_dashboard_post($userB, __DIR__ . '/../public/dashboard-vendors.php', [
        'action' => 'decline',
        'id' => $vendorBId
    ]);
    $clientA = fetch_row($pdo, 'SELECT status FROM clients WHERE id = ? LIMIT 1', [$clientAId]);
    $vendorB = fetch_row($pdo, 'SELECT status FROM vendors WHERE id = ? LIMIT 1', [$vendorBId]);
    test_output('vendor_declined', ($vendorB['status'] ?? '') === 'declined');
    test_output('client_synced_declined', ($clientA['status'] ?? '') === 'declined');

    simulate_dashboard_post($userB, __DIR__ . '/../public/dashboard-vendors.php', [
        'action' => 'accept',
        'id' => $vendorBId
    ]);
    $clientA = fetch_row($pdo, 'SELECT status FROM clients WHERE id = ? LIMIT 1', [$clientAId]);
    $vendorB = fetch_row($pdo, 'SELECT status FROM vendors WHERE id = ? LIMIT 1', [$vendorBId]);
    test_output('vendor_accepted', ($vendorB['status'] ?? '') === 'active');
    test_output('client_synced_active', ($clientA['status'] ?? '') === 'active');

    $labelsFromStructure = function (array $structure): array {
        return array_merge(
            flatten_labels($structure['location'] ?? []),
            flatten_labels($structure['code1'] ?? []),
            flatten_labels($structure['code2'] ?? [])
        );
    };

    $clientRecord = fetch_row($pdo, 'SELECT * FROM clients WHERE id = ? LIMIT 1', [$clientAId]);
    $structure = $clientRecord
        ? site_get_accounting_structure_for_client($userAId, (string) $clientRecord['id'])
        : ['location' => [], 'code1' => [], 'code2' => []];
    $labels = $labelsFromStructure($structure);
    test_output('accounting_uses_client_codes', in_array($codeB, $labels, true));
    test_output('accounting_uses_vendor_codes', in_array($codeA, $labels, true));

    $vendorClientRecord = fetch_row($pdo, 'SELECT * FROM clients WHERE id = ? LIMIT 1', [$clientBId]);
    $vendorClientStructure = $vendorClientRecord
        ? site_get_accounting_structure_for_client($userBId, (string) $vendorClientRecord['id'])
        : ['location' => [], 'code1' => [], 'code2' => []];
    $vendorClientLabels = $labelsFromStructure($vendorClientStructure);
    test_output('vendor_cart_client_codes', in_array($codeA, $vendorClientLabels, true));

    $linkedUserStructure = site_get_accounting_structure_for_client($userBId, $userAId);
    $linkedUserLabels = $labelsFromStructure($linkedUserStructure);
    test_output('vendor_cart_linked_user_codes', in_array($codeA, $linkedUserLabels, true));

    $vendorIdStructure = site_get_accounting_structure_for_client($userBId, $vendorAId);
    $vendorIdLabels = $labelsFromStructure($vendorIdStructure);
    test_output('vendor_cart_vendor_id_codes', in_array($codeA, $vendorIdLabels, true));

    $pdo->rollBack();
} catch (Throwable $e) {
    $pdo->rollBack();
    echo '[exception] ' . $e->getMessage() . PHP_EOL;
}
