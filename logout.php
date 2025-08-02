<?php
session_start();

header('Access-Control-Allow-Origin: http://localhost:4200');  // Allow requests from your Angular app
header('Access-Control-Allow-Credentials: true');  // Allow sending cookies (important for sessions)
header('Access-Control-Allow-Methods: POST, OPTIONS');  // Restrict to POST for logout
header('Access-Control-Allow-Headers: Content-Type');  // Handle JSON content
header('Content-Type: application/json');  // Set JSON response type

// Handle preflight (CORS) requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Clear session data
$_SESSION = [];
session_unset();
session_destroy();

// Expire the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

$response = ['status' => true, 'msg' => 'Logged out successfully'];
http_response_code(200);
echo json_encode($response);
exit();
?>