<?php
// session_start();
if (!isset($_SESSION['user'])) {
    http_response_code(401);  // Unauthorized
    echo json_encode(['msg' => 'Unauthorized. Please log in.']);
    exit();
}

// ini_set('session.gc_maxlifetime', 3600);  // 1 hour


// if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
//     header('HTTP/1.1 200 OK');
//     exit();
// }

// if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
//     http_response_code(200);
//     exit();
// }

// // Fetch job seeker data (applications, saved jobs)
// $query = "SELECT * FROM applications_table WHERE seeker_id = ?";
// $stmt = $dbconnection->prepare($query);
// $stmt->bind_param('i', $user_id);
// $stmt->execute();
// $result = $stmt->get_result();

// if ($result->num_rows > 0) {
//     // $applications = $result->fetch_all(MYSQLI_ASSOC);
//     $row = $result->fetch_assoc();
//     $applications[] = $row;
//     echo json_encode(['status' => true, 'applications' => $applications]);
// } else {
//     echo json_encode(['status' => false, 'msg' => 'No applications found']);
// }


// session_set_cookie_params(3600); // 1-hour session

// if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
//     session_unset();
//     session_destroy();
// }
// $_SESSION['LAST_ACTIVITY'] = time();
?>
