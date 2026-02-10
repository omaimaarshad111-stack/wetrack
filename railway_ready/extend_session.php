<?php
// extend_session.php
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::validateCSRFToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $_SESSION['last_activity'] = time();
    echo json_encode(['success' => true]);
    exit;
}
?>