<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/Validator.php';

$user = validateJWT(); // Any role
$user_id = $user['user_id'];

$data = json_decode(file_get_contents('php://input'));
$oldPassword = $data->oldPassword ?? '';
$newPassword = $data->newPassword ?? '';

$validator = new Validator([
    'oldPassword' => $oldPassword,
    'newPassword' => $newPassword,
]);

$validator->rule('oldPassword', 'required');
$validator->rule('newPassword', 'required|min:8');

if (!$validator->validate()) {
    http_response_code(400);
    echo json_encode(['status' => false, 'errors' => $validator->errors()]);
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
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Old password is incorrect.']);
    exit;
}

// Hash new password and update
$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$updateStmt = $dbconnection->prepare("UPDATE users_table SET password = ? WHERE user_id = ?");
$updateStmt->bind_param('si', $newHash, $user_id);
$success = $updateStmt->execute();
$updateStmt->close();

if ($success) {
    echo json_encode(['status' => true, 'message' => 'Password updated successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to update password.']);
}

$dbconnection->close();
?>
