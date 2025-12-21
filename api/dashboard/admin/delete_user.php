<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('admin');
if ($user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['status' => false, 'message' => 'Access Denied']);
    exit;
}

$input = json_decode(file_get_contents('php://input'));
$target_id = $input->user_id ?? null;

if (!$target_id) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'User ID required']);
    exit;
}

// Safety: Prevent deleting other Admins via API
$check = $dbconnection->prepare("SELECT role FROM users_table WHERE user_id = ?");
$check->bind_param('i', $target_id);
$check->execute();
$res = $check->get_result();
if ($row = $res->fetch_assoc()) {
    if ($row['role'] === 'admin') {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Cannot delete other admins']);
        exit;
    }
} else {
    http_response_code(404);
    echo json_encode(['status' => false, 'message' => 'User not found']);
    exit;
}

// Execute Delete
// Thanks to ON DELETE CASCADE, this removes their profile, jobs, applications, etc.
$stmt = $dbconnection->prepare("DELETE FROM users_table WHERE user_id = ?");
$stmt->bind_param('i', $target_id);

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'message' => 'User deleted successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Delete failed']);
}
?>