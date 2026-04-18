<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/Validator.php';

$user = validateJWT('admin');
$user_id = $user['user_id'];

$input = json_decode(file_get_contents('php://input'));
$current_password = $input->current_password ?? '';
$new_password = $input->new_password ?? '';

$validator = new Validator([
    'current_password' => $current_password,
    'new_password' => $new_password,
]);

$validator->rule('current_password', 'required');
$validator->rule('new_password', 'required|min:' . Validator::PASSWORD_MIN_LENGTH);

if (!$validator->validate()) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => $validator->firstError()]);
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
        http_response_code(403); 
    echo json_encode(['status' => false, 'message' => 'Current password is incorrect']);
    exit;
}

// 2. Update to New Password
$hashed = password_hash($new_password, PASSWORD_DEFAULT);

$updateStmt = $dbconnection->prepare("UPDATE users_table SET password = ? WHERE user_id = ?");
$updateStmt->bind_param('si', $hashed, $user_id);

if ($updateStmt->execute()) {
    echo json_encode(['status' => true, 'message' => 'Password updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Update failed']);
}
?>
