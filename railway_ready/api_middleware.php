<?php
// api_middleware.php
require_once 'config.php';
require_once 'auth.php';
// Cache helper is used by rate limiting; ensure it's loaded
require_once 'cache.php';

class APIMiddleware {
    
    public static function cors() {
        $allowed_origins = explode(',', CORS_ALLOWED_ORIGINS);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowed_origins)) {
            header("Access-Control-Allow-Origin: $origin");
        } else if (ENVIRONMENT === 'development') {
            header("Access-Control-Allow-Origin: *");
        }
        
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
        header('Access-Control-Allow-Credentials: true');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }
    
    public static function rateLimit($key, $limit = API_RATE_LIMIT) {
        $cache_key = "rate_limit_$key";
        $current = Cache::get($cache_key, 0);
        
        if ($current >= $limit) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            exit;
        }
        
        Cache::set($cache_key, $current + 1, 60); // Reset every minute
        header("X-RateLimit-Limit: $limit");
        header("X-RateLimit-Remaining: " . ($limit - $current - 1));
    }
    
    public static function validateAPIKey() {
        $api_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
        
        if (empty($api_key)) {
            http_response_code(401);
            echo json_encode(['error' => 'API key required']);
            exit;
        }
        
        // Validate API key against database
        $valid = Database::fetchOne(
            "SELECT id FROM api_keys WHERE api_key = ? AND is_active = 1 AND expires_at > NOW()",
            [$api_key]
        );
        
        if (!$valid) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }
        
        return true;
    }
    
    public static function validateDriverToken() {
        // Try common places for Authorization header (PHP/Apache often differs)
        $tokenHeader = '';
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $tokenHeader = $_SERVER['HTTP_AUTHORIZATION'];
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $tokenHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (!empty($headers['Authorization'])) {
                $tokenHeader = $headers['Authorization'];
            } elseif (!empty($headers['authorization'])) {
                $tokenHeader = $headers['authorization'];
            }
        }

        $token = str_replace('Bearer ', '', $tokenHeader);
        
        if (empty($token)) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }
        
        // Decode JWT or validate session token
        try {
            $payload = self::decodeJWT($token);
            return $payload['driver_id'];
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid token']);
            exit;
        }
    }
    
    public static function decodeJWT($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new Exception('Invalid token format');
        }
        
        $payload = json_decode(base64_decode($parts[1]), true);
        $signature = hash_hmac('sha256', "$parts[0].$parts[1]", JWT_SECRET, true);
        $signature_base64 = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if (!hash_equals($signature_base64, $parts[2])) {
            throw new Exception('Invalid token signature');
        }
        
        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new Exception('Token expired');
        }
        
        return $payload;
    }
    
    public static function generateJWT($data) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode(array_merge($data, [
            'iat' => time(),
            'exp' => time() + (24 * 60 * 60) // 24 hours
        ]));
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }
}
?>