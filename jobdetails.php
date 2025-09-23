<?php
require_once 'headers.php';
require 'connect.php';
require 'vendor/autoload.php';
use Firebase\JWT\JWT;

$jobId = isset($_GET['id']) ? intval($_GET['id']) : null;

if (!$jobId) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Job ID is required']);
    exit();
}

if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
}

if (empty($_ENV['JWT_SECRET'])) {
    error_log('Missing JWT_SECRET in .env');
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Server configuration error']);
    exit;
}

// Validate JWT (Optional for job details)
$key = $_ENV['JWT_SECRET'];
$user_id = null;
$role = null;
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';

if ($auth && str_starts_with($auth, 'Bearer ')) {
    $jwt = str_replace('Bearer ', '', $auth);
    try {
        $decoded = JWT::decode($jwt, new \Firebase\JWT\Key($key, 'HS256'));
        $user_id = $decoded->user_id;
        $role = $decoded->role;
    } catch (Exception $e) {
        // Ignore invalid token; treat as unauthenticated
        $user_id = null;
        $role = null;
    }
}

// Get job details
$query = "SELECT * FROM jobs_table WHERE job_id = ?";
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
$job['hasApplied'] = false;
$job['isSaved'] = false;

// If logged in and role is job_seeker, check if already applied or saved
if ($user_id && $role === 'job_seeker') {
    $checkQuery = "SELECT application_id FROM applications_table WHERE job_id = ? AND seeker_id = ?";
    $checkStmt = $dbconnection->prepare($checkQuery);
    $checkStmt->bind_param('ii', $jobId, $user_id);
    $checkStmt->execute();
    $checkStmt->store_result();
    if ($checkStmt->num_rows > 0) {
        $job['hasApplied'] = true;
    }
    $checkStmt->close();

    // Check if job is already saved
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

echo json_encode(['status' => true, 'job' => $job]);

$stmt->close();
$dbconnection->close();