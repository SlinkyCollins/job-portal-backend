<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';
require_once __DIR__ . '/../../../config/api_response.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // First, get the current cv_url and cv_public_id to delete from Cloudinary
        $select = $dbconnection->prepare("SELECT cv_url, cv_public_id FROM job_seekers_table WHERE user_id = ?");
        $select->bind_param('i', $user_id);
        $select->execute();
        $result = $select->get_result();
        $row = $result->fetch_assoc();
        $select->close();

        if ($row && $row['cv_public_id']) {
            // Delete from Cloudinary
            $cloudinary->uploadApi()->destroy($row['cv_public_id'], ['resource_type' => 'raw']);
        }

        // Clear cv_url, cv_filename and cv_public_id in DB
        $update = $dbconnection->prepare("UPDATE job_seekers_table SET cv_url = NULL, cv_filename = NULL, cv_public_id = NULL WHERE user_id = ?");
        $update->bind_param('i', $user_id);
        $update->execute();
        $update->close();

        apiResponse(true, 'CV deleted successfully.');
    } catch (Exception $e) {
        apiResponse(false, 'Delete failed.', 500);
    }
} else {
    apiResponse(false, 'Invalid request method.', 405);
}

$dbconnection->close();
