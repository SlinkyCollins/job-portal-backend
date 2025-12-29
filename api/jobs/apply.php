<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';
require_once __DIR__ . '/../../config/cloudinary.php';

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

// Get POST data (JSON for text fields, files for CV)
$job_id = $_POST['jobId'] ?? null;
$cover_letter = $_POST['cover_letter'] ?? null;

if (!$job_id) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Job ID is required.']);
    exit;
}

// Check if job is active and not expired
$jobCheckQuery = "SELECT status, deadline FROM jobs_table WHERE job_id = ?";
$jobCheckStmt = $dbconnection->prepare($jobCheckQuery);
$jobCheckStmt->bind_param('i', $job_id);
$jobCheckStmt->execute();
$jobCheckResult = $jobCheckStmt->get_result();
$job = $jobCheckResult->fetch_assoc();
$jobCheckStmt->close();

if (!$job || $job['status'] !== 'active' || strtotime($job['deadline']) <= time()) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'This job is closed or expired and no longer accepting applications.']);
    exit;
}

// Prevent duplicate applications
$checkQuery = "SELECT application_id FROM applications_table WHERE job_id = ? AND seeker_id = ?";
$checkStmt = $dbconnection->prepare($checkQuery);
$checkStmt->bind_param('ii', $job_id, $user_id);
$checkStmt->execute();
$checkStmt->store_result();

if ($checkStmt->num_rows > 0) {
    echo json_encode(['status' => false, 'msg' => 'You have already applied for this job.', 'hasApplied' => true]);
    exit;
}
$checkStmt->close();

// Handle CV upload or default
$resume_url = null;
$resume_filename = null;
$resume_public_id = null;

if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
    // Upload specific CV to Cloudinary
    $file = $_FILES['cv_file'];
    $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $maxSize = 10 * 1024 * 1024;  // 10MB

    if (!in_array($file['type'], $allowedTypes)) {
        echo json_encode(['status' => false, 'msg' => 'Invalid CV file type. Only PDF and DOCX allowed.']);
        exit;
    }
    if ($file['size'] > $maxSize) {
        echo json_encode(['status' => false, 'msg' => 'CV file too large. Max 10MB.']);
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
        $uploadResult = $cloudinary->uploadApi()->upload($file['tmp_name'], [
            'folder' => 'jobnet/applications_cvs',
            'resource_type' => 'raw',
            'public_id' => uniqid('cv_') . $extension,
            'filename' => $file['name']
        ]);
        $resume_url = $uploadResult['secure_url'];
        $resume_filename = htmlspecialchars(basename($file['name']), ENT_QUOTES, 'UTF-8');
        $resume_public_id = $uploadResult['public_id'];
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['status' => false, 'msg' => 'CV upload failed: ' . $e->getMessage()]);
        exit;
    }
} else {
    // Use default CV from job_seekers_table
    $defaultQuery = "SELECT cv_url, cv_filename, cv_public_id FROM job_seekers_table WHERE user_id = ?";
    $defaultStmt = $dbconnection->prepare($defaultQuery);
    $defaultStmt->bind_param('i', $user_id);
    $defaultStmt->execute();
    $defaultResult = $defaultStmt->get_result();
    $default = $defaultResult->fetch_assoc();
    $defaultStmt->close();

    if ($default && $default['cv_url']) {
        $resume_url = $default['cv_url'];
        $resume_filename = $default['cv_filename'];
        $resume_public_id = $default['cv_public_id'];  // Note: Do NOT delete this on application retraction
    } else {
        echo json_encode(['status' => false, 'msg' => 'No CV available. Please upload a CV in your dashboard first.']);
        exit;
    }
}

// Insert into DB
$query = "INSERT INTO applications_table (job_id, seeker_id, status, cover_letter, resume_url, resume_filename, resume_public_id) VALUES (?, ?, 'pending', ?, ?, ?, ?)";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('iissss', $job_id, $user_id, $cover_letter, $resume_url, $resume_filename, $resume_public_id);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode([
        'status' => true,
        'msg' => 'Application submitted successfully!',
        'hasApplied' => true
    ]);
} else {
    // If insert fails and we uploaded a file, delete it from Cloudinary
    if ($resume_public_id && isset($_FILES['cv_file'])) {
        try {
            $cloudinary->uploadApi()->destroy($resume_public_id, ['resource_type' => 'raw']);
        } catch (Exception $e) {
            // Log error, but don't block response
        }
    }
    http_response_code(500);
    echo json_encode([
        'status' => false,
        'msg' => 'Failed to submit application. Please try again later.',
        'hasApplied' => false
    ]);
}

$stmt->close();
$dbconnection->close();
?>