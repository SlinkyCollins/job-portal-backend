<?php
require_once '../headers.php';
require '../connect.php';
require_once '../middleware.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$input = json_decode(file_get_contents('php://input'));
$applicationId = $input->applicationId ?? null;

if (!$applicationId) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Application ID is required.']);
    exit;
}

// Update application status to 'retracted' (safer than deleting)
$query = "UPDATE applications_table SET status = 'retracted', retracted_at = NOW() WHERE application_id = ? AND seeker_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param("ii", $applicationId, $user_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => true, 'msg' => 'Application retracted successfully']);
    } else {
        echo json_encode(['status' => false, 'msg' => 'Application not found or already retracted']);
    }
} else {
    echo json_encode(['status' => false, 'msg' => 'Failed to retract: ' . $stmt->error]);
}

$stmt->close();
$dbconnection->close();
?>