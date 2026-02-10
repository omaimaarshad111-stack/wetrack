<?php
// auth.php
require_once 'config.php';

class Auth {
    public static function checkAdmin() {
        if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
            // If this looks like an API request, return JSON 401 instead of redirect
            $isApi = false;
            $script = $_SERVER['SCRIPT_NAME'] ?? '';
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (stripos($script, 'api') !== false || stripos($uri, '/api') !== false) {
                $isApi = true;
            }
            if (stripos($accept, 'application/json') !== false || stripos($contentType, 'application/json') !== false) {
                $isApi = true;
            }

            if ($isApi) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Unauthorized']);
                exit;
            }

            header('Location: admin_login.php');
            exit;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            self::logout();
        }
        $_SESSION['last_activity'] = time();
    }
    
    public static function checkDriver() {
        if (!isset($_SESSION['driver_id']) || !isset($_SESSION['driver_token'])) {
            return false;
        }
        return true;
    }
    
    public static function loginAdmin($username, $password) {
        // In production, use hashed passwords from database
        $admin_username = getenv('ADMIN_USER') ?: 'admin';
        $admin_password_plain = getenv('ADMIN_PASS') ?: 'admin123';
        $admin_password_hash = password_hash($admin_password_plain, PASSWORD_DEFAULT);

        // Accept either a direct plaintext match (useful for env default) or a password_verify against a hash
        if ($username === $admin_username && ($password === $admin_password_plain || password_verify($password, $admin_password_hash))) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['last_activity'] = time();
            $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            $_SESSION['admin_name'] = $admin_username;
            return true;
        }
        return false;
    }
    
    public static function logout() {
        session_destroy();
        header('Location: index.php');
        exit;
    }
    
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('self::sanitizeInput', $data);
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}
?>