<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];
$data = json_decode(file_get_contents("php://input"));

if (empty($data->firstname) || empty($data->lastname)) {
    apiResponse(false, 'Name fields are required.', 400);
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

    apiResponse(true, 'Profile updated successfully.');

} catch (Exception $e) {
    apiResponse(false, 'An error occurred while updating the profile.', 500);
}
?>
