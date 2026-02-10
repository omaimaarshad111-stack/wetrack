<?php
// admin_login_process.php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // In development mode, allow skipping strict CSRF validation for local testing
    if (!(defined('ENVIRONMENT') && ENVIRONMENT === 'development')) {
        if (!Auth::validateCSRFToken($_POST['csrf_token'] ?? '')) {
            die('Invalid CSRF token');
        }
    }
    
    $username = Auth::sanitizeInput($_POST['username']);
    $password = Auth::sanitizeInput($_POST['password']);
    
    if (Auth::loginAdmin($username, $password)) {
        header('Location: admin_dashboard.php');
        exit;
    } else {
        header('Location: admin_login.php?error=1');
        exit;
    }
}
?>