<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT(); // Works for ANY role
$user_id = $user['user_id'];

$data = json_decode(file_get_contents('php://input'));
$firstname = trim($data->firstName ?? '');
$lastname = trim($data->lastName ?? '');

$errors = [];

// 1. Validation (Name only)
if (empty($firstname) || strlen($firstname) < 2) $errors[] = 'First name is required.';
if (empty($lastname) || strlen($lastname) < 2) $errors[] = 'Last name is required.';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'errors' => $errors]);
    exit;
}

try {
    $updateUser = $dbconnection->prepare("UPDATE users_table SET firstname = ?, lastname = ? WHERE user_id = ?");
    $updateUser->bind_param('ssi', $firstname, $lastname, $user_id);
    
    if ($updateUser->execute()) {
        echo json_encode(['status' => true, 'msg' => 'Account updated successfully.']);
    } else {
        throw new Exception("Update failed: " . $updateUser->error);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Error updating account.', 'error' => $e->getMessage()]);
}
?>