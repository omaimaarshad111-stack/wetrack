<?php
// database.php
require_once 'config.php';

class Database {
    private static $conn = null;
    
    public static function connect() {
        if (self::$conn === null) {
            try {
                self::$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                
                if (self::$conn->connect_error) {
                    error_log("Database connection failed: " . self::$conn->connect_error);
                    // Throw the real mysqli error for easier debugging in non-production
                    throw new Exception(self::$conn->connect_error);
                }
                
                self::$conn->set_charset("utf8mb4");
                self::$conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
                
            } catch (Exception $e) {
                // Log error but don't expose details to user
                error_log("Database error: " . $e->getMessage());
                if (ENVIRONMENT === 'production') {
                    die("Database connection error. Please try again later.");
                } else {
                    die("Database error: " . $e->getMessage());
                }
            }
        }
        return self::$conn;
    }
    
    public static function query($sql, $params = [], $types = '') {
        $conn = self::connect();
        
        if (empty($types) && !empty($params)) {
            $types = str_repeat('s', count($params));
        }
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log("SQL Prepare failed: " . $conn->error . " | Query: " . $sql);
            return false;
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        if (!$stmt->execute()) {
            error_log("SQL Execute failed: " . $stmt->error);
            return false;
        }

        // For SELECT queries mysqli->get_result() returns a result object.
        // For INSERT/UPDATE/DELETE it returns false, but the execution may still be successful.
        $res = $stmt->get_result();
        if ($res === false) {
            // Non-SELECT successful query
            return true;
        }

        return $res;
    }
    
    public static function fetchAll($sql, $params = [], $types = '') {
        $result = self::query($sql, $params, $types);
        if (!$result) return [];
        
        $data = [];
        while($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }
    
    public static function fetchOne($sql, $params = [], $types = '') {
        $result = self::query($sql, $params, $types);
        if (!$result) return null;
        
        return $result->fetch_assoc();
    }
    
    public static function insert($table, $data) {
        $keys = array_keys($data);
        $values = array_values($data);
        $placeholders = str_repeat('?,', count($values) - 1) . '?';
        $types = str_repeat('s', count($values));
        
        $sql = "INSERT INTO $table (" . implode(',', $keys) . ") VALUES ($placeholders)";
        $result = self::query($sql, $values, $types);
        
        return $result ? self::connect()->insert_id : false;
    }
}
?>