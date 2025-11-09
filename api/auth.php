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
        if (!isset($header['alg']) || $header['alg'] !== JWT_ALGORITHM) {
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
            // Also check 'aud' claim
            if (!isset($payload['aud']) || $payload['aud'] !== OKTA_CLIENT_ID) {
                throw new Exception('Invalid JWT audience');
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
                return self::createPublicKeyFromJwk($key);
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
     * Create public key from JWK
     */
    private static function createPublicKeyFromJwk($jwk) {
        if (!isset($jwk['n']) || !isset($jwk['e'])) {
            throw new Exception('Invalid JWK format');
        }

        // Decode modulus and exponent
        $n = self::base64UrlDecode($jwk['n']);
        $e = self::base64UrlDecode($jwk['e']);

        // Create RSA public key
        $rsa = [
            'n' => new \phpseclib3\Math\BigInteger($n, 256),
            'e' => new \phpseclib3\Math\BigInteger($e, 256)
        ];

        // For now, we'll use openssl directly
        // In production, you might want to use a JWT library like firebase/php-jwt

        // Convert to PEM format
        $modulus = base64_encode($n);
        $exponent = base64_encode($e);

        // Build the public key in PEM format
        $pem = self::createPemFromModulusAndExponent($modulus, $exponent);

        return openssl_pkey_get_public($pem);
    }

    /**
     * Create PEM format public key from modulus and exponent
     */
    private static function createPemFromModulusAndExponent($modulus, $exponent) {
        // This is a simplified version
        // In production, use a proper JWT library

        $modulusBin = base64_decode($modulus);
        $exponentBin = base64_decode($exponent);

        // Build ASN.1 structure
        $rsa = chr(0x30) . self::encodeLength(strlen($modulusBin) + strlen($exponentBin) + 10) .
               chr(0x02) . self::encodeLength(strlen($modulusBin)) . $modulusBin .
               chr(0x02) . self::encodeLength(strlen($exponentBin)) . $exponentBin;

        $rsaInfo = chr(0x30) . chr(0x0d) .
                   chr(0x06) . chr(0x09) . chr(0x2a) . chr(0x86) . chr(0x48) . chr(0x86) .
                   chr(0xf7) . chr(0x0d) . chr(0x01) . chr(0x01) . chr(0x01) .
                   chr(0x05) . chr(0x00);

        $bitString = chr(0x03) . self::encodeLength(strlen($rsa) + 1) . chr(0x00) . $rsa;

        $sequence = chr(0x30) . self::encodeLength(strlen($rsaInfo) + strlen($bitString)) .
                    $rsaInfo . $bitString;

        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($sequence), 64);
        $pem .= "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    /**
     * Encode length for ASN.1
     */
    private static function encodeLength($length) {
        if ($length <= 0x7f) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), chr(0));
        return pack('Ca*', 0x80 | strlen($temp), $temp);
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
            'email' => $payload['email'] ?? null,
            'name' => $payload['name'] ?? $payload['email'] ?? null,
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
