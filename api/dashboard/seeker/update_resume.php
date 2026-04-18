<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/Validator.php';
require_once __DIR__ . '/../../../config/api_response.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$resumeData = json_decode(file_get_contents('php://input'));
$overview = $resumeData->overview ?? null;
$education = $resumeData->education ?? null;
$resume_skills = $resumeData->resume_skills ?? null;
$experience = $resumeData->experience ?? null;

$validator = new Validator([
    'overview' => $overview,
    'experience' => $experience,
]);

$validator->rule('overview', 'required|max:500');
$validator->rule('experience', 'required|max:1000');

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
if (is_array($education) && count($education) == 0) {
    $errors['education'] = 'At least one education entry is required.';
}
if (is_array($resume_skills) && count($resume_skills) == 0) {
    $errors['resume_skills'] = 'At least one skill is required.';
}
if (!$validator->validate()) {
    $errors = array_merge($validator->errors(), $errors);
}

if (!empty($errors)) {
    apiResponse(false, 'Validation failed.', 400, [], $errors);
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
        apiResponse(true, 'Resume updated successfully.');
    } else {
        apiResponse(false, 'Resume update failed.', 500);
    }
} else {
    apiResponse(false, 'Invalid request method.', 405);
}

$dbconnection->close();
?>
