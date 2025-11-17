<?php
/**
 * Brand Kit API Endpoint
 * Handles CRUD operations for brand kit management
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

// Enable CORS
header('Access-Control-Allow-Origin: ' . CORS_ALLOWED_ORIGINS);
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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

// Get database instance
try {
    $db = Database::getInstance();
} catch (Exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'code' => 'DATABASE_ERROR'
    ]);
    exit;
}

// Route the request
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            handleGet($db, $user);
            break;

        case 'POST':
            handlePost($db, $user);
            break;

        case 'PUT':
            handlePut($db, $user);
            break;

        case 'DELETE':
            handleDelete($db, $user);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'error' => 'Method not allowed',
                'code' => 'METHOD_NOT_ALLOWED'
            ]);
            break;
    }
} catch (Exception $e) {
    error_log('API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'code' => 'INTERNAL_ERROR',
        'message' => $e->getMessage()
    ]);
}

/**
 * Handle GET requests - Retrieve brand kit for user's company
 */
function handleGet($db, $user) {
    // Use organization ID from Okta claims
    $companyId = $user['organization_id'] ?? null;

    if (!$companyId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Organization ID not found in user claims',
            'code' => 'MISSING_ORGANIZATION'
        ]);
        return;
    }

    // Get brand kit for this company
    $brandKit = getBrandKitByCompany($db, $companyId);

    if (!$brandKit) {
        // Return default brand kit if none exists
        echo json_encode([
            'success' => true,
            'brand_kit' => getDefaultBrandKit($companyId),
            'is_default' => true
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'brand_kit' => $brandKit,
        'is_default' => false
    ]);
}

/**
 * Handle POST requests - Create new brand kit
 */
function handlePost($db, $user) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid JSON data',
            'code' => 'INVALID_DATA'
        ]);
        return;
    }

    // Use organization ID from Okta claims
    $companyId = $user['organization_id'] ?? null;

    if (!$companyId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Organization ID not found in user claims',
            'code' => 'MISSING_ORGANIZATION'
        ]);
        return;
    }

    // Check if brand kit already exists for this company
    $existing = getBrandKitByCompany($db, $companyId);
    if ($existing) {
        http_response_code(409);
        echo json_encode([
            'error' => 'Brand kit already exists for this organization. Use PUT to update.',
            'code' => 'ALREADY_EXISTS',
            'brand_kit_id' => $existing['id']
        ]);
        return;
    }

    // Create new brand kit
    $brandKitId = createBrandKit($db, $data, $companyId);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $brandKitId,
        'message' => 'Brand kit created successfully'
    ]);
}

/**
 * Handle PUT requests - Update existing brand kit
 */
function handlePut($db, $user) {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid JSON data',
            'code' => 'INVALID_DATA'
        ]);
        return;
    }

    // Use organization ID from Okta claims
    $companyId = $user['organization_id'] ?? null;

    if (!$companyId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Organization ID not found in user claims',
            'code' => 'MISSING_ORGANIZATION'
        ]);
        return;
    }

    // Get existing brand kit
    $existing = getBrandKitByCompany($db, $companyId);

    if (!$existing) {
        // Create if doesn't exist
        $brandKitId = createBrandKit($db, $data, $companyId);
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'id' => $brandKitId,
            'message' => 'Brand kit created successfully'
        ]);
        return;
    }

    // Update existing brand kit
    $updated = updateBrandKit($db, $existing['id'], $data, $companyId);

    if (!$updated) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to update brand kit',
            'code' => 'UPDATE_FAILED'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Brand kit updated successfully'
    ]);
}

/**
 * Handle DELETE requests - Delete brand kit
 */
function handleDelete($db, $user) {
    // Use organization ID from Okta claims
    $companyId = $user['organization_id'] ?? null;

    if (!$companyId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Organization ID not found in user claims',
            'code' => 'MISSING_ORGANIZATION'
        ]);
        return;
    }

    // Delete brand kit for this company
    $deleted = deleteBrandKit($db, $companyId);

    if (!$deleted) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Brand kit not found',
            'code' => 'NOT_FOUND'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Brand kit deleted successfully'
    ]);
}

