<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';

opd_require_role(['admin', 'manager']);
opd_require_csrf();

if (!isset($_FILES['image'])) {
    opd_json_response(['error' => 'Missing image file'], 400);
}

$file = $_FILES['image'];
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    opd_json_response(['error' => 'Upload failed'], 400);
}

$maxBytes = 5 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxBytes) {
    opd_json_response(['error' => 'Image too large'], 400);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];
if (!isset($allowed[$mime])) {
    opd_json_response(['error' => 'Unsupported image type'], 400);
}

$uploadDir = __DIR__ . '/../public/uploads/products';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    opd_json_response(['error' => 'Upload directory unavailable'], 500);
}

$extension = $allowed[$mime];
$filename = sprintf('prod-%s.%s', bin2hex(random_bytes(8)), $extension);
$targetPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    opd_json_response(['error' => 'Unable to save image'], 500);
}

opd_json_response([
    'path' => '/uploads/products/' . $filename,
]);
