<?php
require_once 'headers.php';
require 'connect.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;

$user_id = null;
$savedJobIds = [];

// ------------------- AUTH CHECK -------------------
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
            $user_id = null; // guest
        }
    }
}

// ------------------- SAVED JOBS -------------------
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

// ------------------- SEARCH FILTERS -------------------
$location = $_GET['location'] ?? null;
$category = $_GET['category'] ?? null; // category id
$keyword = $_GET['keyword'] ?? null;

// Base query (JOIN categories for category name)
$sql = "SELECT j.job_id, j.title, j.location, j.salary_amount, j.currency, j.salary_duration, 
               j.created_at, j.employment_type,
               c.name AS category_name,
               co.id AS company_id, co.name AS company_name, co.logo_url AS company_logo
        FROM jobs_table j
        LEFT JOIN categories c ON j.category_id = c.id
        LEFT JOIN companies co ON j.company_id = co.id
        WHERE 1=1";

$params = [];
$types = "";

// Location filter
if (!empty($location)) {
    $sql .= " AND j.location LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}

// Category filter (match by category id)
if (!empty($category) && ctype_digit($category)) {
    $sql .= " AND j.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

// Keyword filter (search across title/overview/description)
if (!empty($keyword)) {
    $sql .= " AND (j.title LIKE ? OR j.overview LIKE ? OR j.description LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $types .= "sss";
}

$sql .= " ORDER BY j.created_at DESC";

$stmt = $dbconnection->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// ------------------- RESPONSE -------------------
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