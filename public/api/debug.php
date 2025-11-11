<?php
/**
 * Debug script to test API connectivity and configuration
 * Access this at: http://localhost:9000/api/debug.php
 */

header('Content-Type: text/plain');

echo "=== Customization Portal API Debug ===\n\n";

// Check PHP version
echo "1. PHP Version: " . phpversion() . "\n";
echo "   Minimum required: 7.4\n";
echo "   Status: " . (version_compare(phpversion(), '7.4.0', '>=') ? "✓ OK" : "✗ FAILED") . "\n\n";

// Check if .env file exists
echo "2. Environment File (.env):\n";
$envPath = __DIR__ . '/../../.env';
if (file_exists($envPath)) {
    echo "   Status: ✓ Found\n";

    // Try to load it
    require_once __DIR__ . '/config.php';

    echo "   DB_TYPE: " . (defined('DB_TYPE') ? DB_TYPE : 'NOT SET') . "\n";
    echo "   DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT SET') . "\n";
    echo "   DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT SET') . "\n";
    echo "   DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT SET') . "\n";
    echo "   OKTA_DOMAIN: " . (defined('OKTA_DOMAIN') ? OKTA_DOMAIN : 'NOT SET') . "\n";
    echo "   OKTA_CLIENT_ID: " . (defined('OKTA_CLIENT_ID') ? (OKTA_CLIENT_ID ? 'SET' : 'EMPTY') : 'NOT SET') . "\n\n";
} else {
    echo "   Status: ✗ NOT FOUND\n";
    echo "   Action: Copy .env.example to .env and configure it\n\n";
}

// Check PDO Database Extensions
echo "3. PDO Database Extensions:\n";
echo "   pdo_pgsql: " . (extension_loaded('pdo_pgsql') ? "✓" : "✗") . "\n";
echo "   pdo_mysql: " . (extension_loaded('pdo_mysql') ? "✓" : "✗") . "\n";
if (file_exists($envPath)) {
    require_once __DIR__ . '/config.php';
    $requiredExt = DB_TYPE === 'mysql' ? 'pdo_mysql' : 'pdo_pgsql';
    echo "   Required for DB_TYPE=$DB_TYPE: $requiredExt\n";
    if (!extension_loaded($requiredExt)) {
        echo "   Action: Install $requiredExt extension\n";
    }
}
echo "\n";

// Check other required extensions
echo "4. Other Required Extensions:\n";
$required = ['openssl', 'json', 'pdo'];
foreach ($required as $ext) {
    echo "   $ext: " . (extension_loaded($ext) ? "✓" : "✗") . "\n";
}
echo "\n";

// Test database connection
echo "5. Database Connection Test:\n";
if (file_exists($envPath)) {
    require_once __DIR__ . '/config.php';

    // Check if the required PDO extension is loaded
    $requiredExtension = DB_TYPE === 'mysql' ? 'pdo_mysql' : 'pdo_pgsql';

    if (!extension_loaded($requiredExtension)) {
        echo "   Status: ✗ SKIPPED (missing $requiredExtension extension)\n\n";
    } else {
        try {
            $dsn = getDatabaseDsn();

            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            echo "   Connection: ✓ SUCCESS\n";
            echo "   Database type: " . DB_TYPE . "\n";

            // Test if content table exists
            $tableName = getTableName('content');
            $stmt = $pdo->query("SELECT COUNT(*) FROM $tableName");
            $count = $stmt->fetchColumn();
            echo "   Content table: ✓ EXISTS ($tableName)\n";
            echo "   Content count: $count items\n\n";

    } catch (PDOException $e) {
        echo "   Connection: ✗ FAILED\n";
        echo "   Error: " . $e->getMessage() . "\n";
        echo "   Code: " . $e->getCode() . "\n\n";

        if (strpos($e->getMessage(), 'could not connect to server') !== false) {
            echo "   Possible issues:\n";
            echo "   - PostgreSQL is not running\n";
            echo "   - Wrong host or port in .env\n";
            echo "   - Firewall blocking connection\n\n";
        } elseif (strpos($e->getMessage(), 'authentication failed') !== false) {
            echo "   Possible issues:\n";
            echo "   - Wrong username or password in .env\n";
            echo "   - User doesn't have access to database\n\n";
        } elseif (strpos($e->getMessage(), 'database') !== false && strpos($e->getMessage(), 'does not exist') !== false) {
            echo "   Possible issues:\n";
            echo "   - Database hasn't been created yet\n";
            echo "   - Run: CREATE DATABASE " . DB_NAME . ";\n\n";
        } elseif (strpos($e->getMessage(), 'relation') !== false && strpos($e->getMessage(), 'does not exist') !== false) {
            echo "   Possible issues:\n";
            echo "   - Content table hasn't been created\n";
            echo "   - Run the schema from README.md\n\n";
        }
    }
} } else {
    echo "   Status: ⊘ SKIPPED (missing .env or pdo_pgsql extension)\n\n";
}

// Check file permissions
echo "6. File Permissions:\n";
echo "   API directory readable: " . (is_readable(__DIR__) ? "✓" : "✗") . "\n";
echo "   config.php readable: " . (is_readable(__DIR__ . '/config.php') ? "✓" : "✗") . "\n";
echo "   auth.php readable: " . (is_readable(__DIR__ . '/auth.php') ? "✓" : "✗") . "\n";
echo "   content.php readable: " . (is_readable(__DIR__ . '/content.php') ? "✓" : "✗") . "\n\n";

// Check web server
echo "7. Web Server Info:\n";
echo "   Server Software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "\n";
echo "   Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'Unknown') . "\n";
echo "   Script Filename: " . __FILE__ . "\n\n";

echo "=== Debug Complete ===\n";
echo "\nIf you see errors above, fix them and refresh this page.\n";
echo "If all checks pass, try accessing: /api/content.php?type=emails\n";
