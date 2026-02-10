<?php
// live_tracking_api.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

$host = 'localhost';
$user = 'root';
$password = '';
$database = 'bus_information';

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Connection failed: ' . $conn->connect_error]));
}

// Get live location of a specific bus
if (isset($_GET['action']) && $_GET['action'] == 'getBusLocation' && isset($_GET['bus_id'])) {
    $bus_id = (int)$_GET['bus_id'];
    
    $sql = "SELECT ll.*, b.bus_no, b.route_description, d.driver_name, 
                   bs.current_passengers, bs.status as bus_status, bs.temperature,
                   bs.next_stop_id
            FROM live_locations ll
            JOIN buses b ON ll.bus_id = b.bus_id
            JOIN bus_drivers d ON ll.driver_id = d.driver_id
            LEFT JOIN bus_status bs ON ll.bus_id = bs.bus_id
            WHERE ll.bus_id = ? 
            ORDER BY ll.timestamp DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $bus_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Get next stop name
        if ($row['next_stop_id']) {
            $stop_sql = "SELECT stop_name FROM bus_stops WHERE stop_id = ?";
            $stop_stmt = $conn->prepare($stop_sql);
            $stop_stmt->bind_param("i", $row['next_stop_id']);
            $stop_stmt->execute();
            $stop_result = $stop_stmt->get_result();
            if ($stop_row = $stop_result->fetch_assoc()) {
                $row['next_stop_name'] = $stop_row['stop_name'];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No live data available']);
    }
}

// Get all active buses with live locations
elseif (isset($_GET['action']) && $_GET['action'] == 'getAllActiveBuses') {
    $sql = "SELECT b.bus_id, b.bus_no, b.route_description, 
                   ll.latitude, ll.longitude, ll.speed, ll.timestamp,
                   d.driver_name, bs.current_passengers, bs.status as bus_status,
                   bs.next_stop_id, bs.temperature
            FROM buses b
            JOIN live_locations ll ON b.bus_id = ll.bus_id
            JOIN bus_drivers d ON ll.driver_id = d.driver_id
            LEFT JOIN bus_status bs ON b.bus_id = bs.bus_id
            WHERE ll.timestamp >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            AND d.status = 'active'
            GROUP BY b.bus_id
            ORDER BY ll.timestamp DESC";
    
    $result = $conn->query($sql);
    $buses = [];
    
    while($row = $result->fetch_assoc()) {
        // Get next stop name
        if ($row['next_stop_id']) {
            $stop_sql = "SELECT stop_name FROM bus_stops WHERE stop_id = ?";
            $stop_stmt = $conn->prepare($stop_sql);
            $stop_stmt->bind_param("i", $row['next_stop_id']);
            $stop_stmt->execute();
            $stop_result = $stop_stmt->get_result();
            if ($stop_row = $stop_result->fetch_assoc()) {
                $row['next_stop_name'] = $stop_row['stop_name'];
            }
        }
        
        $buses[] = $row;
    }
    
    echo json_encode(['success' => true, 'buses' => $buses, 'count' => count($buses)]);
}

// Get location history
elseif (isset($_GET['action']) && $_GET['action'] == 'getLocationHistory' && isset($_GET['bus_id'])) {
    $bus_id = (int)$_GET['bus_id'];
    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 1;
    
    $sql = "SELECT latitude, longitude, speed, timestamp
            FROM live_locations
            WHERE bus_id = ? 
            AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ORDER BY timestamp ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $bus_id, $hours);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $locations = [];
    while($row = $result->fetch_assoc()) {
        $locations[] = $row;
    }
    
    echo json_encode(['success' => true, 'locations' => $locations]);
}

$conn->close();
?>