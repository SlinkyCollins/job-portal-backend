<?php
require_once 'middleware.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

// Get DELETE JSON data
$data = json_decode(file_get_contents("php://input"));
$job_id = $data->jobId ?? null;

if (!$job_id) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Job ID is required.']);
    exit;
}

$query = "DELETE FROM saved_jobs_table WHERE user_id = ? AND job_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('ii', $user_id, $job_id);

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'msg' => 'Removed from Wishlist!', 'isSaved' => false]);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Failed to remove job. Please try again later.']);
}

$stmt->close();
$dbconnection->close();
?>