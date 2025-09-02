<?php
require_once '../headers.php';
require '../connect.php';
require '../vendor/autoload.php';
use Firebase\JWT\JWT;

if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

$key = $_ENV('JWT_SECRET');

try {
    $decoded = JWT::decode($jwt, new \Firebase\JWT\Key($key, 'HS256'));
    $user_id = $decoded->user_id;
    $role = $decoded->role;
    if ($role !== 'job_seeker') {
        http_response_code(403);
        echo json_encode(['status' => false, 'msg' => 'Access denied. You do not have permission to view this page.']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['status' => false, 'msg' => 'Token expired. Log in again']);
    exit;
}

$response = [];

// Prepare and execute query
$query = "SELECT user_id, firstname, lastname, email, role FROM users_table WHERE user_id= ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$execute = $stmt->execute();

if ($execute) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $response = [
            'status' => true,
            'user' => $user
        ];
    } else {
        http_response_code(404);
        $response = ['status' => false, 'msg' => 'No user data found. Please contact support if you believe this is an error.'];
    }
} else {
    http_response_code(500);
    $response = ['status' => false, 'msg' => 'An error occurred while retrieving your data. Please try again later.'];
}

// Send ALL response as JSON at once
echo json_encode($response);

// Close the database connection
$dbconnection->close();
