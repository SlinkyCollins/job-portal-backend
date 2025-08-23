<?php
require_once '../headers.php';
require '../session_config.php';
require '../connect.php';
$timeout_duration = 1800;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    http_response_code(401);
    $response = [
        'status' => false,
        'msg' => 'Your session has expired due to inactivity, please log in again'
    ];
    exit();
}

if (!isset($_SESSION['user']) || !is_array($_SESSION['user']) || empty($_SESSION['user']['id']) || empty($_SESSION['user']['role'])) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(['status' => false, 'msg' => 'Not authenticated. Please log in again.']);
    exit;
}

$_SESSION['last_activity'] = time();

$response = [];

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

if ($role !== 'employer') {
    http_response_code(403);
    $response = [
        'status' => false,
        'msg' => 'Access denied. You do not have permission to view this page.'
    ];
    exit();
}

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

echo json_encode($response);

$dbconnection->close();
