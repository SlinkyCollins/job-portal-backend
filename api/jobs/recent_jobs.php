<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;

$user_id = null;
$savedJobIds = [];

// ------------------- AUTH CHECK -------------------
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';

if ($auth && str_starts_with($auth, 'Bearer ')) {
    $jwt = str_replace('Bearer ', '', $auth);
    if (file_exists(dirname(__DIR__) . '/../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/..');
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

$sql = "SELECT 
  j.job_id,
  j.title,
  j.location,
  j.published_at,
  j.employment_type,
  j.experience_level,
  co.name AS company_name,
  co.logo_url AS company_logo,
  GROUP_CONCAT(t.name ORDER BY t.name SEPARATOR ', ') AS tags
FROM jobs_table j
LEFT JOIN companies co ON j.company_id = co.id
LEFT JOIN job_tags jt ON jt.job_id = j.job_id
LEFT JOIN tags t ON t.id = jt.tag_id
WHERE j.status = 'active'
GROUP BY j.job_id
ORDER BY j.published_at DESC
LIMIT 5
";

$result = $dbconnection->query($sql);

$jobs = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $job_id = $row['job_id'];
        // Convert comma-separated tags to array
        $row['tags'] = !empty($row['tags']) ? explode(', ', $row['tags']) : [];
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
        'msg' => 'No recent jobs found'
    ];
}

echo json_encode($response);

$dbconnection->close();

?>