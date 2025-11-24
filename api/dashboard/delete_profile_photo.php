<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/cloudinary.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Fetch current public_id
    $stmt = $dbconnection->prepare("SELECT profile_pic_public_id FROM job_seekers_table WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row && $row['profile_pic_public_id']) {
        try {
            // Delete from Cloudinary
            $cloudinary->uploadApi()->destroy($row['profile_pic_public_id'], ['resource_type' => 'image']);
        } catch (Exception $e) {
            // Log error but continue to clear DB
            error_log('Cloudinary delete failed: ' . $e->getMessage());
        }
    }

    // Clear database
    $update = $dbconnection->prepare("UPDATE job_seekers_table SET profile_pic_url = NULL, profile_pic_public_id = NULL WHERE user_id = ?");
    $update->bind_param('i', $user_id);
    $update->execute();
    $update->close();

    echo json_encode(['status' => true, 'msg' => 'Profile photo deleted successfully']);
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid request.']);
}

$dbconnection->close();
?>