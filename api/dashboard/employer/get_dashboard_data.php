<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];

try {
    // 1. GET STATS
    $statsQuery = "SELECT 
        (SELECT COUNT(*) FROM jobs_table WHERE employer_id = ?) as total_jobs,
        (SELECT COUNT(*) FROM jobs_table WHERE employer_id = ? AND status = 'active') as active_jobs,
        (SELECT COUNT(*) FROM applications_table a JOIN jobs_table j ON a.job_id = j.job_id WHERE j.employer_id = ?) as total_applications,
        (SELECT COUNT(*) FROM applications_table a JOIN jobs_table j ON a.job_id = j.job_id WHERE j.employer_id = ? AND a.status = 'shortlisted') as shortlisted_count";

    $stmt = $dbconnection->prepare($statsQuery);
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();

    // 2. GET RECENT JOBS (Limit 5)
    $jobsQuery = "SELECT j.job_id, j.title, j.employment_type, j.location, j.status, j.published_at, c.logo_url 
                  FROM jobs_table j 
                  LEFT JOIN companies c ON j.employer_id = c.user_id
                  WHERE j.employer_id = ? 
                  ORDER BY j.published_at DESC LIMIT 5";
    
    $stmt = $dbconnection->prepare($jobsQuery);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recentJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode([
        "status" => true, 
        "stats" => $stats, 
        "recentJobs" => $recentJobs
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>