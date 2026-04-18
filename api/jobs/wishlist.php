<?php
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/api_response.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

// Get POST JSON data
$data = json_decode(file_get_contents("php://input"));
$job_id = $data->jobId ?? null;

if (!$job_id) {
    apiResponse(false, 'Job ID is required.', 400);
    exit;
}

// Prevent duplicate saved jobs
$checkQuery = "SELECT id FROM saved_jobs_table WHERE job_id = ? AND user_id = ?";
$checkStmt = $dbconnection->prepare($checkQuery);
$checkStmt->bind_param('ii', $job_id, $user_id);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    apiResponse(false, 'You have already saved this job.', 400);
    exit;
}
$checkStmt->close();

// Insert into DB
$query = "INSERT INTO saved_jobs_table (job_id, user_id) VALUES (?, ?)";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('ii', $job_id, $user_id);

if ($stmt->execute()) {
    apiResponse(true, 'Added to Wishlist!', 200, ['isSaved' => true]);
} else {
    apiResponse(false, 'Failed to save job. Please try again later.', 500);
}

$stmt->close();
$dbconnection->close();
?>