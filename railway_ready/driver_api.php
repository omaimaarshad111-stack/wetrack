<?php

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

require_once 'database.php';

// Use Database wrapper (Database::connect() will initialize mysqli internally)
try {
    Database::connect();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed', 'error' => $e->getMessage()]);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// SIMPLIFIED LOGIN/REGISTER
if ($method === 'POST' && $action === 'register') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $phone = $input['phone_number'] ?? '';
    $name = $input['driver_name'] ?? '';
    $bus_no = $input['bus_no'] ?? '';
    
    // Get bus_id from bus number
    $busRow = Database::fetchOne("SELECT bus_id FROM buses WHERE bus_no = ? LIMIT 1", [$bus_no]);
    if ($busRow) {
        $bus_id = $busRow['bus_id'];

        // Check if driver exists
        $driverRow = Database::fetchOne("SELECT driver_id FROM bus_drivers WHERE phone_number = ?", [$phone]);

        if ($driverRow) {
            // Driver exists - update
            Database::query("UPDATE bus_drivers SET bus_id = ?, driver_name = ?, status = 'active' WHERE driver_id = ?", [$bus_id, $name, $driverRow['driver_id']], 'isi');

            echo json_encode([
                'success' => true,
                'message' => 'Driver updated',
                'driver_id' => $driverRow['driver_id'],
                'bus_id' => $bus_id
            ]);
        } else {
            // New driver
            $license = 'DL' . time();
            $newId = Database::insert('bus_drivers', [
                'bus_id' => $bus_id,
                'driver_name' => $name,
                'phone_number' => $phone,
                'license_number' => $license
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Driver registered',
                'driver_id' => $newId,
                'bus_id' => $bus_id
            ]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Bus not found']);
    }
}

// SIMPLIFIED GET DRIVER
elseif ($method === 'GET' && $action === 'getDriver') {
    $phone = $_GET['phone'] ?? '';
    
    $row = Database::fetchOne(
        "SELECT d.*, b.bus_no, b.route_description 
         FROM bus_drivers d JOIN buses b ON d.bus_id = b.bus_id 
         WHERE d.phone_number = ?",
        [$phone]
    );

    if ($row) {
        echo json_encode(['success' => true, 'driver' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
    }
}

// UPDATE LOCATION
elseif ($method === 'POST' && $action === 'update_location') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $driver_id = $input['driver_id'] ?? 0;
    $lat = $input['latitude'] ?? 0;
    $lng = $input['longitude'] ?? 0;
    
    // Get bus_id from driver
    $busRow = Database::fetchOne("SELECT bus_id FROM bus_drivers WHERE driver_id = ?", [$driver_id]);
    if ($busRow) {
        $bus_id = $busRow['bus_id'];

        // Insert location (update existing row if unique constraint exists)
        Database::query(
            "INSERT INTO live_locations (driver_id, bus_id, latitude, longitude) VALUES (?, ?, ?, ?) 
             ON DUPLICATE KEY UPDATE latitude = VALUES(latitude), longitude = VALUES(longitude), timestamp = NOW()",
            [$driver_id, $bus_id, $lat, $lng],
            'iidd'
        );

        // Update driver last active if column exists; otherwise try 'updated_at'
        // SHOW ... LIKE does not accept prepared placeholders on some MariaDB/MySQL setups.
        $mysqli = Database::connect();
        $col1 = $mysqli->real_escape_string('last_active');
        $hasLastActive = Database::fetchOne("SHOW COLUMNS FROM bus_drivers LIKE '$col1'");
        if ($hasLastActive) {
            Database::query("UPDATE bus_drivers SET last_active = NOW() WHERE driver_id = ?", [$driver_id], 'i');
        } else {
            $col2 = $mysqli->real_escape_string('updated_at');
            $hasUpdatedAt = Database::fetchOne("SHOW COLUMNS FROM bus_drivers LIKE '$col2'");
            if ($hasUpdatedAt) {
                Database::query("UPDATE bus_drivers SET updated_at = NOW() WHERE driver_id = ?", [$driver_id], 'i');
            }
        }

        echo json_encode(['success' => true, 'message' => 'Location updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Driver not found']);
    }
}

// HEALTH CHECK
elseif ($action === 'health') {
    echo json_encode(['success' => true, 'status' => 'API is running']);
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>