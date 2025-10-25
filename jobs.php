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

// ------------------- FILTER PARAMS -------------------
$location = $_GET['location'] ?? null;
$category = $_GET['category'] ?? null;
$keyword = $_GET['keyword'] ?? null;

$employment_types = $_GET['employment_type'] ?? []; // array or comma string
$experience_levels = $_GET['experience_level'] ?? []; // array or comma string
$tags = $_GET['tags'] ?? []; // array

// ------------------- BASE QUERY -------------------
$sql = "
SELECT 
    j.job_id, j.title, j.location, j.salary_amount, j.currency, j.salary_duration,
    j.published_at, j.employment_type, j.experience_level,
    c.name AS category_name,
    co.id AS company_id, co.name AS company_name, co.logo_url AS company_logo
FROM jobs_table j
LEFT JOIN categories c ON j.category_id = c.id
LEFT JOIN companies co ON j.company_id = co.id
LEFT JOIN job_tags jt ON jt.job_id = j.job_id
LEFT JOIN tags t ON t.id = jt.tag_id
WHERE 1=1
AND j.status = 'active'
";

$params = [];
$types = "";

// ------------------- APPLY FILTERS -------------------

// Location filter
if (!empty($location)) {
    $sql .= " AND j.location LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}

// Category filter
if (!empty($category) && ctype_digit($category)) {
    $sql .= " AND j.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

// Keyword filter
if (!empty($keyword)) {
    $sql .= " AND (j.title LIKE ? OR j.overview LIKE ? OR j.description LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $types .= "sss";
}

// Employment type filter
if (!empty($_GET['employment_type'])) {
    $raw = $_GET['employment_type'];
    $arr = is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', $raw)));
    if (count($arr) > 0) {
        $placeholders = implode(',', array_fill(0, count($arr), '?'));
        $sql .= " AND j.employment_type IN ($placeholders)";
        foreach ($arr as $et) {
            $params[] = $et;
            $types .= "s";
        }
    }
}

// Experience level filter
if (!empty($_GET['experience_level'])) {
    $raw = $_GET['experience_level'];
    $arr = is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', $raw)));
    if (count($arr) > 0) {
        $placeholders = implode(',', array_fill(0, count($arr), '?'));
        $sql .= " AND j.experience_level IN ($placeholders)";
        foreach ($arr as $ex) {
            $params[] = $ex;
            $types .= "s";
        }
    }
}

// Tag filter (many-to-many)
if (!empty($tags) && is_array($tags)) {
    $placeholders = implode(',', array_fill(0, count($tags), '?'));
    $sql .= " AND t.name IN ($placeholders)";
    foreach ($tags as $tag) {
        $params[] = $tag;
        $types .= "s";
    }
}

// ------------------- GROUP & SORT -------------------
$sql .= " GROUP BY j.job_id ORDER BY j.published_at DESC";

$stmt = $dbconnection->prepare($sql);

if ($stmt === false) {
    echo json_encode(['status' => false, 'msg' => 'Prepare failed: ' . $dbconnection->error]);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// ------------------- ATTACH TAGS TO EACH JOB -------------------
$jobs = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $job_id = $row['job_id'];

        // Fetch tags for this job
        $tagQuery = "SELECT t.name FROM tags t 
                     JOIN job_tags jt ON jt.tag_id = t.id 
                     WHERE jt.job_id = ?";
        $tagStmt = $dbconnection->prepare($tagQuery);
        $tagStmt->bind_param("i", $job_id);
        $tagStmt->execute();
        $tagResult = $tagStmt->get_result();

        $tagsArray = [];
        while ($tagRow = $tagResult->fetch_assoc()) {
            $tagsArray[] = $tagRow['name'];
        }
        $tagStmt->close();

        $row['tags'] = $tagsArray;
        $row['isSaved'] = $user_id ? in_array($job_id, $savedJobIds) : false;

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
?>