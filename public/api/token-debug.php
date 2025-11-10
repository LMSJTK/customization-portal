<?php
/**
 * Token Debug Endpoint
 * Shows what token is being received and its decoded contents
 * Helps debug authentication issues
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get Authorization header
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

$response = [
    'headers_received' => [
        'Authorization' => $authHeader ? 'Present' : 'Missing',
        'all_headers' => array_keys($headers)
    ]
];

if (empty($authHeader)) {
    $response['error'] = 'No Authorization header found';
    $response['hint'] = 'Make sure the frontend is sending: Authorization: Bearer <token>';
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Extract Bearer token
if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
    $response['error'] = 'Invalid Authorization header format';
    $response['received'] = $authHeader;
    $response['hint'] = 'Should be: Bearer <token>';
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

$token = $matches[1];
$response['token_received'] = 'Yes';
$response['token_length'] = strlen($token);

// Split token into parts
$parts = explode('.', $token);
$response['token_parts_count'] = count($parts);

if (count($parts) !== 3) {
    $response['error'] = 'Invalid JWT format (should have 3 parts)';
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// Decode header and payload (without verification for debugging)
list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

function base64UrlDecode($input) {
    $remainder = strlen($input) % 4;
    if ($remainder) {
        $input .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($input, '-_', '+/'));
}

$header = json_decode(base64UrlDecode($headerEncoded), true);
$payload = json_decode(base64UrlDecode($payloadEncoded), true);

$response['jwt_header'] = $header;
$response['jwt_payload'] = $payload;

// Check important claims
$response['validation_checks'] = [
    'has_exp' => isset($payload['exp']) ? 'Yes' : 'No',
    'is_expired' => isset($payload['exp']) ? ($payload['exp'] < time() ? 'YES - EXPIRED' : 'No') : 'Unknown',
    'exp_time' => isset($payload['exp']) ? date('Y-m-d H:i:s', $payload['exp']) : 'Not set',
    'current_time' => date('Y-m-d H:i:s', time()),
    'has_iss' => isset($payload['iss']) ? 'Yes' : 'No',
    'issuer' => $payload['iss'] ?? 'Not set',
    'has_cid' => isset($payload['cid']) ? 'Yes' : 'No',
    'cid' => $payload['cid'] ?? 'Not set',
    'has_aud' => isset($payload['aud']) ? 'Yes' : 'No',
    'aud' => $payload['aud'] ?? 'Not set',
    'has_sub' => isset($payload['sub']) ? 'Yes' : 'No',
    'sub' => $payload['sub'] ?? 'Not set'
];

// Check against expected values
require_once __DIR__ . '/config.php';

$expectedIssuer = OKTA_ISSUER;
if (empty($expectedIssuer)) {
    $expectedIssuer = 'https://' . OKTA_DOMAIN . '/oauth2/default';
}

$response['expected_values'] = [
    'issuer' => $expectedIssuer,
    'client_id' => OKTA_CLIENT_ID,
    'issuer_matches' => ($payload['iss'] ?? '') === $expectedIssuer ? 'YES' : 'NO',
    'client_id_matches' => (($payload['cid'] ?? '') === OKTA_CLIENT_ID || ($payload['aud'] ?? '') === OKTA_CLIENT_ID) ? 'YES' : 'NO'
];

echo json_encode($response, JSON_PRETTY_PRINT);
