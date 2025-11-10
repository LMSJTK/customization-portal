<?php
/**
 * Simple test endpoint that uses auth middleware
 * This will help us see what error auth.php is throwing
 */

// Temporarily enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    require_once __DIR__ . '/auth.php';

    // Try to authenticate
    $user = Auth::authenticate();

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'Authentication failed - no user returned'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful!',
            'user' => $user
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
}
