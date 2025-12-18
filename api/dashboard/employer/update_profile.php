<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];
$data = json_decode(file_get_contents("php://input"));

if (empty($data->firstname) || empty($data->lastname)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Name fields are required."]);
    exit;
}

try {
    // 1. Update User Basic Info
    $query1 = "UPDATE users_table SET firstname = ?, lastname = ? WHERE user_id = ?";
    $stmt1 = $dbconnection->prepare($query1);
    $stmt1->bind_param("ssi", $data->firstname, $data->lastname, $user_id);
    $stmt1->execute();

    // 2. Update Employer Role
    $query2 = "UPDATE employers_table SET employer_role = ? WHERE user_id = ?";
    $stmt2 = $dbconnection->prepare($query2);
    $stmt2->bind_param("si", $data->employer_role, $user_id);
    $stmt2->execute();

    echo json_encode(["status" => true, "message" => "Profile updated successfully."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>