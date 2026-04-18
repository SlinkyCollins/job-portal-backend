<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

// Validate JWT and require admin role
$user = validateJWT('admin');
$user_id = $user['user_id'];

$query = "SELECT user_id, firstname, lastname, email, role FROM users_table WHERE user_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$execute = $stmt->execute();

if ($execute) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        apiResponse(true, 'Admin dashboard loaded successfully.', 200, ['user' => $user_data]);
    } else {
        apiResponse(false, 'No user data found. Please contact support if you believe this is an error.', 404);
    }
} else {
    apiResponse(false, 'An error occurred while retrieving your data. Please try again later.', 500);
}
$dbconnection->close();
?>
