# Android Manager

A PHP dashboard for viewing Android device status logs stored in MySQL. `manager.php` displays device identity, battery, network, location, system details, and expandable JSON payloads in a Portuguese interface that refreshes every 30 seconds.

Filter by device and choose how many recent records to display (100 by default, up to 500), ordered newest first.

To run it, use PHP with the PDO MySQL extension and create `config/database.php` returning an array with `host`, `database`, `username`, and `password`. Optional settings are `charset` (default `utf8mb4`), `php_timezone` (default `America/Sao_Paulo`), and `database_timezone` (default `-03:00`).

The database must already contain a `device_status_logs` table with `id`, `device_key`, `payload` (JSON), and `received_at` columns. Status collection and insertion happen outside this dashboard. Serve the project through a PHP-capable server and open `/manager.php`.
