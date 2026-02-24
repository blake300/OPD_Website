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
    opd_json_response(['error' => 'Image too large'], 400);
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

$context = $_POST['context'] ?? 'products';
$allowedContexts = ['products', 'pages'];
if (!in_array($context, $allowedContexts, true)) {
    $context = 'products';
}

$prefix = $context === 'pages' ? 'page' : 'prod';
$uploadDir = __DIR__ . '/../public/uploads/' . $context;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    opd_json_response(['error' => 'Upload directory unavailable'], 500);
}

$extension = $allowed[$mime];
$filename = sprintf('%s-%s.%s', $prefix, bin2hex(random_bytes(8)), $extension);
$targetPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    opd_json_response(['error' => 'Unable to save image'], 500);
}

opd_json_response([
    'path' => '/uploads/' . $context . '/' . $filename,
]);
