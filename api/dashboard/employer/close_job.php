<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];

$data = json_decode(file_get_contents("php://input"));

if (empty($data->job_id)) {
    echo json_encode(["status" => false, "message" => "Job ID required"]);
    exit;
}

try {
    // Update status to 'closed' only if the employer owns the job and it's not already closed
    $query = "UPDATE jobs_table SET status = 'closed' WHERE job_id = ? AND employer_id = ? AND status != 'closed'";
    
    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param("ii", $data->job_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(["status" => true, "message" => "Job closed successfully"]);
    } else {
        echo json_encode(["status" => false, "message" => "Failed to close job or job not found/owned"]);
    }
    
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>