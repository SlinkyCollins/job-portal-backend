<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

$user = validateJWT(); // Works for ANY role
$user_id = $user['user_id'];

try {
    $query = "SELECT firstname, lastname, email, linked_providers, password, createdat FROM users_table WHERE user_id = ?";
    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Check if password column is not empty/null
        $hasPassword = !empty($row['password']);

        apiResponse(true, 'Settings retrieved successfully', 200, [
            "firstname" => $row['firstname'],
            "lastname" => $row['lastname'],
            "email" => $row['email'],
            "created_at" => $row['createdat'],
            "linked_providers" => json_decode($row['linked_providers'], true), // Decode JSON for frontend
            "has_password" => $hasPassword 
        ]);
    } else {
        apiResponse(false, "User not found", 404);
    }
} catch (Exception $e) {
    apiResponse(false, $e->getMessage(), 500);
}
?>