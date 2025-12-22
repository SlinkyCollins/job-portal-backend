<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// 1. Security Check (Middleware handles the 403 if not admin)
$user = validateJWT('admin');

// 2. Fetch All Jobs
$query = "SELECT 
            j.job_id, 
            j.title as job_title, 
            j.employment_type as job_type, 
            j.location,
            j.published_at as created_at, 
            c.name as company_name,
            u.firstname,
            u.lastname,
            (SELECT COUNT(*) FROM applications_table WHERE job_id = j.job_id) as application_count
          FROM jobs_table j
          LEFT JOIN companies c ON j.company_id = c.id
          JOIN users_table u ON j.employer_id = u.user_id
          ORDER BY j.published_at DESC";

$result = $dbconnection->query($query);

$jobs = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $jobs[] = $row;
    }
}

echo json_encode(['status' => true, 'data' => $jobs]);
?>