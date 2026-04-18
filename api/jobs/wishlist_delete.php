<?php
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/api_response.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

// Get DELETE JSON data
$data = json_decode(file_get_contents("php://input"));
$job_id = $data->jobId ?? null;

if (!$job_id) {
    apiResponse(false, 'Job ID is required.', 400);
    exit;
}

$query = "DELETE FROM saved_jobs_table WHERE user_id = ? AND job_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('ii', $user_id, $job_id);

if ($stmt->execute()) {
    apiResponse(true, 'Removed from Wishlist!', 200, ['isSaved' => false]);
} else {
    apiResponse(false, 'Failed to remove job. Please try again later.', 500);
}

$stmt->close();
$dbconnection->close();
?>