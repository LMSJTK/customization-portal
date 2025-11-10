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
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    echo "   Status: ✓ Found\n";

    // Try to load it
    require_once __DIR__ . '/config.php';

    echo "   DB_HOST: " . (defined('DB_HOST') ? DB_HOST : 'NOT SET') . "\n";
    echo "   DB_NAME: " . (defined('DB_NAME') ? DB_NAME : 'NOT SET') . "\n";
    echo "   DB_USER: " . (defined('DB_USER') ? DB_USER : 'NOT SET') . "\n";
    echo "   OKTA_DOMAIN: " . (defined('OKTA_DOMAIN') ? OKTA_DOMAIN : 'NOT SET') . "\n";
    echo "   OKTA_CLIENT_ID: " . (defined('OKTA_CLIENT_ID') ? (OKTA_CLIENT_ID ? 'SET' : 'EMPTY') : 'NOT SET') . "\n\n";
} else {
    echo "   Status: ✗ NOT FOUND\n";
    echo "   Action: Copy .env.example to .env and configure it\n\n";
}

// Check PDO PostgreSQL extension
echo "3. PDO PostgreSQL Extension:\n";
if (extension_loaded('pdo_pgsql')) {
    echo "   Status: ✓ Installed\n\n";
} else {
    echo "   Status: ✗ NOT INSTALLED\n";
    echo "   Action: Install with: sudo apt-get install php-pgsql (Ubuntu/Debian)\n";
    echo "           or: sudo yum install php-pgsql (CentOS/RHEL)\n\n";
}

// Check other required extensions
echo "4. Other Required Extensions:\n";
$required = ['openssl', 'json', 'pdo'];
foreach ($required as $ext) {
    echo "   $ext: " . (extension_loaded($ext) ? "✓" : "✗") . "\n";
}
echo "\n";

// Test database connection
echo "5. Database Connection Test:\n";
if (file_exists($envPath) && extension_loaded('pdo_pgsql')) {
    try {
        require_once __DIR__ . '/config.php';

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        echo "   Connection: ✓ SUCCESS\n";

        // Test if content table exists
        $stmt = $pdo->query("SELECT COUNT(*) FROM global.content");
        $count = $stmt->fetchColumn();
        echo "   Content table: ✓ EXISTS\n";
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
} else {
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
