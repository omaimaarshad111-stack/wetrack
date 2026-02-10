<?php
// error_handler.php
require_once 'config.php';

class ErrorHandler {
    public static function init() {
        if (ENVIRONMENT === 'production') {
            error_reporting(0);
            ini_set('display_errors', 0);
            ini_set('log_errors', 1);
            // Ensure logs directory exists before setting error_log
            $logDir = __DIR__ . '/logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            ini_set('error_log', $logDir . '/error.log');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
        }
        
        set_exception_handler([__CLASS__, 'handleException']);
        set_error_handler([__CLASS__, 'handleError']);
    }
    
    public static function handleException($exception) {
        self::logError('Exception', $exception->getMessage(), $exception->getFile(), $exception->getLine());
        
        if (ENVIRONMENT === 'production') {
            http_response_code(500);
            echo json_encode(['error' => 'An internal server error occurred']);
        } else {
            echo "Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine();
        }
    }
    
    public static function handleError($errno, $errstr, $errfile, $errline) {
        self::logError('Error', $errstr, $errfile, $errline);
        
        if (ENVIRONMENT === 'production') {
            return false; // Let PHP handle it
        }
        return true; // Display in development
    }
    
    private static function logError($type, $message, $file, $line) {
        $logMessage = sprintf(
            "[%s] %s: %s in %s on line %d\nIP: %s\nURL: %s\n\n",
            date('Y-m-d H:i:s'),
            $type,
            $message,
            $file,
            $line,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        );
        
        // Ensure logs directory exists
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        error_log($logMessage, 3, $logDir . '/error.log');
    }
}

ErrorHandler::init();
?>