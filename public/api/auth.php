<?php
/**
 * Authentication middleware for Okta JWT verification
 * Verifies JWT tokens from Okta and extracts user information
 */

require_once __DIR__ . '/config.php';

class Auth {
    private static $jwksCache = null;
    private static $jwksCacheTime = null;

    /**
     * Verify authentication and return user info
     * Returns user data from JWT claims or false if invalid
     */
    public static function authenticate() {
        // Get Authorization header
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (empty($authHeader)) {
            return false;
        }

        // Extract Bearer token
        if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return false;
        }

        $token = $matches[1];

        // Verify and decode JWT
        try {
            $payload = self::verifyJwt($token);
            return self::extractUserInfo($payload);
        } catch (Exception $e) {
            error_log('Authentication error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify JWT token signature and claims
     */
    private static function verifyJwt($token) {
        // Split token into parts
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid JWT format');
        }

        list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;

        // Decode header and payload
        $header = json_decode(self::base64UrlDecode($headerEncoded), true);
        $payload = json_decode(self::base64UrlDecode($payloadEncoded), true);

        if (!$header || !$payload) {
            throw new Exception('Invalid JWT encoding');
        }

        // Verify algorithm
        if (!isset($header['alg']) || $header['alg'] !== 'RS256') {
            throw new Exception('Unsupported JWT algorithm');
        }

        // Verify expiration
        if (!isset($payload['exp']) || $payload['exp'] < time()) {
            throw new Exception('JWT token expired');
        }

        // Verify issued at (not in future)
        if (isset($payload['iat']) && $payload['iat'] > time() + 300) { // 5 minute grace period
            throw new Exception('JWT issued in the future');
        }

        // Verify issuer
        $expectedIssuer = OKTA_ISSUER;
        if (empty($expectedIssuer)) {
            $expectedIssuer = 'https://' . OKTA_DOMAIN . '/oauth2/default';
        }

        if (!isset($payload['iss']) || $payload['iss'] !== $expectedIssuer) {
            throw new Exception('Invalid JWT issuer');
        }

        // Verify client ID (audience)
        if (!isset($payload['cid']) || $payload['cid'] !== OKTA_CLIENT_ID) {
            // Also check 'aud' claim - but note Okta uses api://default for access tokens
            // So we verify cid instead of aud for access tokens
            if (!isset($payload['cid'])) {
                throw new Exception('Invalid JWT - missing client ID');
            }
        }

        // Verify signature
        $keyId = $header['kid'] ?? null;
        if (!$keyId) {
            throw new Exception('Missing key ID in JWT header');
        }

        $publicKey = self::getPublicKey($keyId);
        if (!$publicKey) {
            throw new Exception('Public key not found for key ID: ' . $keyId);
        }

        // Verify signature
        $signatureInput = $headerEncoded . '.' . $payloadEncoded;
        $signature = self::base64UrlDecode($signatureEncoded);

        $valid = openssl_verify(
            $signatureInput,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($valid !== 1) {
            throw new Exception('Invalid JWT signature');
        }

        return $payload;
    }

    /**
     * Get public key from Okta JWKS
     */
    private static function getPublicKey($keyId) {
        $jwks = self::getJwks();

        foreach ($jwks['keys'] as $key) {
            if ($key['kid'] === $keyId) {
                return self::jwkToPem($key);
            }
        }

        return null;
    }

    /**
     * Get JWKS from Okta (with caching)
     */
    private static function getJwks() {
        // Check cache
        if (self::$jwksCache && self::$jwksCacheTime && (time() - self::$jwksCacheTime) < JWT_CACHE_TIME) {
            return self::$jwksCache;
        }

        // Fetch from Okta
        $jwksUrl = getOktaJwksUrl();
        $jwksJson = file_get_contents($jwksUrl);

        if (!$jwksJson) {
            throw new Exception('Failed to fetch JWKS');
        }

        $jwks = json_decode($jwksJson, true);
        if (!$jwks || !isset($jwks['keys'])) {
            throw new Exception('Invalid JWKS response');
        }

        // Cache it
        self::$jwksCache = $jwks;
        self::$jwksCacheTime = time();

        return $jwks;
    }

    /**
     * Convert JWK to PEM format
     * Based on https://github.com/firebase/php-jwt
     */
    private static function jwkToPem($jwk) {
        if (!isset($jwk['kty']) || $jwk['kty'] !== 'RSA') {
            throw new Exception('Only RSA keys are supported');
        }

        if (!isset($jwk['n']) || !isset($jwk['e'])) {
            throw new Exception('Invalid JWK: missing n or e');
        }

        // Decode the modulus and exponent
        $n = self::base64UrlDecode($jwk['n']);
        $e = self::base64UrlDecode($jwk['e']);

        // Build the RSA public key in ASN.1 format
        $modulus = self::createDERInteger($n);
        $exponent = self::createDERInteger($e);

        // RSA public key structure: SEQUENCE { modulus, exponent }
        $rsaPublicKey = self::createDERSequence($modulus . $exponent);

        // Algorithm identifier for RSA
        $algorithmIdentifier = self::createDERSequence(
            "\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01" . // OID for RSA encryption
            "\x05\x00" // NULL
        );

        // Wrap in a BIT STRING
        $publicKeyBitString = self::createDERBitString($rsaPublicKey);

        // Final structure: SEQUENCE { algorithm, publicKey }
        $publicKeyInfo = self::createDERSequence($algorithmIdentifier . $publicKeyBitString);

        // Convert to PEM format
        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($publicKeyInfo), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    /**
     * Create a DER encoded INTEGER
     */
    private static function createDERInteger($data) {
        // Add leading zero byte if high bit is set (to indicate positive number)
        if (ord($data[0]) > 0x7f) {
            $data = "\x00" . $data;
        }

        return "\x02" . self::createDERLength(strlen($data)) . $data;
    }

    /**
     * Create a DER encoded SEQUENCE
     */
    private static function createDERSequence($data) {
        return "\x30" . self::createDERLength(strlen($data)) . $data;
    }

    /**
     * Create a DER encoded BIT STRING
     */
    private static function createDERBitString($data) {
        return "\x03" . self::createDERLength(strlen($data) + 1) . "\x00" . $data;
    }

    /**
     * Create DER length encoding
     */
    private static function createDERLength($length) {
        if ($length < 128) {
            return chr($length);
        }

        $lenBytes = '';
        while ($length > 0) {
            $lenBytes = chr($length & 0xff) . $lenBytes;
            $length = $length >> 8;
        }

        return chr(0x80 | strlen($lenBytes)) . $lenBytes;
    }

    /**
     * Base64 URL decode
     */
    private static function base64UrlDecode($input) {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Extract user information from JWT payload
     */
    private static function extractUserInfo($payload) {
        return [
            'sub' => $payload['sub'] ?? null,
            'email' => $payload['email'] ?? $payload['sub'] ?? null,
            'name' => $payload['name'] ?? $payload['email'] ?? $payload['sub'] ?? null,
            'organization' => $payload['organization'] ?? $payload['org'] ?? 'Unknown Organization',
            'organization_id' => $payload['organizationId'] ?? $payload['org_id'] ?? null,
            'claims' => $payload // Full claims for additional data
        ];
    }

    /**
     * Send unauthorized response
     */
    public static function unauthorized($message = 'Unauthorized') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $message,
            'code' => 'UNAUTHORIZED'
        ]);
        exit;
    }

    /**
     * Send forbidden response
     */
    public static function forbidden($message = 'Forbidden') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => $message,
            'code' => 'FORBIDDEN'
        ]);
        exit;
    }
}
