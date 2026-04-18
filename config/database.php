<?php

require __DIR__ . '/../vendor/autoload.php';  // Composer's autoloader 
require_once __DIR__ . '/api_response.php';

// Load .env only if it exists (optional for local dev)
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();
}

// --- ENVIRONMENT SWITCH ---
$env = $_ENV['ENV'] ?? 'local'; // Default to local if not set

if ($env === 'production') {
    // Aiven (production) settings
    $host = $_ENV['DB_HOST'];
    $port = $_ENV['DB_PORT'];
    $user = $_ENV['DB_USER'];
    $pass = $_ENV['DB_PASS'];
    $db   = $_ENV['DB_NAME'];
    $caCert = __DIR__ . '/../certs/ca.pem'; // SSL cert for Aiven
    $useSSL = true;
} else {
    // Local (XAMPP) settings
    $host = $_ENV['DB_HOST_LOCAL'];
    $port = $_ENV['DB_PORT_LOCAL'];
    $user = $_ENV['DB_USER_LOCAL'];
    $pass = $_ENV['DB_PASS_LOCAL'];
    $db   = $_ENV['DB_NAME_LOCAL'];
    $caCert = null; // No SSL for local
    $useSSL = false;
}

// --- MYSQLI CONNECTION ---
$dbconnection = mysqli_init();
if (!$dbconnection) {
    apiResponse(false, 'Database init failed', 500);
    exit;
}

if ($useSSL) {
    $dbconnection->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    $dbconnection->ssl_set(null, null, $caCert, null, null);
    $connectFlags = MYSQLI_CLIENT_SSL;
} else {
    $connectFlags = 0; // No SSL for local
}

if (!$dbconnection->real_connect($host, $user, $pass, $db, $port, null, $connectFlags)) {
    apiResponse(false, 'Database connection failed', 500);
    exit;
};