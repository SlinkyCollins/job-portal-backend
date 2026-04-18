<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$job_id = $input->job_id ?? null;
$status = $input->status ?? null;

$allowedStatuses = ['active', 'closed'];
if (!$job_id || !in_array($status, $allowedStatuses)) {
    apiResponse(false, 'Invalid job ID or status', 400);
    exit;
}

$stmt = $dbconnection->prepare("UPDATE jobs_table SET status = ? WHERE job_id = ?");
$stmt->bind_param('si', $status, $job_id);

if ($stmt->execute()) {
    apiResponse(true, 'Job status updated successfully', 200);
} else {
    apiResponse(false, 'Failed to update job status', 500);
}
$dbconnection->close();