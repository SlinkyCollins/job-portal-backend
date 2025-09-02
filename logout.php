<?php
require_once 'headers.php';
// Clear JWT cookie
setcookie('jwt', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => 'jobnet.vercel.app',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict'
]);

http_response_code(200);
echo json_encode(['status' => true, 'msg' => 'Logged out successfully']);
?>
