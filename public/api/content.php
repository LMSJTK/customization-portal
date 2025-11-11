<?php
/**
 * Content API Endpoint
 * Handles CRUD operations for content management
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
 * Handle GET requests - Retrieve content
 */
function handleGet($db, $user) {
    $contentType = $_GET['type'] ?? null;
    $contentId = $_GET['id'] ?? null;

    if ($contentId) {
        // Get specific content item
        $content = getContentById($db, $contentId, $user);
        if (!$content) {
            http_response_code(404);
            echo json_encode([
                'error' => 'Content not found',
                'code' => 'NOT_FOUND'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'content' => $content
        ]);
    } else {
        // Get list of content
        $items = getContentList($db, $contentType, $user);
        echo json_encode([
            'success' => true,
            'items' => $items,
            'count' => count($items)
        ]);
    }
}

/**
 * Handle POST requests - Create new customized content
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

    // Validate required fields
    $requiredFields = ['title', 'content_type'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode([
                'error' => "Missing required field: $field",
                'code' => 'MISSING_FIELD'
            ]);
            return;
        }
    }

    // Create new content
    $contentId = createContent($db, $data, $user);

    http_response_code(201);
    echo json_encode([
        'success' => true,
        'id' => $contentId,
        'message' => 'Content created successfully'
    ]);
}

/**
 * Handle PUT requests - Update existing content
 */
function handlePut($db, $user) {
    $contentId = $_GET['id'] ?? null;

    if (!$contentId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Content ID is required',
            'code' => 'MISSING_ID'
        ]);
        return;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Invalid JSON data',
            'code' => 'INVALID_DATA'
        ]);
        return;
    }

    // Update content
    $updated = updateContent($db, $contentId, $data, $user);

    if (!$updated) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Content not found or unauthorized',
            'code' => 'NOT_FOUND'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Content updated successfully'
    ]);
}

/**
 * Handle DELETE requests - Delete content
 */
function handleDelete($db, $user) {
    $contentId = $_GET['id'] ?? null;

    if (!$contentId) {
        http_response_code(400);
        echo json_encode([
            'error' => 'Content ID is required',
            'code' => 'MISSING_ID'
        ]);
        return;
    }

    // Delete content
    $deleted = deleteContent($db, $contentId, $user);

    if (!$deleted) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Content not found or unauthorized',
            'code' => 'NOT_FOUND'
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Content deleted successfully'
    ]);
}

/**
 * Get content by ID
 */
function getContentById($db, $contentId, $user) {
    $tableName = getTableName('content');
    $sql = "SELECT * FROM $tableName WHERE id = :id LIMIT 1";
    $result = $db->queryOne($sql, ['id' => $contentId]);
    return $result ?: null;
}

/**
 * Get list of content with optional filtering
 */
function getContentList($db, $contentType, $user) {
    $tableName = getTableName('content');
    $sql = "SELECT
                id,
                company_id,
                title,
                description,
                content_type,
                content_preview,
                content_url,
                email_subject,
                created_at,
                updated_at
            FROM $tableName
            WHERE 1=1";

    $params = [];

    // Filter by content type if provided
    if ($contentType) {
        $sql .= " AND content_type = :content_type";
        $params['content_type'] = $contentType;
    }

    // Order by most recent
    $sql .= " ORDER BY created_at DESC LIMIT 100";

    $results = $db->query($sql, $params);
    return $results;
}

/**
 * Create new content
 */
function createContent($db, $data, $user) {
    // Generate unique ID
    $id = uniqid('content_', true);

    // Use organization ID from Okta claims
    $companyId = $user['organization_id'] ?? 'unknown';

    $tableName = getTableName('content');
    $sql = "INSERT INTO $tableName (
                id,
                company_id,
                title,
                description,
                content_type,
                content_preview,
                content_url,
                email_from_address,
                email_subject,
                email_body_html,
                email_attachment_filename,
                email_attachment_content,
                created_at,
                updated_at
            ) VALUES (
                :id,
                :company_id,
                :title,
                :description,
                :content_type,
                :content_preview,
                :content_url,
                :email_from_address,
                :email_subject,
                :email_body_html,
                :email_attachment_filename,
                :email_attachment_content,
                NOW(),
                NOW()
            )";

    $params = [
        'id' => $id,
        'company_id' => $companyId,
        'title' => $data['title'],
        'description' => $data['description'] ?? null,
        'content_type' => $data['content_type'],
        'content_preview' => $data['content_preview'] ?? null,
        'content_url' => $data['content_url'] ?? null,
        'email_from_address' => $data['email_from_address'] ?? null,
        'email_subject' => $data['email_subject'] ?? null,
        'email_body_html' => $data['email_body_html'] ?? null,
        'email_attachment_filename' => $data['email_attachment_filename'] ?? null,
        'email_attachment_content' => $data['email_attachment_content'] ?? null,
    ];

    $db->execute($sql, $params);

    return $id;
}

/**
 * Update existing content
 */
function updateContent($db, $contentId, $data, $user) {
    // Build dynamic update query
    $updateFields = [];
    $params = ['id' => $contentId];

    $allowedFields = [
        'title', 'description', 'content_preview', 'content_url',
        'email_from_address', 'email_subject', 'email_body_html',
        'email_attachment_filename', 'email_attachment_content'
    ];

    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = :$field";
            $params[$field] = $data[$field];
        }
    }

    if (empty($updateFields)) {
        return false;
    }

    $updateFields[] = "updated_at = NOW()";

    $tableName = getTableName('content');
    $sql = "UPDATE $tableName SET " . implode(', ', $updateFields) . " WHERE id = :id";

    $rowCount = $db->execute($sql, $params);

    return $rowCount > 0;
}

/**
 * Delete content
 */
function deleteContent($db, $contentId, $user) {
    // For now, we'll allow deletion of any content
    // In production, you might want to add authorization checks

    $tableName = getTableName('content');
    $sql = "DELETE FROM $tableName WHERE id = :id";
    $rowCount = $db->execute($sql, ['id' => $contentId]);

    return $rowCount > 0;
}
