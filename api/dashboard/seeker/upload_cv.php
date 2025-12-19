<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $cvfilename = $_POST['filename'] ?? '';  // Fix: Get from POST, not FILES
    $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];  // PDF, DOCX
    $maxSize = 10 * 1024 * 1024;  // 10MB

    // Validate file
    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['status' => false, 'message' => 'Invalid file type. Only PDF and DOCX allowed.']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['status' => false, 'message' => 'File too large. Max 10MB.']);  // Updated to match frontend
        exit;
    }

    // Sanitize filename (basic security)
    $cvfilename = htmlspecialchars(trim($cvfilename), ENT_QUOTES, 'UTF-8');
    if (empty($cvfilename)) {
        echo json_encode(['status' => false, 'message' => 'Filename is required.']);
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
        // Check for existing CV and delete if exists
        $select = $dbconnection->prepare("SELECT cv_public_id FROM job_seekers_table WHERE user_id = ?");
        $select->bind_param('i', $user_id);
        $select->execute();
        $result = $select->get_result();
        $row = $result->fetch_assoc();
        $select->close();

        if ($row && !empty($row['cv_public_id'])) {
            try {
                $cloudinary->uploadApi()->destroy($row['cv_public_id'], ['resource_type' => 'raw']);
            } catch (Exception $e) {
                // Continue even if delete fails
            }
        }

        // Upload with extension in public_id
        $uploadResult = $cloudinary->uploadApi()->upload($file['tmp_name'], [
            'folder' => 'jobnet/cvs',
            'resource_type' => 'raw',  // Let Cloudinary detect (should work for PDFs/DOCX)
            'public_id' => uniqid('cv_') . $extension,
            'filename' => $file['name']  // Use original file name for Cloudinary
        ]);

        $cv_url = $uploadResult['secure_url'];
        $public_id = $uploadResult['public_id'];

        // Create download URL
        $download_url = str_replace(
            'raw/upload/',
            'raw/upload/fl_attachment/',
            $cv_url
        );

        // Update database
        $update = $dbconnection->prepare("UPDATE job_seekers_table SET cv_url = ?, cv_filename = ?, cv_public_id = ? WHERE user_id = ?");
        $update->bind_param('sssi', $download_url, $cvfilename, $public_id, $user_id);
        $update->execute();
        $update->close();

        echo json_encode([
            'status' => true,
            'msg' => 'CV uploaded successfully',
            'url' => $download_url,
            'filename' => $cvfilename,
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