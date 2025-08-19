<?php
require '../session_config.php';
require '../connect.php';
require_once '../headers.php';

header("Set-Cookie: JobNetSession=" . session_id() . "; path=/JobPortal; SameSite=None; Secure; HttpOnly");

$response = [];

if (!isset($_SESSION['user']) || empty($_SESSION['user'])) {
    http_response_code(401);
    $response = [
        'status' => false,
        'msg' => 'Session expired or invalid, please log in again'
    ];
    exit();
}

$user = $_SESSION['user'];
$user_id = $user['id'];
$role = $user['role'];

$query = "SELECT user_id, firstname, lastname, email, role FROM users_table WHERE user_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $response = [
        'status' => true,
        'user' => $user_data
    ];
} else {
    http_response_code(404);
    $response = [
        'status' => false,
        'msg' => 'User not found'
    ];
}

echo json_encode($response);

$dbconnection->close();
