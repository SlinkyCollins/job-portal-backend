<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$user_id = $input->user_id ?? null;
$action = $input->action ?? null;  // 'suspend' or 'unsuspend'

if (!$user_id || !in_array($action, ['suspend', 'unsuspend'])) {
    apiResponse(false, 'Invalid request', 400);
    exit;
}

// Prevent suspending other admins
$checkRole = $dbconnection->prepare("SELECT role FROM users_table WHERE user_id = ?");
$checkRole->bind_param('i', $user_id);
$checkRole->execute();
$result = $checkRole->get_result();
if ($result->num_rows === 0) {
    apiResponse(false, 'User not found', 404);
    exit;
}
$user = $result->fetch_assoc();
if ($user['role'] === 'admin') {
    apiResponse(false, 'Cannot suspend other admins', 403);
    exit;
}

$value = $action === 'suspend' ? 1 : 0;
$stmt = $dbconnection->prepare("UPDATE users_table SET suspended = ? WHERE user_id = ?");
$stmt->bind_param('ii', $value, $user_id);

if ($stmt->execute()) {
    $message = $action === 'suspend' ? 'suspended' : 'unsuspended';
    apiResponse(true, 'User ' . $message . ' successfully', 200);
} else {
    apiResponse(false, 'Failed to update user', 500);
}
?>