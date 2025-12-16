<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];

try {
    // We use COALESCE() to pick the application resume first. 
    // If it's null, we grab the profile CV.
    $query = "SELECT 
                a.application_id, 
                a.status, 
                a.applied_at,
                a.cover_letter,
                j.title as job_title,
                u.firstname, 
                u.lastname, 
                u.email,
                COALESCE(a.resume_url, js.cv_url) as cv_url, 
                js.profile_pic_url
              FROM applications_table a
              JOIN jobs_table j ON a.job_id = j.job_id
              JOIN users_table u ON a.seeker_id = u.user_id
              LEFT JOIN job_seekers_table js ON u.user_id = js.user_id
              WHERE j.employer_id = ?
              AND a.status != 'retracted'
              ORDER BY a.applied_at DESC";

    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $applications = [];
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }

    echo json_encode(["status" => true, "data" => $applications]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>