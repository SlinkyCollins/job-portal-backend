<?php
require '../connect.php';
session_start();

// Set session timeout (in seconds)
$timeout_duration = 1800;  // 30 minutes

// Allow CORS for Angular frontend
header('Access-Control-Allow-Origin: http://localhost:4200');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

// Check for session timeout
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

// Update last activity time
$_SESSION['last_activity'] = time();

$response = [];

$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];

// Role check
if ($role !== 'job_seeker') {
    http_response_code(403);
    $response = [
        'status' => false,
        'msg' => 'Access denied. You do not have permission to view this page.'
    ];
    exit();
}

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
?>
