<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

// 1. Validate JWT (Employer)
$user = validateJWT('employer');
$user_id = $user['user_id'];

// 2. Get Input
$data = json_decode(file_get_contents("php://input"));

if (empty($data->application_id) || empty($data->status)) {
    apiResponse(false, 'Application ID and status are required.', 400);
    exit;
}

$valid_statuses = ['pending', 'shortlisted', 'accepted', 'rejected'];
if (!in_array($data->status, $valid_statuses)) {
    apiResponse(false, 'Invalid status.', 400);
    exit;
}

try {
    // 3. Security Check & Update
    // We must ensure the application belongs to a job posted by THIS employer.
    // We use a JOIN to verify ownership before updating.
    
    $query = "UPDATE applications_table a
              JOIN jobs_table j ON a.job_id = j.job_id
              SET a.status = ?
              WHERE a.application_id = ? AND j.employer_id = ?";

    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param("sii", $data->status, $data->application_id, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            apiResponse(true, 'Status updated successfully.');
        } else {
            // Either ID doesn't exist OR it doesn't belong to this employer
            apiResponse(false, 'Update failed. Application not found or access denied.', 403);
        }
    } else {
        throw new Exception("Update failed: " . $stmt->error);
    }

} catch (Exception $e) {
    apiResponse(false, 'An error occurred while updating application status.', 500);
}
$dbconnection->close();
?>
