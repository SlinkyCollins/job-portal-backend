<?php
require 'connect.php';
require_once 'headers.php';

$response = [];

$query = "SELECT job_id, job_title, job_description, employer_id, location, salary, job_type, qualifications, deadline, createdat FROM jobs_table ORDER BY createdat DESC";


$result = $dbconnection->query($query);

if ($result && $result->num_rows > 0) {
    $jobs = [];

    while ($row = $result->fetch_assoc()) {
        // Decode qualifications if it's stored as JSON
        $row['qualifications'] = json_decode($row['qualifications'], true);
        $jobs[] = $row;
    }

    $response = [
        'status' => true,
        'jobs' => $jobs
    ];
} else {
    $response = [
        'status' => false,
        'msg' => 'No jobs found'
    ];
}

echo json_encode($response);
$dbconnection->close();
