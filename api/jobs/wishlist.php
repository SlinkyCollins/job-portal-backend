<?php
require_once __DIR__ . '/../../config/middleware.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

// Get POST JSON data
$data = json_decode(file_get_contents("php://input"));
$job_id = $data->jobId ?? null;

if (!$job_id) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Job ID is required.']);
    exit;
}

// Prevent duplicate saved jobs
$checkQuery = "SELECT id FROM saved_jobs_table WHERE job_id = ? AND user_id = ?";
$checkStmt = $dbconnection->prepare($checkQuery);
$checkStmt->bind_param('ii', $job_id, $user_id);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(['status' => false, 'msg' => 'You have already saved this job.', 'isSaved' => true]);
    exit;
}
$checkStmt->close();

// Insert into DB
$query = "INSERT INTO saved_jobs_table (job_id, user_id) VALUES (?, ?)";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('ii', $job_id, $user_id);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode([
        'status' => true,
        'msg' => 'Added to Wishlist!',
        'isSaved' => true
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'msg' => 'Failed to save job. Please try again later.',
        'isSaved' => false
    ]);
}

$stmt->close();
$dbconnection->close();
?>