<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/site_auth.php';
require_once __DIR__ . '/../src/api_helpers.php';
require_once __DIR__ . '/../src/store.php';

$user = site_require_auth();
site_require_csrf();

if (!isset($_FILES['image'])) {
    opd_json_response(['error' => 'Missing image file'], 400);
}

$file = $_FILES['image'];
if (!is_array($file)) {
    opd_json_response(['error' => 'Upload failed'], 400);
}

$uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
if ($uploadError !== UPLOAD_ERR_OK) {
    $message = match ($uploadError) {
        UPLOAD_ERR_INI_SIZE => 'Image exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE => 'Image exceeds form upload limit.',
        UPLOAD_ERR_PARTIAL => 'Image upload was incomplete.',
        UPLOAD_ERR_NO_FILE => 'No image uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary upload directory.',
        UPLOAD_ERR_CANT_WRITE => 'Server failed to write the uploaded image.',
        UPLOAD_ERR_EXTENSION => 'Image upload stopped by a server extension.',
        default => 'Upload failed.'
    };
    opd_json_response(['error' => $message], 400);
}

$maxBytes = 5 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxBytes) {
    opd_json_response(['error' => 'Image too large (max 5MB)'], 400);
}

$mime = '';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($file['tmp_name']);
}
if ($mime === '' && function_exists('mime_content_type')) {
    $mime = (string) mime_content_type($file['tmp_name']);
}
if ($mime === '' && function_exists('getimagesize')) {
    $info = getimagesize($file['tmp_name']);
    if (is_array($info) && isset($info['mime'])) {
        $mime = (string) $info['mime'];
    }
}
$mime = trim($mime);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];
if ($mime === '') {
    opd_json_response(['error' => 'Unable to detect image type'], 400);
}
if (!isset($allowed[$mime])) {
    opd_json_response(['error' => 'Unsupported image type'], 400);
}

// Validate equipment belongs to user
$equipmentId = $_POST['equipmentId'] ?? '';
if ($equipmentId !== '') {
    $pdo = opd_db();
    $stmt = $pdo->prepare('SELECT id FROM equipment WHERE id = ? AND userId = ? LIMIT 1');
    $stmt->execute([$equipmentId, $user['id']]);
    if (!$stmt->fetch()) {
        opd_json_response(['error' => 'Equipment not found'], 404);
    }

    // Check max 8 images per equipment
    $countStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM equipment_images WHERE equipmentId = ?');
    $countStmt->execute([$equipmentId]);
    $count = (int) ($countStmt->fetch()['cnt'] ?? 0);
    if ($count >= 8) {
        opd_json_response(['error' => 'Maximum 8 images per equipment listing'], 400);
    }
}

$uploadDir = __DIR__ . '/../public/uploads/equipment';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    opd_json_response(['error' => 'Upload directory unavailable'], 500);
}

$extension = $allowed[$mime];
$filename = sprintf('equip-%s.%s', bin2hex(random_bytes(8)), $extension);
$targetPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    opd_json_response(['error' => 'Unable to save image'], 500);
}

$path = '/uploads/equipment/' . $filename;

// If equipmentId provided, also save to equipment_images table
if ($equipmentId !== '') {
    $pdo = opd_db();
    site_ensure_equipment_images_table($pdo);
    $imgId = opd_generate_id('eimg');
    $now = gmdate('Y-m-d H:i:s');

    // Check if this should be primary (first image)
    $checkStmt = $pdo->prepare('SELECT COUNT(*) as cnt FROM equipment_images WHERE equipmentId = ?');
    $checkStmt->execute([$equipmentId]);
    $isPrimary = ((int) ($checkStmt->fetch()['cnt'] ?? 0)) === 0 ? 1 : 0;

    $insert = $pdo->prepare(
        'INSERT INTO equipment_images (id, equipmentId, url, isPrimary, sortOrder, createdAt, updatedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([$imgId, $equipmentId, $path, $isPrimary, 0, $now, $now]);

    opd_json_response([
        'id' => $imgId,
        'path' => $path,
        'isPrimary' => $isPrimary,
    ]);
}

opd_json_response(['path' => $path]);
