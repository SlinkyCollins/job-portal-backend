<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// 1. Validate JWT
$user = validateJWT('employer');
$user_id = $user['user_id'];

// 2. Pagination and Sorting Params
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int) $_GET['per_page'] : 10;
$offset = ($page - 1) * $per_page;
$sort = $_GET['sort'] ?? 'all';  // Default to 'all'

// Determine WHERE and ORDER BY based on sort
$whereClause = "WHERE j.employer_id = ?";
$orderByClause = "ORDER BY j.published_at DESC";  // Default for most cases
$params = [$user_id];
$types = "i";

switch ($sort) {
    case 'active':
        $whereClause .= " AND j.status = 'active'";
        break;
    case 'pending':
        $whereClause .= " AND j.status = 'pending'";
        break;
    case 'closed':
        $whereClause .= " AND j.status = 'closed'";
        break;
    case 'old':
        $orderByClause = "ORDER BY j.published_at ASC"; 
        break;
    case 'all':
    case 'new':
    default:
        // No additional WHERE for 'all' or 'new' (both show all, sorted by newest)
        break;
}

try {
    // 3. Count Query (for filtered total jobs)
    $countQuery = "SELECT COUNT(*) AS total FROM jobs_table j $whereClause";
    $countStmt = $dbconnection->prepare($countQuery);
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total_jobs = $countResult->fetch_assoc()['total'] ?? 0;
    $countStmt->close();

    // 3b. Count Query (for total jobs regardless of filter)
    $countAllQuery = "SELECT COUNT(*) AS total FROM jobs_table WHERE employer_id = ?";
    $countAllStmt = $dbconnection->prepare($countAllQuery);
    $countAllStmt->bind_param('i', $user_id);
    $countAllStmt->execute();
    $countAllResult = $countAllStmt->get_result();
    $total_all = $countAllResult->fetch_assoc()['total'] ?? 0;
    $countAllStmt->close();

    // 4. Main Query (with pagination and sorting)
    $query = "SELECT 
                j.job_id, 
                j.title, 
                j.employment_type, 
                j.location, 
                j.salary_amount, 
                j.currency, 
                j.status, 
                j.deadline, 
                j.published_at,
                COUNT(a.application_id) AS application_count
              FROM jobs_table j
              LEFT JOIN applications_table a ON j.job_id = a.job_id
              $whereClause
              GROUP BY j.job_id
              $orderByClause
              LIMIT ? OFFSET ?";

    // Add pagination params
    $params[] = $per_page;
    $params[] = $offset;
    $types .= "ii";

    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    $stmt->close();

    // 5. Response with Pagination Metadata
    echo json_encode([
        "status" => true,
        "data" => $jobs,
        "total" => $total_jobs,  // Filtered total
        "total_all" => $total_all,  // Total jobs (all statuses)
        "page" => $page,
        "per_page" => $per_page,
        "total_pages" => ceil($total_jobs / $per_page)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Database error: " . $e->getMessage()]);
}

$dbconnection->close();
?>