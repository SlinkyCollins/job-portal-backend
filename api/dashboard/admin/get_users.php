<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('admin');
$role = $user['role'];
$user_id = $user['user_id'];

// Fetch users (excluding the current admin to prevent self-deletion accidents)
// We also join with jobs_table to count how many jobs an employer has posted
$query = "SELECT 
            u.user_id, 
            u.firstname, 
            u.lastname, 
            u.email, 
            u.role, 
            u.createdat,
            u.suspended,
            (SELECT COUNT(*) FROM jobs_table WHERE employer_id = u.user_id) as job_count
          FROM users_table u 
          WHERE u.user_id != ? 
          ORDER BY u.createdat DESC";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode(['status' => true, 'data' => $users]);
?>