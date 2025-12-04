<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$data = json_decode(file_get_contents('php://input'));
$oldPassword = $data->oldPassword ?? '';

if (empty($oldPassword)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Old password is required.']);
    exit;
}

$stmt = $dbconnection->prepare("SELECT password FROM users_table WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$userData = $result->fetch_assoc();
$stmt->close();

if (!$userData || !password_verify($oldPassword, $userData['password'])) {
    echo json_encode(['status' => false, 'message' => 'Old password is incorrect.']);
} else {
    echo json_encode(['status' => true, 'message' => 'Old password verified.']);
}

$dbconnection->close();
?>