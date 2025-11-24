<?php
require_once __DIR__ . '/../../config/middleware.php';

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
    echo json_encode(['status' => true, 'user' => $user_data]);
} else {
    http_response_code(404);
    echo json_encode(['status' => false, 'msg' => 'User not found']);
}

$dbconnection->close();
?>