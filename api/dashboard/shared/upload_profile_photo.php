<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';

// 1. Validate JWT for EITHER role
// Passing an array allows both roles to pass validation
$user = validateJWT(['job_seeker', 'employer']);
$user_id = $user['user_id'];
$role = $user['role'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024;  // 5MB limit

    if (!in_array($file['type'], $allowedTypes)) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'Invalid file type.']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        http_response_code(400);
        echo json_encode(['status' => false, 'message' => 'File too large. Max 5MB.']);
        exit;
    }

    try {
        // 2. Determine Table based on Role
        $table = '';
        if ($role === 'job_seeker') {
            $table = 'job_seekers_table';
        } elseif ($role === 'employer') {
            $table = 'employers_table';
        } else {
            throw new Exception("Invalid user role for profile photo.");
        }

        // 3. CHECK FOR EXISTING PHOTO AND DELETE IF EXISTS
        $checkQuery = "SELECT profile_pic_public_id FROM $table WHERE user_id = ?";
        $stmt = $dbconnection->prepare($checkQuery);
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result->fetch_assoc();
        $stmt->close();

        // If exists, delete from Cloudinary
        if ($existing && !empty($existing['profile_pic_public_id'])) {
            try {
                $cloudinary->uploadApi()->destroy($existing['profile_pic_public_id'], ['resource_type' => 'image']);
            } catch (Exception $e) {
                // Continue even if delete fails (don't block the new upload)
                error_log("Failed to delete old image: " . $e->getMessage());
            }
        }

        // 4. Upload to Cloudinary
        $uploadResult = $cloudinary->uploadApi()->upload($file['tmp_name'], [
            'folder' => 'jobnet/profile_photos',
            'resource_type' => 'image',
            'public_id' => $role . '_' . $user_id . '_' . time(), // e.g. employer_5_123456
            'filename' => $file['name']
        ]);

        $photo_url = $uploadResult['secure_url'];
        $public_id = $uploadResult['public_id'];

        // 5. Update Database (Dynamic Table)
        $query = "UPDATE $table SET profile_pic_url = ?, profile_pic_public_id = ? WHERE user_id = ?";
        $stmt = $dbconnection->prepare($query);
        $stmt->bind_param('ssi', $photo_url, $public_id, $user_id);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => true,
                'msg' => 'Profile photo uploaded successfully',
                'photoURL' => $photo_url,
                'public_id' => $public_id
            ]);
        } else {
            throw new Exception("Database update failed.");
        }
        $stmt->close();

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => false,
            'message' => 'Upload failed: ' . $e->getMessage()
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'No file uploaded.']);
}

$dbconnection->close();
?>