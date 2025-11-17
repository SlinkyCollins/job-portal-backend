<?php
require_once '../headers.php';
require '../connect.php';
require_once '../middleware.php';
require '../vendor/autoload.php';

// Load JWT secret
if (file_exists(dirname(__DIR__) . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
    $dotenv->load();
}
$key = $_ENV['JWT_SECRET'] ?? null;
if (!$key) {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Server config error']);
    exit;
}

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

// Query to get saved jobs with details
$query = "SELECT 
    sj.id AS saved_id, sj.saved_at,
    j.job_id, j.title, j.overview, j.description, j.location, j.salary_amount, j.currency, j.salary_duration, j.experience_level, j.employment_type,
    c.name AS company_name, c.logo_url AS company_logo,
    GROUP_CONCAT(t.name SEPARATOR ',') AS tags
FROM saved_jobs_table sj
JOIN jobs_table j ON sj.job_id = j.job_id
JOIN companies c ON j.company_id = c.id
LEFT JOIN job_tags jt ON jt.job_id = j.job_id
LEFT JOIN tags t ON t.id = jt.tag_id
WHERE sj.user_id = ?
GROUP BY sj.id, j.job_id, c.id 
ORDER BY sj.saved_at DESC;";

$stmt = $dbconnection->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'DB prepare error']);
    exit;
}

$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'DB execute error']);
    exit;
}

$result = $stmt->get_result();

// ------------------- ATTACH TAGS TO EACH JOB -------------------
if ($result && $result->num_rows > 0) {
    $savedJobs = [];
    while ($row = $result->fetch_assoc()) {
        $row['tags'] = explode(',', $row['tags'] ?? '');
        $savedJobs[] = $row;
    }

    $response = [
        'status' => true,
        'savedJobs' => $savedJobs
    ];
} else {
    $response = [
        'status' => false,
        'msg' => 'No jobs found'
    ];
}

echo json_encode($response);

$stmt->close();
$dbconnection->close();
?>