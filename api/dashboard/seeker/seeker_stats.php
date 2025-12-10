<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$shortlisted = 0;
$applied = 0;

// Get shortlisted count (jobs where user was shortlisted)
$shortlistedQuery = "SELECT COUNT(*) as count FROM applications_table WHERE seeker_id = ? AND status = 'shortlisted'";
if ($stmt = $dbconnection->prepare($shortlistedQuery)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $shortlisted = $res ? (int) $res->fetch_assoc()['count'] : 0;
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'DB prepare error (shortlisted)']);
    exit;
}

// Get applied jobs count
$appliedQuery = "SELECT COUNT(*) as count FROM applications_table WHERE seeker_id = ? AND status != 'retracted'";
if ($stmt = $dbconnection->prepare($appliedQuery)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $applied = $res ? (int) $res->fetch_assoc()['count'] : 0;
    $stmt->close();
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'DB prepare error (applied)']);
    exit;
}

echo json_encode(['status' => true, 'shortlisted' => $shortlisted, 'appliedJobs' => $applied]);

$dbconnection->close();