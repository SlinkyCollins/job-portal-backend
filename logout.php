<?php
require_once 'headers.php';
require 'session_config.php';
header("Set-Cookie: JobNetSession=" . session_id() . "; path=/JobPortal; SameSite=None; Secure; HttpOnly");

// Clear session data
$_SESSION = [];
session_unset();
session_destroy();

// Expire the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie('JobNetSession', '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

$response = ['status' => true, 'msg' => 'Logged out successfully'];
http_response_code(200);
echo json_encode($response);
exit();