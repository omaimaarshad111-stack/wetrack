<?php
// admin_api.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

require_once 'Database.php';
require_once 'api_middleware.php';
require_once 'auth.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

APIMiddleware::cors();
APIMiddleware::rateLimit($_SERVER['REMOTE_ADDR']);
Auth::checkAdmin();

// Get request method and input data
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);
$action = $_GET['action'] ?? $input['action'] ?? '';

try {
    switch($action) {
        // Get all buses with detailed info
        case 'getAllBuses':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            getAllBuses();
            break;
            
        // Send message/alert to driver
        case 'sendAlert':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            sendAlert($input);
            break;
            
        // Update bus status (admin override)
        case 'updateBusStatus':
            if ($method !== 'POST') {
                throw new Exception('Method not allowed', 405);
            }
            updateBusStatus($input);
            break;
            
        // Get system statistics
        case 'getStatistics':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            getStatistics();
            break;
            
        // Export data
        case 'exportData':
            if ($method !== 'GET') {
                throw new Exception('Method not allowed', 405);
            }
            exportData();
            break;
            
        default:
            http_response_code(404);
            echo json_encode([
                'success' => false, 
                'message' => 'Action not found'
            ]);
            break;
    }
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Function definitions
function getAllBuses() {
    $sql = "SELECT 
                b.bus_id, b.bus_no, b.route_description,
                d.driver_id, d.driver_name, d.phone_number, d.status as driver_status,
                ll.latitude, ll.longitude, ll.speed, ll.timestamp as last_location_time,
                bs.current_passengers, bs.status as bus_status, bs.temperature,
                bs.next_stop_id, bs.estimated_arrival,
                (SELECT COUNT(*) FROM live_locations WHERE bus_id = b.bus_id 
                 AND timestamp >= DATE_SUB(NOW(), INTERVAL 1 HOUR)) as location_updates
            FROM buses b
            LEFT JOIN bus_drivers d ON b.bus_id = d.bus_id AND d.status = 'active'
            LEFT JOIN live_locations ll ON b.bus_id = ll.bus_id 
                AND ll.timestamp = (SELECT MAX(timestamp) FROM live_locations WHERE bus_id = b.bus_id)
            LEFT JOIN bus_status bs ON b.bus_id = bs.bus_id
            ORDER BY b.bus_no";
    
    $buses = Database::fetchAll($sql);
    
    // Get next stop names
    foreach ($buses as &$bus) {
        if ($bus['next_stop_id']) {
            $stop = Database::fetchOne(
                "SELECT stop_name FROM bus_stops WHERE stop_id = ?",
                [$bus['next_stop_id']]
            );
            $bus['next_stop_name'] = $stop['stop_name'] ?? null;
        }
    }
    
    echo json_encode([
        'success' => true, 
        'buses' => $buses,
        'count' => count($buses)
    ]);
}

function sendAlert($data) {
    $required = ['driver_id', 'message'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field", 400);
        }
    }
    
    $driver_id = (int)$data['driver_id'];
    $message = trim($data['message']);
    $type = $data['type'] ?? 'alert';
    
    // Validate driver exists
    $driver = Database::fetchOne(
        "SELECT driver_id FROM bus_drivers WHERE driver_id = ?",
        [$driver_id]
    );
    
    if (!$driver) {
        throw new Exception('Driver not found', 404);
    }
    
    $sql = "INSERT INTO driver_alerts (driver_id, message_type, message, status, created_at) 
            VALUES (?, ?, ?, 'sent', NOW())";
    
    $result = Database::query($sql, [$driver_id, $type, $message]);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Alert sent successfully',
            'alert_id' => Database::connect()->insert_id
        ]);
    } else {
        throw new Exception('Failed to send alert', 500);
    }
}

function updateBusStatus($data) {
    $required = ['bus_id', 'status'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field", 400);
        }
    }
    
    $bus_id = (int)$data['bus_id'];
    $status = trim($data['status']);
    $notes = $data['notes'] ?? '';
    
    // Validate bus exists
    $bus = Database::fetchOne(
        "SELECT bus_id FROM buses WHERE bus_id = ?",
        [$bus_id]
    );
    
    if (!$bus) {
        throw new Exception('Bus not found', 404);
    }
    
    // Check if status exists
    $check = Database::fetchOne(
        "SELECT id FROM bus_status WHERE bus_id = ?",
        [$bus_id]
    );
    
    if ($check) {
        $sql = "UPDATE bus_status SET status = ?, admin_notes = ?, updated_at = NOW() WHERE bus_id = ?";
    } else {
        $sql = "INSERT INTO bus_status (bus_id, status, admin_notes, updated_at) VALUES (?, ?, ?, NOW())";
    }
    
    $params = $check ? [$status, $notes, $bus_id] : [$bus_id, $status, $notes];
    
    $result = Database::query($sql, $params);
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Bus status updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update bus status', 500);
    }
}

function getStatistics() {
    $stats = [];
    
    // Total buses
    $result = Database::fetchOne("SELECT COUNT(*) as count FROM buses");
    $stats['total_buses'] = $result['count'] ?? 0;
    
    // Active drivers
    $result = Database::fetchOne("SELECT COUNT(*) as count FROM bus_drivers WHERE status = 'active'");
    $stats['active_drivers'] = $result['count'] ?? 0;
    
    // Today's trips
    $result = Database::fetchOne("SELECT COUNT(DISTINCT driver_id) as count FROM live_locations WHERE DATE(timestamp) = CURDATE()");
    $stats['active_today'] = $result['count'] ?? 0;
    
    // Total stops
    $result = Database::fetchOne("SELECT COUNT(*) as count FROM bus_stops");
    $stats['total_stops'] = $result['count'] ?? 0;
    
    // Average passengers
    $result = Database::fetchOne("SELECT AVG(current_passengers) as avg FROM bus_status WHERE current_passengers > 0");
    $stats['avg_passengers'] = round($result['avg'] ?? 0);
    
    // Buses by status
    $result = Database::fetchAll("
        SELECT bs.status, COUNT(*) as count 
        FROM bus_status bs 
        GROUP BY bs.status
    ");
    $stats['by_status'] = $result;
    
    echo json_encode([
        'success' => true, 
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function exportData() {
    $type = $_GET['type'] ?? 'json';
    $date = $_GET['date'] ?? date('Y-m-d');
    
    if (!in_array($type, ['json', 'csv'])) {
        throw new Exception('Invalid export type. Use json or csv', 400);
    }
    
    $data = Database::fetchAll("
        SELECT b.bus_no, d.driver_name, ll.*, bs.current_passengers, bs.status
        FROM live_locations ll
        JOIN buses b ON ll.bus_id = b.bus_id
        LEFT JOIN bus_drivers d ON ll.driver_id = d.driver_id
        LEFT JOIN bus_status bs ON ll.bus_id = bs.bus_id
        WHERE DATE(ll.timestamp) = ?
        ORDER BY ll.timestamp DESC
    ", [$date]);
    
    if ($type === 'json') {
        header('Content-Disposition: attachment; filename="bus_data_' . $date . '.json"');
        echo json_encode($data, JSON_PRETTY_PRINT);
    } elseif ($type === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="bus_data_' . $date . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }
        
        // Add data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
    }
}
?>