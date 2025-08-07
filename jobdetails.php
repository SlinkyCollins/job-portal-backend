<?php
require 'connect.php';
require_once 'headers.php';

$jobId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$jobId) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Job ID is required']);
    exit();
}

$query = "SELECT job_id, job_title, job_description, employer_id, location, salary, job_type, qualifications, deadline, createdat FROM jobs_table WHERE job_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $jobId);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $job = $result->fetch_assoc();
    $job['qualifications'] = json_decode($job['qualifications'], true);
    echo json_encode(['status' => true, 'job' => $job]);
} else {
    http_response_code(404);
    echo json_encode(['status' => false, 'msg' => 'Job not found']);
}

$dbconnection->close();