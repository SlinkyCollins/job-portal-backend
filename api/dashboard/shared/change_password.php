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
$newPassword = $data->newPassword ?? '';

$validator = new Validator([
    'oldPassword' => $oldPassword,
    'newPassword' => $newPassword,
]);
$validator->labels([
    'oldPassword' => 'Current password',
    'newPassword' => 'New password',
]);

$validator->rule('oldPassword', 'required');
$validator->rule('newPassword', 'required|min:' . Validator::PASSWORD_MIN_LENGTH);

if (!$validator->validate()) {
    passwordResponse(false, 'Validation failed.', 400, $validator->errors());
    exit;
}

// Fetch current password hash
$stmt = $dbconnection->prepare("SELECT password FROM users_table WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData || !password_verify($oldPassword, $userData['password'])) {
    passwordResponse(false, 'Current password is incorrect.', 400);
    exit;
}

// Hash new password and update
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$updateStmt = $dbconnection->prepare("UPDATE users_table SET password = ? WHERE user_id = ?");
$updateStmt->bind_param('si', $newHash, $user_id);
$success = $updateStmt->execute();
$updateStmt->close();

if ($success) {
    passwordResponse(true, 'Password updated successfully.');
} else {
    passwordResponse(false, 'Failed to update password.', 500);
}

$dbconnection->close();
?>
