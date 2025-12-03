<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$query =
    "SELECT 
    js.fullname,
    js.phone,
    js.bio,
    js.address,
    js.country,
    js.profile_pic_url,
    js.cv_url,
    js.cv_filename,
    js.overview,
    js.education,
    js.resume_skills,
    js.experience,
    u.linked_providers,
    u.firstname,
    u.lastname 
FROM job_seekers_table js 
JOIN users_table u ON js.user_id = u.user_id 
WHERE js.user_id = ?";

$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    // Pre-fill name
    $profile = $result->fetch_assoc();
    $profile['fullname'] = trim(($profile['firstname'] ?? '') . ' ' . ($profile['lastname'] ?? ''));
    echo json_encode(['status' => true, 'profile' => $profile]);
} else {
    echo json_encode(['status' => false, 'msg' => 'Profile not found.']);
}

$stmt->close();
$dbconnection->close();