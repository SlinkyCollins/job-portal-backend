<?php
require_once 'headers.php';
require 'connect.php';
require_once 'middleware.php';
require 'config/cloudinary_config.php';

$user = validateJWT('job_seeker');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];  // PDF, DOCX
    $maxSize = 5 * 1024 * 1024;  // 5MB

    // Validate file
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['status' => false, 'message' => 'Invalid file type. Only PDF and DOCX allowed.']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['status' => false, 'message' => 'File too large. Max 5MB.']);
        exit;
    }

    try {
        // Upload to Cloudinary
        $uploadResult = $cloudinary->uploadApi()->upload($file['tmp_name'], [
            'folder' => 'jobnet/cvs',
            'resource_type' => 'raw',  // For non-image files
            'public_id' => uniqid('cv_'),  // Unique ID for the file
        ]);

        echo json_encode([
            'status' => true,
            'url' => $uploadResult['secure_url'],
            'public_id' => $uploadResult['public_id']  // Useful for deletion later
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