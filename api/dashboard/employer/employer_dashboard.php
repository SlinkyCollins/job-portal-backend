<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

// Validate JWT and require employer role
$user = validateJWT('employer');
$user_id = $user['user_id'];

// JOIN users_table with employers_table to get the company_id
$query = "SELECT 
            u.user_id, 
            u.firstname, 
            u.lastname, 
            u.email, 
            u.role,
            u.suspended,
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

        // Add suspended check here
        if ($user_data['suspended'] == 1) {
            apiResponse(false, 'Your account has been suspended. Please contact support.', 403);
            exit;
        }

        // Ensure company_id is null if not found (just in case)
        if (!isset($user_data['company_id'])) {
            $user_data['company_id'] = null;
        }

        apiResponse(true, 'Employer dashboard loaded successfully.', 200, ['user' => $user_data]);
    } else {
        apiResponse(false, 'No user data found.', 404);
    }
} else {
    apiResponse(false, 'Database error.', 500);
}
$dbconnection->close();
?>
