<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/Validator.php';
require_once __DIR__ . '/../../../config/password_response.php';

$user = validateJWT('admin');
$user_id = $user['user_id'];

$input = json_decode(file_get_contents('php://input'));
$current_password = $input->current_password ?? '';
$new_password = $input->new_password ?? '';

$validator = new Validator([
    'current_password' => $current_password,
    'new_password' => $new_password,
]);
$validator->labels([
    'current_password' => 'Current password',
    'new_password' => 'New password',
]);

$validator->rule('current_password', 'required');
$validator->rule('new_password', 'required|min:' . Validator::PASSWORD_MIN_LENGTH);

if (!$validator->validate()) {
    passwordResponse(false, 'Validation failed.', 400, $validator->errors());
    exit;
}

// 1. Verify Current Password
$stmt = $dbconnection->prepare("SELECT password FROM users_table WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$res = $stmt->get_result();
$userData = $res->fetch_assoc();
$stmt->close();

if (!$userData || !password_verify($current_password, $userData['password'])) {
    passwordResponse(false, 'Current password is incorrect.', 400);
    exit;
}

// 2. Update to New Password
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

$updateStmt = $dbconnection->prepare("UPDATE users_table SET password = ? WHERE user_id = ?");
$updateStmt->bind_param('si', $hashed, $user_id);

if ($updateStmt->execute()) {
    passwordResponse(true, 'Password updated successfully.');
} else {
    passwordResponse(false, 'Failed to update password.', 500);
}
?>
