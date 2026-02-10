<?php
header('Content-Type: application/json');
require_once 'database.php';

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'getBusRoute':
            $bus_id = (int)($_GET['bus_id'] ?? 0);
            $route = Database::fetchAll(
                "SELECT b.bus_no, b.route_description, bs.stop_name, bs.latitude, bs.longitude 
                 FROM bus_route_stops brs
                 JOIN buses b ON brs.bus_id = b.bus_id
                 JOIN bus_stops bs ON brs.stop_id = bs.stop_id
                 WHERE b.bus_id = ?
                 ORDER BY brs.stop_sequence",
                [$bus_id]
            );
            echo json_encode($route);
            break;
            
        case 'getStops':
            $stops = Database::fetchAll(
                "SELECT stop_id, stop_name, latitude, longitude 
                 FROM bus_stops ORDER BY stop_name"
            );
            echo json_encode($stops);
            break;
            
        case 'getBuses':
            $buses = Database::fetchAll(
                "SELECT bus_id, bus_no, route_description 
                 FROM buses ORDER BY bus_no"
            );
            echo json_encode($buses);
            break;
            
        case 'findBuses':
            $from = $_GET['from'] ?? '';
            $to = $_GET['to'] ?? '';
            $buses = Database::fetchAll(
                "SELECT DISTINCT b.bus_id, b.bus_no, b.route_description
                 FROM buses b
                 JOIN bus_route_stops brs1 ON b.bus_id = brs1.bus_id
                 JOIN bus_route_stops brs2 ON b.bus_id = brs2.bus_id
                 WHERE brs1.stop_id IN (SELECT stop_id FROM bus_stops WHERE stop_name LIKE ?)
                 AND brs2.stop_id IN (SELECT stop_id FROM bus_stops WHERE stop_name LIKE ?)
                 AND brs1.stop_sequence < brs2.stop_sequence",
                ["%$from%", "%$to%"]
            );
            echo json_encode($buses);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    // If environment is production, avoid leaking details
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'production') {
        echo json_encode(['error' => 'Server error']);
    } else {
        echo json_encode([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    exit;
}
?>