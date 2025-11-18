<?php
/**
 * Logo Upload API Endpoint
 * Handles image file uploads for brand kit logos
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

// Enable CORS
header('Access-Control-Allow-Origin: ' . CORS_ALLOWED_ORIGINS);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authenticate user
$user = Auth::authenticate();
if (!$user) {
    Auth::unauthorized('Authentication required');
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'error' => 'Method not allowed',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode([
        'error' => 'No file uploaded',
        'code' => 'NO_FILE'
    ]);
    exit;
}

$file = $_FILES['logo'];

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by PHP extension'
    ];

    http_response_code(500);
    echo json_encode([
        'error' => $errorMessages[$file['error']] ?? 'Unknown upload error',
        'code' => 'UPLOAD_ERROR'
    ]);
    exit;
}

// Validate file size (max 5MB)
$maxSize = 5 * 1024 * 1024; // 5MB in bytes
if ($file['size'] > $maxSize) {
    http_response_code(400);
    echo json_encode([
        'error' => 'File size exceeds 5MB limit',
        'code' => 'FILE_TOO_LARGE',
        'max_size' => '5MB',
        'file_size' => round($file['size'] / 1024 / 1024, 2) . 'MB'
    ]);
    exit;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid file type. Only images are allowed (JPG, PNG, GIF, WebP, SVG)',
        'code' => 'INVALID_FILE_TYPE',
        'detected_type' => $mimeType
    ]);
    exit;
}

// Get file extension
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if (empty($extension)) {
    // Fallback: get extension from MIME type
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/jpg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];
    $extension = $mimeToExt[$mimeType] ?? 'png';
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../uploads/logos';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to create upload directory',
            'code' => 'DIRECTORY_ERROR'
        ]);
        exit;
    }
}

// Generate unique filename
$companyId = $user['organization_id'] ?? 'unknown';
$timestamp = time();
$filename = 'logo_' . preg_replace('/[^a-zA-Z0-9]/', '_', $companyId) . '_' . $timestamp . '.' . $extension;
$filepath = $uploadDir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filepath)) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to save uploaded file',
        'code' => 'SAVE_ERROR'
    ]);
    exit;
}

// Generate URL (relative to public directory)
$logoUrl = '/uploads/logos/' . $filename;

// Return success with URL
http_response_code(200);
echo json_encode([
    'success' => true,
    'url' => $logoUrl,
    'filename' => $filename,
    'size' => $file['size'],
    'type' => $mimeType
]);
