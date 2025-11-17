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

// Get query parameters
$page = (int)($_GET['page'] ?? 1);
$per_page = (int)($_GET['per_page'] ?? 5);
$sort = $_GET['sort'] ?? 'new';

// Validate parameters
if ($page < 1) $page = 1;
if ($per_page < 1 || $per_page > 50) $per_page = 10; // Limit per_page
$offset = ($page - 1) * $per_page;

// Build ORDER BY clause based on sort
$order_by = 'sj.saved_at DESC'; // Default: new
switch ($sort) {
    case 'old':
        $order_by = 'sj.saved_at ASC';
        break;
    case 'salary-high':
        $order_by = 'j.salary_amount DESC';
        break;
    case 'salary-low':
        $order_by = 'j.salary_amount ASC';
        break;
    case 'company':
        $order_by = 'c.name ASC';
        break;
    case 'type':
        $order_by = 'j.employment_type ASC';
        break;
    case 'category':
        // Assuming category is in jobs_table; adjust if needed
        $order_by = 'j.category_id ASC';
        break;
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM saved_jobs_table WHERE user_id = ?";
$count_stmt = $dbconnection->prepare($count_query);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_jobs = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_jobs / $per_page);
$count_stmt->close();

// Query to get saved jobs with pagination and sorting
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
ORDER BY $order_by
LIMIT ? OFFSET ?";

$stmt = $dbconnection->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'DB prepare error']);
    exit;
}

$stmt->bind_param("iii", $user_id, $per_page, $offset);
if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'DB execute error']);
    exit;
}

$result = $stmt->get_result();

// Attach tags to each job
if ($result && $result->num_rows > 0) {
    $savedJobs = [];
    while ($row = $result->fetch_assoc()) {
        $row['tags'] = explode(',', $row['tags'] ?? '');
        $savedJobs[] = $row;
    }

    $response = [
        'status' => true,
        'savedJobs' => $savedJobs,
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_jobs' => $total_jobs,
            'total_pages' => $total_pages
        ]
    ];
} else {
    $response = [
        'status' => true, // Still true, just empty
        'savedJobs' => [],
        'pagination' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total_jobs' => 0,
            'total_pages' => 0
        ]
    ];
}

echo json_encode($response);

$stmt->close();
$dbconnection->close();
?>