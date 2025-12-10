<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$response = [];

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
        $response = [
            'status' => true,
            'applications' => $allApplications
        ];
    } else {
        $response = [
            'status' => false,
            'msg' => 'No applications found.'
        ];
    }
}
else {
    http_response_code(500);
    $response = ['status' => false, 'msg' => 'An error occurred while retrieving applications. Please try again later.'];
}

$stmt->close();
echo json_encode($response);
$dbconnection->close();
?>
