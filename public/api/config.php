<?php
/**
 * Configuration file for Customization Portal
 * Loads environment variables and sets up application configuration
 */

// Enable error reporting for development
// TODO: Disable in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Load environment variables from .env file if it exists
if (file_exists(__DIR__ . '/../../.env')) {
    $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            $value = trim($value, '"\'');

            // Set as environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Database configuration
define('DB_TYPE', getenv('DB_TYPE') ?: 'pgsql'); // pgsql or mysql
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: (DB_TYPE === 'mysql' ? '3306' : '5432'));
define('DB_NAME', getenv('DB_NAME') ?: 'customization_portal');
define('DB_USER', getenv('DB_USER') ?: (DB_TYPE === 'mysql' ? 'root' : 'postgres'));
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_SCHEMA', getenv('DB_SCHEMA') ?: 'global'); // PostgreSQL schema (not used for MySQL)

// Okta configuration
define('OKTA_DOMAIN', getenv('OKTA_DOMAIN') ?: '');
define('OKTA_CLIENT_ID', getenv('OKTA_CLIENT_ID') ?: '');
define('OKTA_ISSUER', getenv('OKTA_ISSUER') ?: '');

// Application configuration
define('APP_URL', getenv('APP_URL') ?: 'http://localhost');
define('API_URL', getenv('API_URL') ?: 'http://localhost/api');

// Content configuration
define('CONTENT_BASE_PATH', getenv('CONTENT_BASE_PATH') ?: '/var/www/html/content');

// CORS configuration
define('CORS_ALLOWED_ORIGINS', getenv('CORS_ALLOWED_ORIGINS') ?: '*');

// JWT configuration
define('JWT_ALGORITHM', 'RS256');
define('JWT_CACHE_TIME', 3600); // Cache JWKS for 1 hour

/**
 * Get Okta JWKS URL
 */
function getOktaJwksUrl() {
    $issuer = OKTA_ISSUER;
    if (empty($issuer)) {
        $issuer = 'https://' . OKTA_DOMAIN . '/oauth2/default';
    }
    return $issuer . '/v1/keys';
}

/**
 * Get full database DSN
 */
function getDatabaseDsn() {
    if (DB_TYPE === 'mysql') {
        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
    } else {
        return sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME
        );
    }
}

/**
 * Get table name with schema (for PostgreSQL) or without (for MySQL)
 */
function getTableName($table) {
    if (DB_TYPE === 'mysql') {
        return $table;
    } else {
        return DB_SCHEMA . '.' . $table;
    }
}
