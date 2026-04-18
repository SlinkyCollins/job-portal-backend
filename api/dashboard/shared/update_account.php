<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/Validator.php';

$user = validateJWT(); // Works for ANY role
$user_id = $user['user_id'];

$data = json_decode(file_get_contents('php://input'));
$firstname = trim($data->firstName ?? '');
$lastname = trim($data->lastName ?? '');

$validator = new Validator([
    'firstName' => $firstname,
    'lastName' => $lastname,
]);

$validator->rule('firstName', 'required|min:2');
$validator->rule('lastName', 'required|min:2');

if (!$validator->validate()) {
    http_response_code(400);
    echo json_encode(['status' => false, 'errors' => $validator->all()]);
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
