<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// 1. Validate JWT
$user = validateJWT('employer');
$user_id = $user['user_id'];

// 2. Get Input
$data = json_decode(file_get_contents("php://input"));

if (empty($data->job_id)) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Job ID is required."]);
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
            echo json_encode(["status" => true, "message" => "Job deleted successfully."]);
        } else {
            // Query ran, but nothing deleted (either job doesn't exist or belongs to someone else)
            http_response_code(404);
            echo json_encode(["status" => false, "message" => "Job not found or access denied."]);
        }
    } else {
        throw new Exception("Delete failed: " . $stmt->error);
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}

$dbconnection->close();
?>