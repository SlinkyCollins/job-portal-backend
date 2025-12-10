<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$response = [];

$query = "SELECT user_id, firstname, lastname, email, role FROM users_table WHERE user_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$execute = $stmt->execute();

if ($execute) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $response = [
            'status' => true,
            'user' => $user_data
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
?>