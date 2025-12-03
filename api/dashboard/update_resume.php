<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$resumeData = json_decode(file_get_contents('php://input'));
$overview = $resumeData->overview ?? null;
$education = $resumeData->education ?? null;
$resume_skills = $resumeData->resume_skills ?? null;
$experience = $resumeData->experience ?? null;

$errors = [];

// Decode and validate education
if ($education !== null) {
    $educationDecoded = json_decode($education);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($educationDecoded)) {
        $errors['education'] = 'Education must be a valid JSON array.';
    } else {
        $education = $educationDecoded;  // Now it's an array
    }
}

// Decode and validate skills
if ($resume_skills !== null) {
    $skillsDecoded = json_decode($resume_skills);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($skillsDecoded)) {
        $errors['resume_skills'] = 'Resume skills must be a valid JSON array.';
    } else {
        $resume_skills = $skillsDecoded;  // Now it's an array
    }
}

// Basic validation
if (empty($overview)) {
    $errors['overview'] = 'Overview is required.';
}
if ($overview !== null && strlen($overview) > 500) {
    $errors['overview'] = 'Overview cannot exceed 500 characters.';
}
if (is_array($education) && count($education) == 0) {
    $errors['education'] = 'At least one education entry is required.';
}
if (is_array($resume_skills) && count($resume_skills) == 0) {
    $errors['resume_skills'] = 'At least one skill is required.';
}
if (empty($experience)) {
    $errors['experience'] = 'Experience is required.';
}
if ($experience !== null && strlen($experience) > 1000) {
    $errors['experience'] = 'Experience cannot exceed 1000 characters.';
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'errors' => $errors]);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Encode arrays to JSON strings for DB
    $educationJson = $education ? json_encode($education) : null;
    $skillsJson = $resume_skills ? json_encode($resume_skills) : null;

    // Update job_seekers_table for resume fields
    $updateSeeker = $dbconnection->prepare("UPDATE job_seekers_table SET overview = ?, education = ?, resume_skills = ?, experience = ? WHERE user_id = ?");
    $updateSeeker->bind_param('ssssi', $overview, $educationJson, $skillsJson, $experience, $user_id);
    $seekerSuccess = $updateSeeker->execute();
    $updateSeeker->close();

    if ($seekerSuccess) {
        echo json_encode(['status' => true, 'msg' => 'Resume updated successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'msg' => 'Resume update failed.']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'msg' => 'Invalid request method.']);
}

$dbconnection->close();
?>