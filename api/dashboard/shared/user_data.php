<?php
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/api_response.php';

// Validate JWT (no specific role required)
$user = validateJWT();
$user_id = $user['user_id'];

$query = "SELECT user_id, firstname, lastname, email, role FROM users_table WHERE user_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    apiResponse(true, 'User data retrieved successfully.', 200, ['user' => $user_data]);
} else {
    apiResponse(false, 'User not found', 404);
}

$dbconnection->close();
?>