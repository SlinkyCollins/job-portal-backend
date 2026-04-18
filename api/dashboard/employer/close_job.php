<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];

$data = json_decode(file_get_contents("php://input"));

if (empty($data->job_id)) {
    apiResponse(false, 'Job ID required.', 400);
    exit;
}

try {
    // Update status to 'closed' only if the employer owns the job and it's not already closed
    $query = "UPDATE jobs_table SET status = 'closed' WHERE job_id = ? AND employer_id = ? AND status != 'closed'";
    
    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param("ii", $data->job_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        apiResponse(true, 'Job closed successfully.');
    } else {
        apiResponse(false, 'Failed to close job or job not found or owned by another employer.', 404);
    }
    
    $stmt->close();
} catch (Exception $e) {
    apiResponse(false, 'An error occurred while closing the job.', 500);
}
?>
