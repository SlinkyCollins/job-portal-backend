<?php
session_start();
require_once 'headers.php';

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