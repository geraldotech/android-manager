<?php

/* 
{
  "device": "a55-01",
  "battery": {
    "level": 49,
    "temperature": 25.9,
    "currentMa": -155
  },
  "network": {
    "wifiSsid": "Router 5G",
    "ip": "10.247.104.4",
    "wifiSignal": -57
  },
  "system": {
    "androidVersion": "14",
    "androidSdk": 34
  },
  "timestamp": "2026-08-23 11:25:37"
}
*/

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]);

    exit;
}

$config = require dirname(__DIR__) . '/config/database.php';

date_default_timezone_set($config['php_timezone'] ?? 'America/Sao_Paulo');

try {

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['database'],
        $config['charset']
    );

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $pdo->exec(
        'SET time_zone = ' . $pdo->quote($config['database_timezone'] ?? '-03:00')
    );

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed'
    ]);

    exit;
}

$rawBody = file_get_contents('php://input');

$data = json_decode($rawBody, true);

if (
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($data)
) {
    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON'
    ]);

    exit;
}

$deviceKey = $data['device'] ?? null;

if (!$deviceKey) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'error' => 'Field "device" is required'
    ]);

    exit;
}

try {

    $sql = "
        INSERT INTO device_status_logs
        (
            device_key,
            payload
        )
        VALUES
        (
            :device_key,
            :payload
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':device_key' => $deviceKey,
        ':payload' => $rawBody
    ]);

    $id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'message' => 'Device status received',
        'id' => (int) $id,
        'device' => $deviceKey,
        'receivedAt' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Failed to save device status'
    ]);

}
