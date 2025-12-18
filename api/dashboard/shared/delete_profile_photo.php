<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';

// 1. Validate JWT for EITHER role
$user = validateJWT(['job_seeker', 'employer']);
$user_id = $user['user_id'];
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2. Determine Table
    $table = '';
    if ($role === 'job_seeker') {
        $table = 'job_seekers_table';
    } elseif ($role === 'employer') {
        $table = 'employers_table';
    } else {
        http_response_code(403);
        echo json_encode(['status' => false, 'message' => 'Invalid role']);
        exit;
    }

    // 3. Fetch current public_id
    $stmt = $dbconnection->prepare("SELECT profile_pic_public_id FROM $table WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && $row['profile_pic_public_id']) {
        try {
            // 4. Delete from Cloudinary
            $cloudinary->uploadApi()->destroy($row['profile_pic_public_id'], ['resource_type' => 'image']);
        } catch (Exception $e) {
            error_log('Cloudinary delete failed: ' . $e->getMessage());
        }
    }

    // 5. Clear Database
    $update = $dbconnection->prepare("UPDATE $table SET profile_pic_url = NULL, profile_pic_public_id = NULL WHERE user_id = ?");
    $update->bind_param('i', $user_id);
    
    if ($update->execute()) {
        echo json_encode(['status' => true, 'msg' => 'Profile photo deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'message' => 'Database update failed']);
    }
    $update->close();

} else {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid request.']);
}

$dbconnection->close();
?>