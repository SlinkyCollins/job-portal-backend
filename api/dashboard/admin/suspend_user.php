<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$user_id = $input->user_id ?? null;
$action = $input->action ?? null;  // 'suspend' or 'unsuspend'

if (!$user_id || !in_array($action, ['suspend', 'unsuspend'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid request']);
    exit;
}

// Prevent suspending other admins
$checkRole = $dbconnection->prepare("SELECT role FROM users_table WHERE user_id = ?");
$checkRole->bind_param('i', $user_id);
$checkRole->execute();
$result = $checkRole->get_result();
if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'User not found']);
    exit;
}
$user = $result->fetch_assoc();
if ($user['role'] === 'admin') {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Cannot suspend other admins']);
    exit;
}

$value = $action === 'suspend' ? 1 : 0;
$stmt = $dbconnection->prepare("UPDATE users_table SET suspended = ? WHERE user_id = ?");
$stmt->bind_param('ii', $value, $user_id);

if ($stmt->execute()) {
    $message = $action === 'suspend' ? 'suspended' : 'unsuspended';
    echo json_encode(['status' => true, 'message' => 'User ' . $message . ' successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to update user']);
}
?>