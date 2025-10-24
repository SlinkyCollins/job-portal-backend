<?php
require_once 'headers.php';
require 'connect.php';
require_once 'middleware.php';
require 'config/cloudinary_config.php';

$user = validateJWT('job_seeker');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];  // PDF, DOCX
    $maxSize = 10 * 1024 * 1024;  // 10MB

    // Validate file
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['status' => false, 'message' => 'Invalid file type. Only PDF and DOCX allowed.']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['status' => false, 'message' => 'File too large. Max 5MB.']);
        exit;
    }

    // Determine extension
    $extension = '';
    if ($file['type'] === 'application/pdf') {
        $extension = '.pdf';
    } elseif ($file['type'] === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
        $extension = '.docx';
    }

    try {
        // Upload with extension in public_id
        $uploadResult = $cloudinary->uploadApi()->upload($file['tmp_name'], [
            'folder' => 'jobnet/cvs',
            'resource_type' => 'raw',  // Let Cloudinary detect (should work for PDFs/DOCX)
            'public_id' => uniqid('cv_') . $extension,
            'filename' => $file['name'] // Add this parameter to use the original file name
        ]);

        $cv_url = $uploadResult['secure_url'];

        // Assuming $cv_url is the URL you received from the upload result
        $download_url = str_replace(
            'raw/upload/',
            'raw/upload/fl_attachment/',
            $cv_url
        );

        // Now provide the $download_url to your user
        echo json_encode([
            'status' => true,
            'msg' => 'CV uploaded successfully',
            'url' => $download_url,
            'public_id' => $uploadResult['public_id']
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