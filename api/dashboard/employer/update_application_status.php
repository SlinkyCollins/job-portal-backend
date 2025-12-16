<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// 1. Validate JWT (Employer)
$user = validateJWT('employer');
$user_id = $user['user_id'];

// 2. Get Input
$data = json_decode(file_get_contents("php://input"));

if (empty($data->application_id) || empty($data->status)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Application ID and Status required."]);
    exit;
}

$valid_statuses = ['pending', 'shortlisted', 'accepted', 'rejected'];
if (!in_array($data->status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Invalid status."]);
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
            echo json_encode(["status" => true, "message" => "Status updated successfully."]);
        } else {
            // Either ID doesn't exist OR it doesn't belong to this employer
            http_response_code(403);
            echo json_encode(["status" => false, "message" => "Update failed. Application not found or access denied."]);
        }
    } else {
        throw new Exception("Update failed: " . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
$dbconnection->close();
?>