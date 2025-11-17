<?php
/**
 * Brand Kit API Test
 * Simple test script to verify brand kit API functionality
 *
 * WARNING: This is for testing only. Remove or secure before production.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Brand Kit API Test</h1>";

// Get database instance
try {
    $db = Database::getInstance();
    echo "<p style='color: green;'>✓ Database connection successful</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test 1: Check if brand_kits table exists
echo "<h2>Test 1: Check brand_kits table</h2>";
try {
    $tableName = getTableName('brand_kits');
    $sql = "SELECT COUNT(*) as count FROM $tableName";
    $result = $db->queryOne($sql);
    echo "<p style='color: green;'>✓ Table '$tableName' exists with {$result['count']} records</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error accessing brand_kits table: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Make sure you've created the brand_kits table in your database.</p>";
    exit;
}

// Test 2: Insert a test brand kit
echo "<h2>Test 2: Create test brand kit</h2>";
try {
    $testId = 'test_' . uniqid();
    $testCompanyId = 'test_company_' . time();

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
        'id' => $testId,
        'company_id' => $testCompanyId,
        'primary_color' => '#4F46E5',
        'text_color' => '#FFFFFF',
        'logo_url' => null,
        'font_family' => 'Inter'
    ];

    $db->execute($sql, $params);
    echo "<p style='color: green;'>✓ Test brand kit created with ID: $testId</p>";
    echo "<pre>Company ID: $testCompanyId\nPrimary Color: #4F46E5\nText Color: #FFFFFF\nFont: Inter</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error creating brand kit: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit;
}

// Test 3: Retrieve the brand kit
echo "<h2>Test 3: Retrieve brand kit</h2>";
try {
    $sql = "SELECT * FROM $tableName WHERE company_id = :company_id LIMIT 1";
    $result = $db->queryOne($sql, ['company_id' => $testCompanyId]);

    if ($result) {
        echo "<p style='color: green;'>✓ Brand kit retrieved successfully</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } else {
        echo "<p style='color: red;'>✗ Brand kit not found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error retrieving brand kit: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 4: Update the brand kit
echo "<h2>Test 4: Update brand kit</h2>";
try {
    $sql = "UPDATE $tableName SET
                primary_color = :primary_color,
                text_color = :text_color,
                updated_at = NOW()
            WHERE company_id = :company_id";

    $params = [
        'company_id' => $testCompanyId,
        'primary_color' => '#EF4444',
        'text_color' => '#000000'
    ];

    $rowCount = $db->execute($sql, $params);

    if ($rowCount > 0) {
        echo "<p style='color: green;'>✓ Brand kit updated successfully</p>";
        echo "<p>New colors: Primary=#EF4444, Text=#000000</p>";

        // Verify update
        $updated = $db->queryOne("SELECT * FROM $tableName WHERE company_id = :company_id", ['company_id' => $testCompanyId]);
        echo "<pre>" . print_r($updated, true) . "</pre>";
    } else {
        echo "<p style='color: orange;'>⚠ No rows updated</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error updating brand kit: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 5: Delete the test brand kit
echo "<h2>Test 5: Delete test brand kit</h2>";
try {
    $sql = "DELETE FROM $tableName WHERE company_id = :company_id";
    $rowCount = $db->execute($sql, ['company_id' => $testCompanyId]);

    if ($rowCount > 0) {
        echo "<p style='color: green;'>✓ Test brand kit deleted successfully</p>";
    } else {
        echo "<p style='color: orange;'>⚠ No rows deleted</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error deleting brand kit: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Final check
echo "<h2>Final Check</h2>";
try {
    $sql = "SELECT COUNT(*) as count FROM $tableName";
    $result = $db->queryOne($sql);
    echo "<p>Total brand kits in database: {$result['count']}</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<h2>API Endpoint Testing</h2>";
echo "<p>To test the actual API endpoints with authentication, you'll need to:</p>";
echo "<ol>";
echo "<li>Log in through the application to get an Okta token</li>";
echo "<li>Use the browser console or a tool like Postman to make authenticated requests</li>";
echo "<li>Include the Authorization header with your access token</li>";
echo "</ol>";
echo "<p><strong>API Endpoints:</strong></p>";
echo "<ul>";
echo "<li><code>GET /api/brand-kit.php</code> - Get your organization's brand kit</li>";
echo "<li><code>POST /api/brand-kit.php</code> - Create a new brand kit</li>";
echo "<li><code>PUT /api/brand-kit.php</code> - Update existing brand kit</li>";
echo "<li><code>DELETE /api/brand-kit.php</code> - Delete your brand kit</li>";
echo "</ul>";

echo "<h3>Example JavaScript (run in browser console after logging in):</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>";
echo htmlspecialchars("
// Get brand kit
const response = await window.authManager.apiCall('/api/brand-kit.php', {
    method: 'GET'
});
const data = await response.json();
console.log(data);

// Update brand kit
const updateResponse = await window.authManager.apiCall('/api/brand-kit.php', {
    method: 'PUT',
    body: JSON.stringify({
        primary_color: '#4F46E5',
        text_color: '#FFFFFF',
        font_family: 'Inter'
    })
});
const updateData = await updateResponse.json();
console.log(updateData);
");
echo "</pre>";

echo "<p style='color: orange;'><strong>Note:</strong> Remember to remove or secure this test file before deploying to production!</p>";
