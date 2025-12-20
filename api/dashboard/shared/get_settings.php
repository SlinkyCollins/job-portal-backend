<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

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

        echo json_encode([
            "status" => true,
            "data" => [
                "firstname" => $row['firstname'],
                "lastname" => $row['lastname'],
                "email" => $row['email'],
                "created_at" => $row['createdat'],
                "linked_providers" => $row['linked_providers'], // Frontend will parse JSON
                "has_password" => $hasPassword 
            ]
        ]);
    } else {
        http_response_code(404);
        echo json_encode(["status" => false, "message" => "User not found"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>