<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

// 1. Validate JWT
$user = validateJWT('employer');
$user_id = $user['user_id'];

// 2. Get Input
$data = json_decode(file_get_contents("php://input"));

if (empty($data->job_id)) {
    apiResponse(false, 'Job ID is required.', 400);
    exit;
}

try {
    // 3. Delete Query (Secure)
    // We strictly enforce that the employer_id matches the logged-in user
    $query = "DELETE FROM jobs_table WHERE job_id = ? AND employer_id = ?";
    
    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param("ii", $data->job_id, $user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            apiResponse(true, 'Job deleted successfully.');
        } else {
            // Query ran, but nothing deleted (either job doesn't exist or belongs to someone else)
            apiResponse(false, 'Job not found or access denied.', 404);
        }
    } else {
        throw new Exception("Delete failed: " . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    apiResponse(false, 'An error occurred while deleting the job.', 500);
}

$dbconnection->close();
?>
