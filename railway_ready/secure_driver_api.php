<?php
// secure_driver_api.php
require_once 'api_middleware.php';
require_once 'database.php';

APIMiddleware::cors();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Rate limit by IP for driver registration
if ($action === 'register') {
    APIMiddleware::rateLimit($_SERVER['REMOTE_ADDR'], 10); // 10 requests per minute
}

if ($method === 'POST' && $action === 'register') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Input validation
    $phone = Auth::sanitizeInput($input['phone_number'] ?? '');
    $name = Auth::sanitizeInput($input['driver_name'] ?? '');
    $bus_no = Auth::sanitizeInput($input['bus_no'] ?? '');
    
    if (empty($phone) || empty($name) || empty($bus_no)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    // Validate phone number
    if (!preg_match('/^[0-9]{11}$/', $phone)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid phone number']);
        exit;
    }
    
    // Check if bus exists
    $bus = Database::fetchOne("SELECT bus_id FROM buses WHERE bus_no = ?", [$bus_no]);
    
    if (!$bus) {
        echo json_encode(['success' => false, 'message' => 'Bus not found']);
        exit;
    }
    
    // Check if driver exists
    $existing = Database::fetchOne(
        "SELECT driver_id FROM bus_drivers WHERE phone_number = ?",
        [$phone]
    );
    
    if ($existing) {
        // Update existing driver
        Database::query(
            "UPDATE bus_drivers SET bus_id = ?, driver_name = ?, status = 'active' WHERE driver_id = ?",
            [$bus['bus_id'], $name, $existing['driver_id']],
            'isi'
        );
        
        $driver_id = $existing['driver_id'];
    } else {
        // Create new driver
        $driver_id = Database::insert('bus_drivers', [
            'bus_id' => $bus['bus_id'],
            'driver_name' => $name,
            'phone_number' => $phone,
            'license_number' => 'DL' . time() . rand(1000, 9999),
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    if ($driver_id) {
        // Generate JWT token for this driver
        $token = APIMiddleware::generateJWT([
            'driver_id' => $driver_id,
            'bus_id' => $bus['bus_id'],
            'phone' => $phone
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Driver registered',
            'driver_id' => $driver_id,
            'bus_id' => $bus['bus_id'],
            'token' => $token
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
}

// Add token validation for other endpoints
elseif ($method === 'POST' && $action === 'update_location') {
    $driver_id = APIMiddleware::validateDriverToken();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $lat = floatval($input['latitude'] ?? 0);
    $lng = floatval($input['longitude'] ?? 0);
    
    // Validate coordinates
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
        exit;
    }
    
    // Insert location with prepared statement
    $result = Database::query(
        "INSERT INTO live_locations (driver_id, bus_id, latitude, longitude, speed, timestamp) 
         SELECT ?, bus_id, ?, ?, ?, NOW() FROM bus_drivers WHERE driver_id = ? 
         ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), speed = VALUES(speed), timestamp = NOW()",
        [$driver_id, $lat, $lng, $input['speed'] ?? 0, $driver_id],
        'idddi'
    );
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Location updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
}
elseif ($action === 'health') {
    echo json_encode(['success' => true, 'status' => 'API is running']);
}
?>