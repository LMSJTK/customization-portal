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

    // Get the original content to check if it's file-based
    $originalContent = getContentById($db, $contentId, $user);

    if (!$originalContent) {
        http_response_code(404);
        echo json_encode([
            'error' => 'Content not found',
            'code' => 'NOT_FOUND'
        ]);
        return;
    }

    // If content is file-based and being edited, create a customized copy for the organization
    if (!empty($originalContent['is_file_based']) && isset($data['email_body_html'])) {
        $companyId = $user['organization_id'] ?? 'unknown';

        // Check if a customized version already exists for this company
        $customizedId = findCustomizedContent($db, $contentId, $companyId);

        if ($customizedId) {
            // Update existing customized version
            $updated = updateContent($db, $customizedId, $data, $user);
            $resultId = $customizedId;
        } else {
            // Create new customized version
            $customizedData = [
                'title' => $originalContent['title'] . ' (Customized)',
                'description' => $originalContent['description'],
                'content_type' => $originalContent['content_type'],
                'email_subject' => $originalContent['email_subject'] ?? null,
                'email_body_html' => $data['email_body_html'],
                'original_content_id' => $contentId // Store reference to original
            ];

            $resultId = createContent($db, $customizedData, $user);
            $updated = true;
        }

        if (!$updated) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to save customized content',
                'code' => 'SAVE_FAILED'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Customized content saved successfully',
            'id' => $resultId,
            'is_customized' => true
        ]);
    } else {
        // Normal update for database-stored content
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
 * Find customized content for a company based on original content ID
 */
function findCustomizedContent($db, $originalContentId, $companyId) {
    $tableName = getTableName('content');

    // Try to find by original_content_id if that column exists
    try {
        $sql = "SELECT id FROM $tableName
                WHERE original_content_id = :original_id
                AND company_id = :company_id
                LIMIT 1";
        $result = $db->queryOne($sql, [
            'original_id' => $originalContentId,
            'company_id' => $companyId
        ]);

        return $result ? $result['id'] : null;
    } catch (Exception $e) {
        // Column doesn't exist yet, return null
        return null;
    }
}

/**
 * Get content by ID
 */
function getContentById($db, $contentId, $user) {
    $tableName = getTableName('content');
    $sql = "SELECT * FROM $tableName WHERE id = :id LIMIT 1";
    $result = $db->queryOne($sql, ['id' => $contentId]);

    if (!$result) {
        return null;
    }

    // If content is file-based (has content_url but no email_body_html), fetch HTML from file
    if (!empty($result['content_url']) && empty($result['email_body_html'])) {
        $result['email_body_html'] = fetchHtmlFromFile($result['content_url']);
        $result['is_file_based'] = true;
        $result['original_content_url'] = $result['content_url'];
    } else {
        $result['is_file_based'] = false;
    }

    return $result;
}

/**
 * Fetch HTML from file system
 */
function fetchHtmlFromFile($relativePath) {
    // Remove leading slash if present
    $relativePath = ltrim($relativePath, '/');

    // Build full path
    $fullPath = CONTENT_BASE_PATH . '/' . $relativePath;

    // Security: Prevent directory traversal
    $realPath = realpath($fullPath);
    $basePath = realpath(CONTENT_BASE_PATH);

    if (!$realPath || strpos($realPath, $basePath) !== 0) {
        error_log('Invalid content path: ' . $fullPath);
        throw new Exception('Invalid content path');
    }

    // Check if file exists
    if (!file_exists($realPath)) {
        error_log('Content file not found: ' . $realPath);
        throw new Exception('Content file not found');
    }

    // Read and return file contents
    $html = file_get_contents($realPath);

    if ($html === false) {
        error_log('Failed to read content file: ' . $realPath);
        throw new Exception('Failed to read content file');
    }

    return $html;
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

    // Check if original_content_id is provided (for customized content)
    $hasOriginalId = !empty($data['original_content_id']);

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
                email_attachment_content," .
                ($hasOriginalId ? " original_content_id," : "") . "
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
                :email_attachment_content," .
                ($hasOriginalId ? " :original_content_id," : "") . "
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

    // Add original_content_id if provided
    if ($hasOriginalId) {
        $params['original_content_id'] = $data['original_content_id'];
    }

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
