<?php

declare(strict_types=1);

$configFile = __DIR__ . '/config/database.php';

if (!file_exists($configFile)) {
    http_response_code(500);
    exit('config/database.php não encontrado.');
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
} catch (PDOException $e) {
    http_response_code(500);
    exit('Falha ao conectar ao banco.');
}

$deviceFilter = trim($_GET['device'] ?? '');
$limit = (int) ($_GET['limit'] ?? 100);
$limit = max(10, min($limit, 500));

$sql = "
    SELECT
        id,
        device_key,
        payload,
        received_at
    FROM device_status_logs
";

$params = [];

if ($deviceFilter !== '') {
    $sql .= " WHERE device_key = :device_key ";
    $params[':device_key'] = $deviceFilter;
}

$sql .= " ORDER BY received_at DESC, id DESC LIMIT {$limit}";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$devices = $pdo
    ->query("
        SELECT DISTINCT device_key
        FROM device_status_logs
        ORDER BY device_key
    ")
    ->fetchAll(PDO::FETCH_COLUMN);

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function nested(array $data, array $path, $default = null)
{
    foreach ($path as $key) {
        if (!is_array($data) || !array_key_exists($key, $data)) {
            return $default;
        }

        $data = $data[$key];
    }

    return $data;
}

function displayDeviceName(array $payload, string $deviceKey): string
{
    $manufacturer = nested($payload, ['identity', 'manufacturer'], '');
    $model = nested($payload, ['identity', 'model'], '');

    if ($manufacturer || $model) {
        return trim($manufacturer . ' ' . $model);
    }

    return $deviceKey;
}

function batteryClass($level): string
{
    if (!is_numeric($level)) {
        return 'neutral';
    }

    $level = (float) $level;

    if ($level <= 20) {
        return 'danger';
    }

    if ($level <= 40) {
        return 'warning';
    }

    return 'success';
}

function ageLabel(string $date): string
{
    try {
        $now = new DateTimeImmutable();
        $received = new DateTimeImmutable($date);
        $diff = max(0, $now->getTimestamp() - $received->getTimestamp());

        if ($diff < 60) {
            return $diff . 's atrás';
        }

        if ($diff < 3600) {
            return floor($diff / 60) . 'min atrás';
        }

        if ($diff < 86400) {
            return floor($diff / 3600) . 'h atrás';
        }

        return floor($diff / 86400) . 'd atrás';
    } catch (Throwable $e) {
        return '';
    }
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="30">

    <title>Device Manager</title>

    <style>
        :root {
            color-scheme: dark;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: #0b0d10;
            color: #eef2f7;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #0b0d10;
        }

        .container {
            width: min(1500px, calc(100% - 32px));
            margin: 32px auto;
        }

        header {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            align-items: flex-end;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0;
            font-size: 28px;
        }

        .subtitle {
            margin: 6px 0 0;
            color: #8993a1;
            font-size: 14px;
        }

        .filters {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        select,
        button {
            background: #15191f;
            color: #eef2f7;
            border: 1px solid #2a3039;
            border-radius: 8px;
            padding: 9px 12px;
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        button:hover {
            background: #1d222a;
        }

        .summary {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }

        .summary-card {
            background: #12161b;
            border: 1px solid #222831;
            border-radius: 10px;
            padding: 12px 16px;
            min-width: 150px;
        }

        .summary-card strong {
            display: block;
            font-size: 21px;
        }

        .summary-card span {
            color: #8993a1;
            font-size: 12px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid #222831;
            border-radius: 12px;
            background: #101419;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1150px;
        }

        th,
        td {
            padding: 13px 14px;
            text-align: left;
            border-bottom: 1px solid #20262e;
            vertical-align: top;
        }

        th {
            color: #8f99a7;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            background: #13181e;
            position: sticky;
            top: 0;
        }

        td {
            font-size: 14px;
        }

        tbody tr:hover {
            background: #151a20;
        }

        .device-name {
            font-weight: 700;
        }

        .device-key {
            display: block;
            color: #778291;
            font-size: 11px;
            margin-top: 4px;
            max-width: 240px;
            overflow-wrap: anywhere;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            border: 1px solid #39414c;
            border-radius: 999px;
            padding: 4px 8px;
            font-size: 12px;
            white-space: nowrap;
        }

        .success {
            border-color: #26643f;
            background: #132a1c;
        }

        .warning {
            border-color: #6c5726;
            background: #2a2414;
        }

        .danger {
            border-color: #74313a;
            background: #30171b;
        }

        .neutral {
            background: #191e25;
        }

        .muted {
            color: #8993a1;
        }

        .network div,
        .location div {
            margin-bottom: 3px;
        }

        a {
            color: #8db9ff;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        details {
            min-width: 120px;
        }

        summary {
            cursor: pointer;
            color: #9dc0ff;
        }

        pre {
            width: min(700px, 70vw);
            max-height: 420px;
            overflow: auto;
            margin: 10px 0 0;
            padding: 12px;
            background: #090b0e;
            border: 1px solid #272e37;
            border-radius: 8px;
            font-size: 12px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .empty {
            padding: 50px;
            text-align: center;
            color: #8993a1;
        }

        .age {
            display: block;
            color: #74b48a;
            margin-top: 4px;
            font-size: 12px;
        }

        @media (max-width: 700px) {
            .container {
                width: min(100% - 18px, 1500px);
                margin-top: 16px;
            }

            h1 {
                font-size: 23px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <header>
        <div>
            <h1>Device Manager</h1>
            <p class="subtitle">
                Últimos status recebidos · atualização automática a cada 30 segundos
            </p>
        </div>

        <form class="filters" method="get">
            <select name="device">
                <option value="">Todos os devices</option>

                <?php foreach ($devices as $device): ?>
                    <option
                        value="<?= h($device) ?>"
                        <?= $deviceFilter === $device ? 'selected' : '' ?>
                    >
                        <?= h($device) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="limit">
                <?php foreach ([25, 50, 100, 250, 500] as $option): ?>
                    <option
                        value="<?= $option ?>"
                        <?= $limit === $option ? 'selected' : '' ?>
                    >
                        <?= $option ?> registros
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Filtrar</button>
        </form>
    </header>

    <div class="summary">
        <div class="summary-card">
            <strong><?= count($rows) ?></strong>
            <span>registros exibidos</span>
        </div>

        <div class="summary-card">
            <strong><?= count($devices) ?></strong>
            <span>devices registrados</span>
        </div>
    </div>

    <div class="table-wrap">

        <?php if (!$rows): ?>

            <div class="empty">
                Nenhum status encontrado.
            </div>

        <?php else: ?>

            <table>
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Device</th>
                    <th>Bateria</th>
                    <th>Rede</th>
                    <th>Localização</th>
                    <th>Sistema</th>
                    <th>Recebido</th>
                    <th>Payload</th>
                </tr>
                </thead>

                <tbody>

                <?php foreach ($rows as $row): ?>

                    <?php
                    $payload = json_decode($row['payload'], true);

                    if (!is_array($payload)) {
                        $payload = [];
                    }

                    $batteryLevel = nested($payload, ['battery', 'level']);
                    $batteryTemp = nested($payload, ['battery', 'temperatureC']);
                    $batteryCurrent = nested($payload, ['battery', 'currentMa']);

                    $ssid = nested($payload, ['network', 'wifiSsid']);
                    $ip = nested($payload, ['network', 'ipAddress']);
                    $wifiSignal = nested($payload, ['network', 'wifiSignalDbm']);
                    $cell = nested($payload, ['network', 'cellConnectionType']);

                    $lat = nested($payload, ['location', 'latitude']);
                    $lng = nested($payload, ['location', 'longitude']);
                    $accuracy = nested($payload, ['location', 'accuracyMeters']);
                    $mapLink = nested($payload, ['location', 'mapLink']);

                    $android = nested($payload, ['system', 'androidVersion']);
                    $sdk = nested($payload, ['system', 'androidSdk']);
                    $uptime = nested($payload, ['system', 'uptime']);
                    $storageFree = nested($payload, ['system', 'storageFree']);
                    $macroVersion = nested($payload, ['system', 'macroDroidVersion']);

                    $prettyJson = json_encode(
                        $payload,
                        JSON_PRETTY_PRINT |
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    );
                    ?>

                    <tr>

                        <td>
                            #<?= (int) $row['id'] ?>
                        </td>

                        <td>
                            <span class="device-name">
                                <?= h(displayDeviceName($payload, $row['device_key'])) ?>
                            </span>

                            <span class="device-key">
                                <?= h($row['device_key']) ?>
                            </span>
                        </td>

                        <td>
                            <span class="badge <?= batteryClass($batteryLevel) ?>">
                                <?= h((string) ($batteryLevel ?? '—')) ?>%
                            </span>

                            <div class="muted" style="margin-top:7px">
                                <?= h((string) ($batteryTemp ?? '—')) ?> °C
                            </div>

                            <div class="muted">
                                <?= h((string) ($batteryCurrent ?? '—')) ?> mA
                            </div>
                        </td>

                        <td class="network">
                            <div>
                                Wi‑Fi:
                                <strong><?= h((string) ($ssid ?? '—')) ?></strong>
                            </div>

                            <div>
                                IP:
                                <?= h((string) ($ip ?? '—')) ?>
                            </div>

                            <div>
                                Sinal:
                                <?= h((string) ($wifiSignal ?? '—')) ?> dBm
                            </div>

                            <?php if ($cell): ?>
                                <div>
                                    Mobile:
                                    <?= h((string) $cell) ?>
                                </div>
                            <?php endif; ?>
                        </td>

                        <td class="location">

                            <?php if ($lat !== null && $lng !== null): ?>

                                <div>
                                    <?= h((string) $lat) ?>,
                                    <?= h((string) $lng) ?>
                                </div>

                                <div class="muted">
                                    Precisão:
                                    <?= h((string) ($accuracy ?? '—')) ?> m
                                </div>

                                <?php if ($mapLink): ?>
                                    <div>
                                        <a
                                            href="<?= h((string) $mapLink) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >
                                            Abrir mapa
                                        </a>
                                    </div>
                                <?php endif; ?>

                            <?php else: ?>

                                <span class="muted">Sem localização</span>

                            <?php endif; ?>

                        </td>

                        <td>
                            <div>
                                Android <?= h((string) ($android ?? '—')) ?>
                                <?php if ($sdk): ?>
                                    <span class="muted">
                                        (SDK <?= h((string) $sdk) ?>)
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="muted">
                                Uptime: <?= h((string) ($uptime ?? '—')) ?>
                            </div>

                            <div class="muted">
                                Livre: <?= h((string) ($storageFree ?? '—')) ?>
                            </div>

                            <div class="muted">
                                MacroDroid: <?= h((string) ($macroVersion ?? '—')) ?>
                            </div>
                        </td>

                        <td>
                            <?= h($row['received_at']) ?>
                            <span class="age">
                                <?= h(ageLabel($row['received_at'])) ?>
                            </span>
                        </td>

                        <td>
                            <details>
                                <summary>Ver JSON</summary>
                                <pre><?= h($prettyJson ?: $row['payload']) ?></pre>
                            </details>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>
            </table>

        <?php endif; ?>

    </div>

</div>
</body>
</html>
