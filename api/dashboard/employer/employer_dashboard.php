<?php
require_once __DIR__ . '/../../../config/middleware.php';

// Validate JWT and require employer role
$user = validateJWT('employer');
$user_id = $user['user_id'];

$response = [];

// JOIN users_table with employers_table to get the company_id
$query = "SELECT 
            u.user_id, 
            u.firstname, 
            u.lastname, 
            u.email, 
            u.role,
            e.company_id 
          FROM users_table u
          LEFT JOIN employers_table e ON u.user_id = e.user_id
          WHERE u.user_id = ?";

$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$execute = $stmt->execute();

if ($execute) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        
        // Ensure company_id is null if not found (just in case)
        if (!isset($user_data['company_id'])) {
            $user_data['company_id'] = null;
        }

        $response = [
            'status' => true,
            'user' => $user_data
        ];
    } else {
        http_response_code(404);
        $response = ['status' => false, 'msg' => 'No user data found.'];
    }
} else {
    http_response_code(500);
    $response = ['status' => false, 'msg' => 'Database error.'];
}

echo json_encode($response);
$dbconnection->close();
?>