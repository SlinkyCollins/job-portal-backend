<?php
require_once 'headers.php';
require 'connect.php';
require 'vendor/autoload.php';
use Firebase\JWT\JWT;

function validateJWT($required_role = null) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';
    $key = $_ENV['JWT_SECRET'];

    if (!$auth || !str_starts_with($auth, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['status' => false, 'msg' => 'No token provided']);
        exit;
    }

    $jwt = str_replace('Bearer ', '', $auth);

    try {
        $decoded = JWT::decode($jwt, new \Firebase\JWT\Key($key, 'HS256'));
        $user_id = $decoded->user_id;
        $role = $decoded->role;

        if ($required_role && $role !== $required_role) {
            http_response_code(403);
            echo json_encode(['status' => false, 'msg' => "Access denied. Requires $required_role role."]);
            exit;
        }

        return ['user_id' => $user_id, 'role' => $role];
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(['status' => false, 'msg' => 'Token expired or invalid']);
        exit;
    }
}
?>