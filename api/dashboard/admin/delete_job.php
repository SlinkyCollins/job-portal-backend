<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

// 1. Security Check (Middleware handles 403)
$user = validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$job_id = $input->job_id ?? null;

if (!$job_id) {
    apiResponse(false, 'Job ID required', 400);
    exit;
}

// 2. Delete Job
$stmt = $dbconnection->prepare("DELETE FROM jobs_table WHERE job_id = ?");
$stmt->bind_param('i', $job_id);

if ($stmt->execute()) {
    apiResponse(true, 'Job deleted successfully', 200);
} else {
    apiResponse(false, 'Failed to delete job', 500);
}
?>