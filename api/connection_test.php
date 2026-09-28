<?php

header('Content-Type: application/json; charset=utf-8');

$configFile = dirname(__DIR__) . '/config/database.php';


if (!file_exists($configFile)) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Arquivo config/database.php não encontrado',
        'expectedPath' => $configFile
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    exit;
}

$config = require $configFile;

date_default_timezone_set($config['php_timezone'] ?? 'America/Sao_Paulo');

try {
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['database'],
        $config['charset'] ?? 'utf8mb4'
    );

    $pdo = new PDO(
        $dsn,
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $pdo->exec(
        'SET time_zone = ' . $pdo->quote($config['database_timezone'] ?? '-03:00')
    );

    $stmt = $pdo->query('SELECT DATABASE() AS database_name, NOW() AS server_time');
    $info = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Conexão com MySQL realizada com sucesso',
        'database' => $info['database_name'] ?? null,
        'serverTime' => $info['server_time'] ?? null,
        'phpVersion' => PHP_VERSION,
        'pdoMysql' => extension_loaded('pdo_mysql')
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'error' => 'Falha na conexão com o banco de dados',
        'details' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
