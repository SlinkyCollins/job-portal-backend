<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../vendor/autoload.php';

// Load JWT secret
if (file_exists(dirname(__DIR__) . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/..');
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

// ------------------- EXCHANGE RATES (Copied from all_jobs.php) -------------------
$usedCurrencies = ['USD', 'NGN', 'GBP', 'EUR'];
$cacheExpiryHours = 72;

$rates = [];
$placeholders = str_repeat('?,', count($usedCurrencies) - 1) . '?';
$cacheQuery = "SELECT currency, rate FROM exchange_rates WHERE currency IN ($placeholders) AND last_updated > DATE_SUB(NOW(), INTERVAL $cacheExpiryHours HOUR)";
$cacheStmt = $dbconnection->prepare($cacheQuery);
$cacheStmt->execute($usedCurrencies);
$cacheResult = $cacheStmt->get_result();

if ($cacheResult->num_rows === count($usedCurrencies)) {
    while ($row = $cacheResult->fetch_assoc()) {
        $rates[$row['currency']] = $row['rate'];
    }
} else {
    $apiKey = $_ENV['EXCHANGE_RATE_API_KEY'] ?? '';
    $ratesUrl = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD";
    // Suppress errors to avoid breaking the API if external call fails
    $ratesResponse = @file_get_contents($ratesUrl);
    if ($ratesResponse) {
        $ratesData = json_decode($ratesResponse, true);
        if ($ratesData && isset($ratesData['conversion_rates'])) {
            $allRates = $ratesData['conversion_rates'] ?? [];
            foreach ($usedCurrencies as $curr) {
                if (isset($allRates[$curr])) {
                    $rates[$curr] = $allRates[$curr];
                    $insertQuery = "INSERT INTO exchange_rates (currency, rate) VALUES (?, ?) ON DUPLICATE KEY UPDATE rate = VALUES(rate), last_updated = NOW()";
                    $insertStmt = $dbconnection->prepare($insertQuery);
                    $insertStmt->bind_param("sd", $curr, $rates[$curr]);
                    $insertStmt->execute();
                    $insertStmt->close();
                }
            }
        }
    }
}
$cacheStmt->close();
// ---------------------------------------------------------------------------------

// Get query parameters
$page = (int) ($_GET['page'] ?? 1);
$per_page = (int) ($_GET['per_page'] ?? 5);
$sort = $_GET['sort'] ?? 'new';

// Validate parameters
if ($page < 1)
    $page = 1;
if ($per_page < 1 || $per_page > 50)
    $per_page = 10; // Limit per_page
$offset = ($page - 1) * $per_page;

// Build ORDER BY clause based on sort
$orderByClause = '';
switch ($sort) {
    case 'old':
        $orderByClause = 'sj.saved_at ASC';
        break;
    case 'salary-high':
        $caseStatement = '';
        foreach ($rates as $curr => $rate) {
            $caseStatement .= "WHEN j.currency = '$curr' THEN j.salary_amount / NULLIF($rate, 0) ";
        }
        // Fallback to raw amount if currency not found or rate is 0
        $orderByClause = "CASE $caseStatement ELSE j.salary_amount END DESC";
        break;
    case 'salary-low':
        $caseStatement = '';
        foreach ($rates as $curr => $rate) {
            $caseStatement .= "WHEN j.currency = '$curr' THEN j.salary_amount / NULLIF($rate, 0) ";
        }
        $orderByClause = "CASE $caseStatement ELSE j.salary_amount END ASC";
        break;
    case 'company':
        $orderByClause = 'c.name ASC';
        break;
    case 'type':
        // Ensure alphabetical order (Contract < Full Time < Part Time)
        // Using TRIM to avoid whitespace issues
        $orderByClause = 'TRIM(j.employment_type) ASC';
        break;
    case 'category':
        $orderByClause = 'cat.name ASC';
        break;
    case 'new':
    default:
        $orderByClause = 'sj.saved_at DESC';  // Default to newest saved
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
    j.job_id, j.title, j.overview, j.description, j.location, j.salary_amount, j.currency, j.salary_duration, j.experience_level, j.category_id AS category_id, cat.name AS category_name, j.employment_type,
    c.name AS company_name, c.logo_url AS company_logo,
    GROUP_CONCAT(t.name SEPARATOR ',') AS tags
FROM saved_jobs_table sj
JOIN jobs_table j ON sj.job_id = j.job_id
JOIN companies c ON j.company_id = c.id
JOIN categories cat ON j.category_id = cat.id
LEFT JOIN job_tags jt ON jt.job_id = j.job_id
LEFT JOIN tags t ON t.id = jt.tag_id
WHERE sj.user_id = ?
GROUP BY sj.id, j.job_id, c.id, cat.id
ORDER BY $orderByClause
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