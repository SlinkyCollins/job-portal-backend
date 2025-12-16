<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

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
        
        echo json_encode([
            'status' => true,
            'has_company' => true,
            'data' => $company
        ]);
    } else {
        // No Company Found (New Employer)
        echo json_encode([
            'status' => true,
            'has_company' => false,
            'data' => null,
            'message' => 'No company profile found.'
        ]);
    }
    
    $stmt->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}

$dbconnection->close();
?>