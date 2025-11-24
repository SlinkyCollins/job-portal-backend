<?php
require_once __DIR__ . '/../../config/headers.php';

http_response_code(200);
echo json_encode(['status' => true, 'msg' => 'Logged out successfully']);
?>
