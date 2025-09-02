<?php
require_once '../headers.php';
require '../connect.php';
require '../vendor/autoload.php';
use Firebase\JWT\JWT;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';
$key = $_ENV('JWT_SECRET');

if (!$auth || !str_starts_with($auth, 'Bearer ')) {
    http_response_code(401);
    echo json_encode(['status' => false, 'msg' => 'No token provided']);
    exit;
}

$jwt = str_replace('Bearer ', '', $auth);

try {
    $decoded = JWT::decode($jwt, new \Firebase\JWT\Key($key, 'HS256'));
    $user_id = $decoded->user_id;
    $role = $decoded->role;
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['status' => false, 'msg' => 'Token expired or invalid, please log in again']);
    exit;
}

// Fetch user data from database
$query = "SELECT user_id, firstname, lastname, email, role FROM users_table WHERE user_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    echo json_encode(['status' => true, 'user' => $user_data]);
} else {
    http_response_code(404);
    echo json_encode(['status' => false, 'msg' => 'User not found']);
}

$dbconnection->close();
?>