<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];

$query = "SELECT 
            u.firstname, 
            u.lastname, 
            u.email, 
            e.employer_role, 
            e.profile_pic_url 
          FROM users_table u
          LEFT JOIN employers_table e ON u.user_id = e.user_id
          WHERE u.user_id = ?";

$stmt = $dbconnection->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode(["status" => true, "data" => $row]);
} else {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "User not found"]);
}
?>