<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

// Get all applications by the user (from applications_table)
$allApplications = [];

$query = "SELECT 
    a.application_id, 
    a.job_id, 
    j.title, 
    j.employment_type, 
    j.location, 
    c.logo_url,
    a.status, 
    a.applied_at 
FROM 
    applications_table a 
LEFT JOIN 
    jobs_table j ON a.job_id = j.job_id 
LEFT JOIN 
    companies c ON c.id = j.company_id 
WHERE 
    a.seeker_id = ? AND a.status != 'retracted'
ORDER BY 
    a.applied_at DESC";

$stmt = $dbconnection->prepare($query);
$stmt->bind_param("i", $user_id);
$execute = $stmt->execute();

if ($execute) {
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $allApplications[] = $row;
        };
        apiResponse(true, 'Applications retrieved successfully.', 200, ['applications' => $allApplications]);
    } else {
        apiResponse(false, 'No applications found.', 200);
    }
}
else {
    apiResponse(false, 'An error occurred while retrieving applications. Please try again later.', 500);
}

$stmt->close();
$dbconnection->close();
?>
