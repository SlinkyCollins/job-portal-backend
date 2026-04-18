<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/Validator.php';
require_once __DIR__ . '/../../../config/password_response.php';

$user = validateJWT(); // Any role
$user_id = $user['user_id'];

$data = json_decode(file_get_contents('php://input'));
$oldPassword = $data->oldPassword ?? '';

$validator = new Validator([
    'oldPassword' => $oldPassword,
]);
$validator->labels([
    'oldPassword' => 'Current password',
]);
$validator->rule('oldPassword', 'required');

if (!$validator->validate()) {
    passwordResponse(false, 'Validation failed.', 400, $validator->errors());
    exit;
}

$stmt = $dbconnection->prepare("SELECT password FROM users_table WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData || !password_verify($oldPassword, $userData['password'])) {
    passwordResponse(false, 'Current password is incorrect.', 400);
} else {
    passwordResponse(true, 'Current password verified.');
}

$dbconnection->close();
?>
