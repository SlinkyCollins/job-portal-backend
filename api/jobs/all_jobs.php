<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;

if (file_exists(dirname(__DIR__) . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/..');
    $dotenv->load();
}

$usedCurrencies = ['USD', 'NGN', 'GBP', 'EUR'];
$cacheExpiryHours = 72;  // Define cache expiry (e.g., 72 hours)

// Check if rates are cached and recent (only for used currencies)
$rates = [];
$placeholders = str_repeat('?,', count($usedCurrencies) - 1) . '?';
$cacheQuery = "SELECT currency, rate FROM exchange_rates WHERE currency IN ($placeholders) AND last_updated > DATE_SUB(NOW(), INTERVAL $cacheExpiryHours HOUR)";
$cacheStmt = $dbconnection->prepare($cacheQuery);
$cacheStmt->execute($usedCurrencies);
$cacheResult = $cacheStmt->get_result();
if ($cacheResult->num_rows === count($usedCurrencies)) {
    // Load from cache if all used currencies are cached
    while ($row = $cacheResult->fetch_assoc()) {
        $rates[$row['currency']] = $row['rate'];
    }
} else {
    // Fetch from API and cache only used currencies
    $apiKey = $_ENV['EXCHANGE_RATE_API_KEY'] ?? '';
    $ratesUrl = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/USD";
    $ratesResponse = file_get_contents($ratesUrl);
    if ($ratesResponse) {
        $ratesData = json_decode($ratesResponse, true);
        if ($ratesData && isset($ratesData['conversion_rates'])) {
            $allRates = $ratesData['conversion_rates'] ?? [];
            // Filter to only used currencies
            foreach ($usedCurrencies as $curr) {
                if (isset($allRates[$curr])) {
                    $rates[$curr] = $allRates[$curr];
                    // Insert/update into DB
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

// Now $rates has only USD, NGN, GBP, EUR

$user_id = null;
$savedJobIds = [];

// ------------------- AUTH CHECK -------------------
$headers = getallheaders();
$auth = $headers['Authorization'] ?? '';

if ($auth && str_starts_with($auth, 'Bearer ')) {
    $jwt = str_replace('Bearer ', '', $auth);
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
$sort = $_GET['sort'] ?? 'datePosted';

$employment_types = $_GET['employment_type'] ?? []; // array or comma string
$experience_levels = $_GET['experience_level'] ?? []; // array or comma string
$tags = $_GET['tags'] ?? []; // array

$currency = $_GET['currency'] ?? null;
$min_salary = $_GET['min_salary'] ?? null;
$max_salary = $_GET['max_salary'] ?? null;
$salary_duration = $_GET['salary_duration'] ?? null;

// ------------------- PAGINATION PARAMS -------------------
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;

// ------------------- DETERMINE ORDER BY CLAUSE -------------------
$orderByClause = '';
switch ($sort) {
    case 'salaryLowToHigh':
        // Build a CASE statement for currency conversion
        $caseStatement = '';
        foreach ($rates as $curr => $rate) {
            $caseStatement .= "WHEN j.currency = '$curr' THEN j.salary_amount / NULLIF($rate, 0) ";
        }
        $orderByClause = "ORDER BY CASE $caseStatement ELSE j.salary_amount END ASC";  // Fallback to original if no match
        break;
    case 'salaryHighToLow':
        $caseStatement = '';
        foreach ($rates as $curr => $rate) {
            $caseStatement .= "WHEN j.currency = '$curr' THEN j.salary_amount / NULLIF($rate, 0) ";
        }
        $orderByClause = "ORDER BY CASE $caseStatement ELSE j.salary_amount END DESC";
        break;
    case 'relevance':
        $orderByClause = 'ORDER BY j.title ASC';  // Alphabetical for now, can be enhanced later
        break;
    case 'datePosted':
    default:
        $orderByClause = 'ORDER BY j.published_at DESC';  // Newest first
        break;
}

// ------------------- BASE QUERY -------------------
$baseSql = "
FROM jobs_table j
LEFT JOIN categories c ON j.category_id = c.id
LEFT JOIN companies co ON j.company_id = co.id
LEFT JOIN job_tags jt ON jt.job_id = j.job_id
LEFT JOIN tags t ON t.id = jt.tag_id
WHERE 1=1
AND j.status = 'active'
AND j.deadline > NOW()
";

$selectSql = "
SELECT 
    j.job_id, j.title, j.location, j.salary_amount, j.currency, j.salary_duration,
    j.published_at, j.employment_type, j.experience_level,
    c.name AS category_name,
    co.id AS company_id, co.name AS company_name, co.logo_url AS company_logo
";

$sql = $selectSql . $baseSql;

$countSql = "SELECT COUNT(DISTINCT j.job_id) as total " . $baseSql;

$params = [];
$types = "";

// Location filter
if (!empty($location)) {
    $sql .= " AND j.location LIKE ?";
    $countSql .= " AND j.location LIKE ?";
    $params[] = "%$location%";
    $types .= "s";
}

// Category filter
if (!empty($category) && ctype_digit($category)) {
    $sql .= " AND j.category_id = ?";
    $countSql .= " AND j.category_id = ?";
    $params[] = $category;
    $types .= "i";
}

// Keyword filter
if (!empty($keyword)) {
    $sql .= " AND (j.title LIKE ? OR j.overview LIKE ? OR j.description LIKE ?)";
    $countSql .= " AND (j.title LIKE ? OR j.overview LIKE ? OR j.description LIKE ?)";
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
        $countSql .= " AND j.employment_type IN ($placeholders)";
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
        $countSql .= " AND j.experience_level IN ($placeholders)";
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
    $countSql .= " AND t.name IN ($placeholders)";
    foreach ($tags as $tag) {
        $params[] = $tag;
        $types .= "s";
    }
}

// Additional Salary Filters
if (!empty($currency)) {
    $sql .= " AND j.currency = ?";
    $countSql .= " AND j.currency = ?";
    $params[] = $currency;
    $types .= "s";
}

if (!empty($min_salary) && !empty($max_salary)) {
    $sql .= " AND j.salary_amount BETWEEN ? AND ?";
    $countSql .= " AND j.salary_amount BETWEEN ? AND ?";
    $params[] = $min_salary;
    $params[] = $max_salary;
    $types .= "ii";  // Change to "dd" if salaries are floats
}

if (!empty($salary_duration)) {
    $sql .= " AND j.salary_duration = ?";
    $countSql .= " AND j.salary_duration = ?";
    $params[] = $salary_duration;
    $types .= "s";
}

// ------------------- GROUP & SORT & PAGINATE -------------------
$filterParams = $params; // Save params before adding pagination
$sql .= " GROUP BY j.job_id $orderByClause LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";

// Prepare and execute count query
$countStmt = $dbconnection->prepare($countSql);
if (!empty($filterParams)) {
    $countStmt->bind_param(substr($types, 0, -2), ...$filterParams);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$total_jobs = $countResult->fetch_assoc()['total'] ?? 0;
$countStmt->close();

// Prepare main query
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
        'jobs' => $jobs,
        'total' => $total_jobs,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total_jobs / $per_page)
    ];
} else {
    $response = [
        'status' => false,
        'msg' => 'No jobs found'
    ];
}

echo json_encode($response);
$dbconnection->close();