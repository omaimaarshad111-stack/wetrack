<?php
// test_connection.php - lightweight diagnostics endpoint
header('Content-Type: application/json');
require_once 'env_loader.php';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db = getenv('DB_NAME') ?: '';

$out = [
    'php_version' => phpversion(),
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? null,
    'db_host' => $host,
    'db_name' => $db
];

try {
    $dsn = "mysql:host={$host};charset=utf8";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    if ($db) {
        $pdo->query('USE ' . str_replace('`','',$db));
        $out['database'] = 'connected to ' . $db;
    } else {
        $out['database'] = 'connected';
    }
} catch (Exception $e) {
    http_response_code(500);
    $out['error'] = $e->getMessage();
}

echo json_encode($out, JSON_PRETTY_PRINT);

?>
<?php
// test_connection.php
require_once 'env_loader.php';

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$db = getenv('DB_NAME') ?: 'bus_information';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    echo "✅ Database connected successfully!<br>";
    
    // Test tables
    $tables = ['buses', 'bus_drivers', 'live_locations', 'bus_stops'];
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result->num_rows > 0) {
            echo "✅ Table '$table' exists<br>";
        } else {
            echo "❌ Table '$table' MISSING<br>";
        }
    }
    
    $conn->close();
}
?>