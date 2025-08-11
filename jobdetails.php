<?php
require 'connect.php';
require_once 'headers.php';
session_start();

$jobId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$jobId) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Job ID is required']);
    exit();
}

// Get job details
$query = "SELECT job_id, job_title, job_description, employer_id, location, salary, job_type, qualifications, deadline, createdat 
          FROM jobs_table WHERE job_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $jobId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['status' => false, 'msg' => 'Job not found']);
    exit();
}

$job = $result->fetch_assoc();
$job['qualifications'] = json_decode($job['qualifications'], true);
$job['hasApplied'] = false; // default

// If logged in and role is job_seeker, check if already applied
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'job_seeker') {
    $user_id = $_SESSION['user']['id'];
    $checkQuery = "SELECT application_id FROM applications_table WHERE job_id = ? AND seeker_id = ?";
    $checkStmt = $dbconnection->prepare($checkQuery);
    $checkStmt->bind_param('ii', $jobId, $user_id);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $job['hasApplied'] = true;
    }
    $checkStmt->close();
}

// If logged in and role is job_seeker, check if job is already saved
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'job_seeker') {
    $user_id = $_SESSION['user']['id'];
    $checkQuery = "SELECT id FROM saved_jobs_table WHERE job_id = ? AND user_id = ?";
    $checkStmt = $dbconnection->prepare($checkQuery);
    $checkStmt->bind_param('ii', $jobId, $user_id);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $job['isSaved'] = true;
    }
    $checkStmt->close();
}

// Send final JSON once
echo json_encode(['status' => true, 'job' => $job]);

$stmt->close();
$dbconnection->close();