<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('employer');
$user_id = $user['user_id'];

$data = json_decode(file_get_contents("php://input"));

if (empty($data->job_id) || empty($data->title)) {
    echo json_encode(["status" => false, "message" => "Missing required fields"]);
    exit;
}

try {
    // We update everything EXCEPT created_at, employer_id, company_id (those shouldn't change)
    $query = "UPDATE jobs_table SET 
                title=?, category_id=?, employment_type=?, location=?, 
                salary_amount=?, currency=?, salary_duration=?, 
                experience_level=?, english_fluency=?, overview=?, 
                description=?, responsibilities=?, requirements=?, 
                nice_to_have=?, benefits=?, deadline=?, status=?
              WHERE job_id=? AND employer_id=?";

    $stmt = $dbconnection->prepare($query);
    
    // Type string: 17 fields to update + 2 for WHERE clause = 19 chars
    // s i s s d s s s s s s s s s s s s i i
    $types = "sisdsssssssssssssii";
    
    $stmt->bind_param($types,
        $data->title,
        $data->category_id,
        $data->employment_type,
        $data->location,
        $data->salary_amount,
        $data->currency,
        $data->salary_duration,
        $data->experience_level,
        $data->english_fluency,
        $data->overview,
        $data->description,
        $data->responsibilities,
        $data->requirements,
        $data->nice_to_have,
        $data->benefits,
        $data->deadline,
        $data->status, // We allow updating status here (active/closed)
        $data->job_id,
        $user_id
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => true, "message" => "Job updated successfully"]);
    } else {
        throw new Exception("Update failed: " . $stmt->error);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "Error: " . $e->getMessage()]);
}
?>