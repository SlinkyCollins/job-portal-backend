<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    $maxSize = 5 * 1024 * 1024;  // 5MB limit for images

    // Validate file
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['status' => false, 'message' => 'Invalid file type. Only JPEG, PNG, and GIF allowed.']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['status' => false, 'message' => 'File too large. Max 5MB.']);
        exit;
    }

    try {
        // Upload to Cloudinary
        $uploadResult = $cloudinary->uploadApi()->upload($file['tmp_name'], [
            'folder' => 'jobnet/profile_photos',
            'resource_type' => 'image',
            'public_id' => uniqid('profile_'),
            'filename' => $file['name']
        ]);

        $photo_url = $uploadResult['secure_url'];
        $public_id = $uploadResult['public_id'];

        // Update database
        $update = $dbconnection->prepare("UPDATE job_seekers_table SET profile_pic_url = ?, profile_pic_public_id = ? WHERE user_id = ?");
        $update->bind_param('ssi', $photo_url, $public_id, $user_id);
        $update->execute();
        $update->close();

        echo json_encode([
            'status' => true,
            'msg' => 'Profile photo uploaded successfully',
            'photoURL' => $photo_url,
            'public_id' => $public_id
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => false,
            'message' => 'Upload failed: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['status' => false, 'message' => 'No file uploaded or invalid request.']);
}

$dbconnection->close();
?>