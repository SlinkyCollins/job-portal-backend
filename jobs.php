<?php
require_once 'headers.php';
require 'connect.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;

$user_id = null;
$savedJobIds = [];

// Check for JWT token manually
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';

if ($auth && str_starts_with($auth, 'Bearer ')) {
    $jwt = str_replace('Bearer ', '', $auth);
    if (file_exists(dirname(__DIR__) . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
    }
    $key = $_ENV['JWT_SECRET'] ?? null;
    if ($key) {
        try {
            $decoded = JWT::decode($jwt, new \Firebase\JWT\Key($key, 'HS256'));
            $user_id = $decoded->user_id ?? null;
        } catch (Exception $e) {
            // Invalid token, treat as guest
            $user_id = null;
        }
    }
}

// If logged in, get saved jobs
if ($user_id) {
    $savedQuery = "SELECT job_id FROM saved_jobs_table WHERE user_id = ?";
    $savedStmt = $dbconnection->prepare($savedQuery);
    $savedStmt->bind_param('i', $user_id);
    $savedStmt->execute();
    $savedResult = $savedStmt->get_result();
    while ($row = $savedResult->fetch_assoc()) {
        $savedJobIds[] = $row['job_id'];
    }
    $savedStmt->close();
}

$query = "SELECT job_id, title, location, salary_amount, currency, salary_duration, created_at FROM jobs_table ORDER BY created_at DESC";
$result = $dbconnection->query($query);

if ($result && $result->num_rows > 0) {
    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $row['isSaved'] = $user_id ? in_array($row['job_id'], $savedJobIds) : false;
        $jobs[] = $row;
    }
    $response = [
        'status' => true,
        'jobs' => $jobs
    ];
} else {
    $response = [
        'status' => false,
        'msg' => 'No jobs found'
    ];
}

echo json_encode($response);
$dbconnection->close();