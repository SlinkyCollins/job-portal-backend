<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];

$job_id = $_GET['job_id'] ?? null;

if (!$job_id) {
    echo json_encode(["status" => false, "message" => "Job ID required"]);
    exit;
}

// Fetch all fields to populate the form
$query = "SELECT * FROM jobs_table WHERE job_id = ? AND employer_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param("ii", $job_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {

    // --- NEW: FETCH TAGS FOR THIS JOB ---
    $tagsQuery = "SELECT t.name 
                  FROM tags t 
                  JOIN job_tags jt ON t.id = jt.tag_id 
                  WHERE jt.job_id = ?";

    $stmtTags = $dbconnection->prepare($tagsQuery);
    $stmtTags->bind_param("i", $job_id);
    $stmtTags->execute();
    $resultTags = $stmtTags->get_result();

    $tags = [];
    while ($tagRow = $resultTags->fetch_assoc()) {
        $tags[] = $tagRow['name']; // Just push the name string
    }
    $stmtTags->close();

    // Attach tags to job object
    $row['tags'] = $tags;
    echo json_encode(["status" => true, "data" => $row]);
    $stmt->close();
} else {
    http_response_code(404);
    echo json_encode(["status" => false, "message" => "Job not found"]);
}
?>