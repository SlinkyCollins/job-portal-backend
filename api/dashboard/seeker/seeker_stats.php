<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$shortlisted = 0;
$applied = 0;
$accepted = 0;
$saved = 0;

try {
    // 1. Total Applied (All applications)
    $queryApplied = "SELECT COUNT(*) as count FROM applications_table WHERE seeker_id = ? AND status != 'retracted'";
    $stmt = $dbconnection->prepare($queryApplied);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $applied = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // 2. Shortlisted (Your application was shortlisted by employer)
    $queryShortlisted = "SELECT COUNT(*) as count FROM applications_table WHERE seeker_id = ? AND status = 'shortlisted'";
    $stmt = $dbconnection->prepare($queryShortlisted);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $shortlisted_apps = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // 3. Accepted (You got the job!)
    $queryAccepted = "SELECT COUNT(*) as count FROM applications_table WHERE seeker_id = ? AND status = 'accepted'";
    $stmt = $dbconnection->prepare($queryAccepted);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $accepted = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    // 4. Saved Jobs (Jobs you bookmarked)
    $querySaved = "SELECT COUNT(*) as count FROM saved_jobs_table WHERE user_id = ?";
    $stmt = $dbconnection->prepare($querySaved);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $saved = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();

    echo json_encode([
        "status" => true,
        "data" => [
            "applied" => $applied,
            "shortlisted_apps" => $shortlisted_apps,
            "accepted" => $accepted,
            "saved_jobs" => $saved
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>