<?php

require __DIR__ . '/vendor/autoload.php'; // Composer's autoloader

// Load .env only if it exists (optional for local dev)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

// --- CONFIG ---
$host = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$db   = $_ENV['DB_NAME'];
$caCert = __DIR__ . '/certs/ca.pem'; // keep your CA cert in /certs/

// --- MYSQLI CONNECTION WITH SSL ---
$dbconnection = mysqli_init();
if (!$dbconnection) {
    error_log('mysqli_init failed');
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Database init failed']);
    exit;
}

$dbconnection->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
$dbconnection->ssl_set(null, null, $caCert, null, null);

if (!$dbconnection->real_connect($host, $user, $pass, $db, $port, null, MYSQLI_CLIENT_SSL)) {
    error_log('DB Connection Error: ' . mysqli_connect_error());
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Database connection failed']);
    exit;
}