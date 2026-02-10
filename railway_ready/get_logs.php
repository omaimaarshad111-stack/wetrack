<?php
// get_logs.php
require_once 'auth.php';
Auth::checkAdmin();

$logFile = __DIR__ . '/logs/error.log';
if (file_exists($logFile)) {
    $logs = file_get_contents($logFile);
    echo nl2br(htmlspecialchars($logs));
} else {
    echo 'No logs found.';
}
?>