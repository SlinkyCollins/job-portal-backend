<?php
require_once 'headers.php';
require 'connect.php';
require_once 'middleware.php';
require 'config/cloudinary_config.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // First, get the current cv_url and public_id to delete from Cloudinary
        $select = $dbconnection->prepare("SELECT cv_url, public_id FROM job_seekers_table WHERE user_id = ?");
        $select->bind_param('i', $user_id);
        $select->execute();
        $result = $select->get_result();
        $row = $result->fetch_assoc();
        $select->close();

        if ($row && $row['public_id']) {
            // Delete from Cloudinary
            $cloudinary->uploadApi()->destroy($row['public_id'], ['resource_type' => 'raw']);
        }

        // Clear cv_url, cv_filename and public_id in DB
        $update = $dbconnection->prepare("UPDATE job_seekers_table SET cv_url = NULL, cv_filename = NULL, public_id = NULL WHERE user_id = ?");
        $update->bind_param('i', $user_id);
        $update->execute();
        $update->close();

        echo json_encode([
            'status' => true,
            'message' => 'CV deleted successfully'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => false,
            'message' => 'Delete failed: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['status' => false, 'message' => 'Invalid request method.']);
}

$dbconnection->close();