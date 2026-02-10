<?php
// config.php
require_once 'env_loader.php';
session_start();

// Environment configuration
define('ENVIRONMENT', 'development'); // Change to 'production' for live

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('DB_NAME') ?: 'bus_information');

// Security configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('API_RATE_LIMIT', 60); // Requests per minute
define('CORS_ALLOWED_ORIGINS', getenv('CORS_ORIGINS') ?: 'https://localhost:3000,https://yourdomain.com');

// JWT Secret (for API authentication)
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-secret-key-here-change-in-production');

// Check if HTTPS is used
if (ENVIRONMENT === 'production' && !isset($_SERVER['HTTPS'])) {
    die('HTTPS is required in production');
}
?>