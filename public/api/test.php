<?php
/**
 * Simple test endpoint to verify API is accessible
 * No authentication required
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'API is accessible',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion()
]);
