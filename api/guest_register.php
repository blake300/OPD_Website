<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/api_helpers.php';

// Only allow POST
opd_require_method('POST');

// Parse JSON body
$data = opd_read_json();

// Validate required fields
$email = trim((string) ($data['email'] ?? ''));
$password = (string) ($data['password'] ?? '');
$name = trim((string) ($data['name'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    opd_json_response(['error' => 'Valid email is required'], 400);
}

if (strlen($password) < 6) {
    opd_json_response(['error' => 'Password must be at least 6 characters'], 400);
}

// Check if email already exists
$pdo = opd_db();
$check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$check->execute([$email]);
if ($check->fetch()) {
    opd_json_response(['error' => 'An account with this email already exists. Please log in instead.'], 400);
}

// Create the user
$userId = opd_generate_id('usr');
$now = gmdate('Y-m-d H:i:s');
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$insert = $pdo->prepare(
    'INSERT INTO users (id, email, passwordHash, name, address, city, state, zip, cellPhone, role, status, createdAt, updatedAt)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

$insert->execute([
    $userId,
    $email,
    $passwordHash,
    $name,
    trim((string) ($data['address'] ?? '')),
    trim((string) ($data['city'] ?? '')),
    trim((string) ($data['state'] ?? '')),
    trim((string) ($data['zip'] ?? '')),
    trim((string) ($data['phone'] ?? '')),
    'customer',
    'active',
    $now,
    $now,
]);

// Auto-login the user
site_start_session();
$_SESSION['site_user_id'] = $userId;

opd_json_response(['ok' => true, 'userId' => $userId]);
