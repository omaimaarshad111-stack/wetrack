<?php
// route_planner.php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

require_once 'database.php';

// Calculate distance between two coordinates (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // in kilometers
    
    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);
    
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;
    
    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
    
    return $angle * $earthRadius;
}

// Find nearest bus stop with coordinates
function findNearestStop($stopName) {
    $search = "%" . $stopName . "%";
    $row = Database::fetchOne(
        "SELECT stop_id, stop_name, latitude, longitude FROM bus_stops 
         WHERE stop_name LIKE ? ORDER BY stop_name LIMIT 1",
        [$search]
    );

    return $row ?: null;
}

// Main route planning function
if (isset($_GET['action']) && $_GET['action'] == 'planRoute') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $from = isset($input['from']) ? trim($input['from']) : '';
    $to = isset($input['to']) ? trim($input['to']) : '';
    
    if (empty($from) || empty($to)) {
        echo json_encode(['success' => false, 'message' => 'Please provide both from and to locations']);
        exit;
    }
    
    // Find stops
    $fromStop = findNearestStop($from);
    $toStop = findNearestStop($to);
    
    if (!$fromStop || !$toStop) {
        echo json_encode(['success' => false, 'message' => 'Could not find one or both stops']);
        exit;
    }
    
    // Find buses that go through both stops
    $sql = "SELECT DISTINCT b.bus_id, b.bus_no, b.route_description,
            (SELECT stop_sequence FROM bus_route_stops WHERE bus_id = b.bus_id AND stop_id = ?) as from_sequence,
            (SELECT stop_sequence FROM bus_route_stops WHERE bus_id = b.bus_id AND stop_id = ?) as to_sequence
            FROM buses b
            WHERE b.bus_id IN (
                SELECT bus_id FROM bus_route_stops WHERE stop_id = ?
            ) AND b.bus_id IN (
                SELECT bus_id FROM bus_route_stops WHERE stop_id = ?
            )";
    
    $busesRaw = Database::fetchAll($sql, [
        $fromStop['stop_id'], $toStop['stop_id'], $fromStop['stop_id'], $toStop['stop_id']
    ]);

    $buses = [];
    foreach ($busesRaw as $row) {
        if (isset($row['from_sequence']) && isset($row['to_sequence']) && $row['from_sequence'] < $row['to_sequence']) {
            $buses[] = $row;
        }
    }
    
    if (empty($buses)) {
        echo json_encode(['success' => false, 'message' => 'No direct bus found between these stops']);
        exit;
    }
    
    // Get the best bus (first one for now)
    $bus = $buses[0];
    
    // Get complete route with coordinates
    $sql2 = "SELECT bs.stop_name, bs.latitude, bs.longitude, brs.stop_sequence,
                    CASE 
                        WHEN bs.stop_id = ? THEN 'start'
                        WHEN bs.stop_id = ? THEN 'end'
                        ELSE 'intermediate'
                    END as stop_type
            FROM bus_route_stops brs
            JOIN bus_stops bs ON brs.stop_id = bs.stop_id
            WHERE brs.bus_id = ?
            ORDER BY brs.stop_sequence";
    
    $routeRows = Database::fetchAll($sql2, [
        $fromStop['stop_id'], $toStop['stop_id'], $bus['bus_id']
    ]);

    $routeStops = [];
    $coordinates = [];
    foreach ($routeRows as $row) {
        $routeStops[] = $row;
        if (!empty($row['latitude']) && !empty($row['longitude'])) {
            $coordinates[] = [(float)$row['latitude'], (float)$row['longitude']];
        }
    }
    
    // Calculate distance
    $distance = calculateDistance($fromStop['latitude'], $fromStop['longitude'], 
                                  $toStop['latitude'], $toStop['longitude']);
    
    // Estimate time (assuming 20 km/h average speed)
    $estimatedMinutes = round(($distance / 20) * 60);
    
    // Prepare response
    $response = [
        'success' => true,
        'trip' => [
            'from' => $fromStop['stop_name'],
            'to' => $toStop['stop_name'],
            'bus' => $bus['bus_no'],
            'route' => $bus['route_description'],
            'distance' => round($distance, 2) . ' km',
            'estimatedTime' => $estimatedMinutes . ' minutes',
            'fromCoords' => [(float)$fromStop['latitude'], (float)$fromStop['longitude']],
            'toCoords' => [(float)$toStop['latitude'], (float)$toStop['longitude']],
            'routeCoordinates' => $coordinates,
            'routeStops' => $routeStops,
            'totalTime' => $estimatedMinutes . ' minutes',
            'steps' => [
                [
                    'type' => 'walk',
                    'description' => "Walk to {$fromStop['stop_name']}",
                    'duration' => '5 min'
                ],
                [
                    'type' => 'board',
                    'description' => "Take bus {$bus['bus_no']} to {$toStop['stop_name']}",
                    'duration' => $estimatedMinutes . ' min',
                    'bus' => $bus['bus_no']
                ],
                [
                    'type' => 'walk',
                    'description' => "Arrive at {$toStop['stop_name']}",
                    'duration' => '2 min'
                ]
            ]
        ],
        'availableBuses' => $buses
    ];
    
    echo json_encode($response);
}
?>