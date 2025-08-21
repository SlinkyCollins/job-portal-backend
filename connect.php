<?php

require __DIR__ . '/vendor/autoload.php'; // Composer's autoloader
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- CONFIG ---
$host = $_ENV['DB_HOST'];
$port = $_ENV['DB_PORT'];
$user = $_ENV['DB_USER'];
$pass = $_ENV['DB_PASS'];
$db   = $_ENV['DB_NAME'];
$caCert = __DIR__ . '/certs/ca.pem'; // keep your CA cert in /certs/

// --- MYSQLI CONNECTION WITH SSL ---
$dbconnection = mysqli_init();
if (!$dbconnection) die('mysqli_init failed');

$dbconnection->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
$dbconnection->ssl_set(NULL, NULL, $caCert, NULL, NULL);

if (!$dbconnection->real_connect($host, $user, $pass, $db, $port, NULL, MYSQLI_CLIENT_SSL)) {
    die('Connection failed: ' . mysqli_connect_error());
}

// echo "✅ Connected successfully to Aiven MySQL with SSL!";

// --- OPTIONAL TEST QUERY ---
// $result = $dbconnection->query("SHOW TABLES");
// while ($row = $result->fetch_row()) echo $row[0] . "<br>";