/**
 * Get brand kit by company ID
 */
function getBrandKitByCompany($db, $companyId) {
    $tableName = getTableName('brand_kits');
    $sql = "SELECT * FROM $tableName WHERE company_id = :company_id LIMIT 1";
    $result = $db->queryOne($sql, ['company_id' => $companyId]);
    return $result ?: null;
}

/**
 * Get default brand kit values
 */
function getDefaultBrandKit($companyId) {
    return [
        'id' => null,
        'company_id' => $companyId,
        'primary_color' => '#4F46E5',
        'text_color' => '#FFFFFF',
        'logo_url' => null,
        'font_family' => 'Inter',
        'created_at' => null,
        'updated_at' => null
    ];
}

/**
 * Validate hex color format
 */
function isValidHexColor($color) {
    return is_string($color) && preg_match('/^#[a-fA-F0-9]{6}$/', $color);
}

/**
 * Create new brand kit
 */
function createBrandKit($db, $data, $companyId) {
    // Generate unique ID
    $id = uniqid('brand_kit_', true);

    // Validate colors if provided
    $primaryColor = $data['primary_color'] ?? '#4F46E5';
    $textColor = $data['text_color'] ?? '#FFFFFF';

    if (!isValidHexColor($primaryColor)) {
        throw new Exception('Invalid primary_color format. Must be hex color (e.g., #4F46E5)');
    }

    if (!isValidHexColor($textColor)) {
        throw new Exception('Invalid text_color format. Must be hex color (e.g., #FFFFFF)');
    }

    $tableName = getTableName('brand_kits');
    $sql = "INSERT INTO $tableName (
                id,
                company_id,
                primary_color,
                text_color,
                logo_url,
                font_family,
                created_at,
                updated_at
            ) VALUES (
                :id,
                :company_id,
                :primary_color,
                :text_color,
                :logo_url,
                :font_family,
                NOW(),
                NOW()
            )";

    $params = [
        'id' => $id,
        'company_id' => $companyId,
        'primary_color' => strtoupper($primaryColor),
        'text_color' => strtoupper($textColor),
        'logo_url' => $data['logo_url'] ?? null,
        'font_family' => $data['font_family'] ?? 'Inter',
    ];

    $db->execute($sql, $params);

    return $id;
}

/**
 * Update existing brand kit
 */
function updateBrandKit($db, $brandKitId, $data, $companyId) {
    // Build dynamic update query
    $updateFields = [];
    $params = ['id' => $brandKitId, 'company_id' => $companyId];

    $allowedFields = [
        'primary_color', 'text_color', 'logo_url', 'font_family'
    ];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            // Validate colors
            if (($field === 'primary_color' || $field === 'text_color')) {
                if (!isValidHexColor($data[$field])) {
                    throw new Exception("Invalid $field format. Must be hex color (e.g., #4F46E5)");
                }
                $updateFields[] = "$field = :$field";
                $params[$field] = strtoupper($data[$field]);
            } else {
                $updateFields[] = "$field = :$field";
                $params[$field] = $data[$field];
            }
        }
    }

    if (empty($updateFields)) {
        return false;
    }

    $updateFields[] = "updated_at = NOW()";

    $tableName = getTableName('brand_kits');
    $sql = "UPDATE $tableName SET " . implode(', ', $updateFields) . " WHERE id = :id AND company_id = :company_id";

    $rowCount = $db->execute($sql, $params);

    return $rowCount > 0;
}

/**
 * Delete brand kit
 */
function deleteBrandKit($db, $companyId) {
    $tableName = getTableName('brand_kits');
    $sql = "DELETE FROM $tableName WHERE company_id = :company_id";
    $rowCount = $db->execute($sql, ['company_id' => $companyId]);

    return $rowCount > 0;
}
