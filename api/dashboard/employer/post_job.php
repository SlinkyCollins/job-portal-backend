<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

// 1. Validate JWT (Employer Role)
$user = validateJWT('employer');
$user_id = $user['user_id'];

// 2. Get Input Data (JSON)
$data = json_decode(file_get_contents("php://input"));

// 3. Check if Employer has a Company (CRITICAL STEP)
// We need the company_id to link the job to the company.
$queryCompany = "SELECT company_id FROM employers_table WHERE user_id = ? LIMIT 1";
$stmtCompany = $dbconnection->prepare($queryCompany);
$stmtCompany->bind_param("i", $user_id);
$stmtCompany->execute();
$resultCompany = $stmtCompany->get_result();

if ($resultCompany->num_rows === 0) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "Employer record not found."]);
    exit;
}

$row = $resultCompany->fetch_assoc();
$company_id = $row['company_id'];
$stmtCompany->close();

if (!$company_id) {
    http_response_code(403);
    echo json_encode(["status" => false, "message" => "You must create a Company Profile before posting a job."]);
    exit;
}

// 4. Validate Required Fields
if (
    empty($data->title) ||
    empty($data->category_id) ||
    empty($data->employment_type) ||
    empty($data->location) ||
    empty($data->description) ||
    empty($data->salary_amount) ||
    empty($data->currency) ||
    empty($data->salary_duration) ||
    empty($data->experience_level) ||
    empty($data->overview) ||
    empty($data->responsibilities) ||
    empty($data->requirements)
) {
    http_response_code(400);
    echo json_encode(["status" => false, "message" => "Please fill in all required fields."]);
    exit;
}

try {
    // 5. Handle Deadline Logic
    // If provided, use it. If empty, default to 30 days from now.
    $deadline = !empty($data->deadline) ? $data->deadline : date('Y-m-d', strtotime('+30 days'));

    // 6. Prepare INSERT Query
    $query = "INSERT INTO jobs_table (
                title, 
                category_id, 
                employment_type, 
                location,           
                salary_amount,      
                currency, 
                salary_duration, 
                experience_level, 
                english_fluency, 
                overview, 
                description, 
                responsibilities, 
                requirements, 
                nice_to_have, 
                benefits, 
                deadline, 
                employer_id, 
                company_id, 
                status,
                published_at
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())";

    $stmt = $dbconnection->prepare($query);

    // 7. Bind Parameters
    $stmt->bind_param(
        "sissdsssssssssssii",
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
        $deadline,
        $user_id,
        $company_id
    );

    // 8. Execute
    if ($stmt->execute()) {
        http_response_code(201);
        echo json_encode([
            "status" => true,
            "message" => "Job posted successfully!",
            "job_id" => $dbconnection->insert_id
        ]);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => "An error occurred: " . $e->getMessage()]);
}
?>