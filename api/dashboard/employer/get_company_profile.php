<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

// 1. Validate JWT (Employer Role)
$user = validateJWT('employer');
$user_id = $user['user_id'];

try {
    // 2. Query the companies table for this user
    $query = "SELECT id, name, logo_url, website, location, description, created_at 
              FROM companies 
              WHERE user_id = ? 
              LIMIT 1";
              
    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Company Found
        $company = $result->fetch_assoc();

        apiResponse(true, 'Company profile retrieved successfully.', 200, [
            'has_company' => true,
            'data' => $company
        ]);
    } else {
        // No Company Found (New Employer)
        apiResponse(true, 'No company profile found.', 200, [
            'has_company' => false,
            'data' => null
        ]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    apiResponse(false, 'Database error while retrieving company profile.', 500);
}

$dbconnection->close();
?>
