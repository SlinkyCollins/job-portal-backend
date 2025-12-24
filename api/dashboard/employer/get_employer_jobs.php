<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// 1. Validate JWT
$user = validateJWT('employer');
$user_id = $user['user_id'];

try {
    // 2. Fetch Jobs with application counts in one query
    // We select specific columns to keep it lightweight.
    // We order by ID DESC so the newest jobs appear first.
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
              WHERE j.employer_id = ?
              GROUP BY j.job_id
              ORDER BY j.job_id DESC";

    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $jobs = [];
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
    $stmt->close();

    echo json_encode([
        "status" => true,
        "data" => $jobs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Database error: " . $e->getMessage()]);
}

$dbconnection->close();
?>