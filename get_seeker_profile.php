<?php
require_once 'headers.php';
require 'connect.php';
require_once 'middleware.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$query = "SELECT cv_url, cv_filename, skills, experience_years, bio FROM job_seekers_table WHERE user_id = ?";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $profile = $result->fetch_assoc();
    echo json_encode(['status' => true, 'profile' => $profile]);
} else {
    echo json_encode(['status' => false, 'msg' => 'Profile not found.']);
}

$stmt->close();
$dbconnection->close();