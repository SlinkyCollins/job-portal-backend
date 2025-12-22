<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// 1. Security: Admin Only
$user = validateJWT('admin');

// 2. Helper to run count queries
function getCount($db, $sql) {
    $result = $db->query($sql);
    return $result ? $result->fetch_row()[0] : 0;
}

try {
    $stats = [
        'total_users' => getCount($dbconnection, "SELECT COUNT(*) FROM users_table"),
        'job_seekers' => getCount($dbconnection, "SELECT COUNT(*) FROM users_table WHERE role = 'job_seeker'"),
        'employers'   => getCount($dbconnection, "SELECT COUNT(*) FROM users_table WHERE role = 'employer'"),
        'total_jobs'  => getCount($dbconnection, "SELECT COUNT(*) FROM jobs_table"),
        'applications'=> getCount($dbconnection, "SELECT COUNT(*) FROM applications_table")
    ];

    echo json_encode(['status' => true, 'data' => $stats]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Server Error']);
}
?>