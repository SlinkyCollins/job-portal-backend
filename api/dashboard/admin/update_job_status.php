<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$job_id = $input->job_id ?? null;
$status = $input->status ?? null;

$allowedStatuses = ['active', 'closed'];
if (!$job_id || !in_array($status, $allowedStatuses)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid job ID or status']);
    exit;
}

$stmt = $dbconnection->prepare("UPDATE jobs_table SET status = ? WHERE job_id = ?");
$stmt->bind_param('si', $status, $job_id);

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'message' => 'Job status updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to update job status']);
}
$dbconnection->close();