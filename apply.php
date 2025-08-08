<?php
require 'connect.php';
require_once 'headers.php';
session_start();

// Check login
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => false, 'msg' => 'Please log in to apply for jobs']);
    exit();
}

// Check role
$user_id = $_SESSION['user']['id'];
$role = $_SESSION['user']['role'];
if ($role !== 'job_seeker') {
    http_response_code(403);
    echo json_encode(['status' => false, 'msg' => 'Only job seekers can apply for jobs.']);
    exit();
}

// Get POST JSON data
$data = json_decode(file_get_contents("php://input"));
$job_id = $data->jobId ?? null;

if (!$job_id) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Job ID is required.']);
    exit();
}

// Insert into DB
$query = "INSERT INTO applications_table (job_id, seeker_id, status) 
          VALUES (?, ?, 'pending')";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('ii', $job_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'msg' => 'Application submitted successfully!']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Failed to submit application. Please try again later.']);
}

$stmt->close();
$dbconnection->close();
