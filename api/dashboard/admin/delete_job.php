<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// 1. Security Check (Middleware handles 403)
$user = validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$job_id = $input->job_id ?? null;

if (!$job_id) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Job ID required']);
    exit;
}

// 2. Delete Job
$stmt = $dbconnection->prepare("DELETE FROM jobs_table WHERE job_id = ?");
$stmt->bind_param('i', $job_id);

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'message' => 'Job deleted successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to delete job']);
}
?>