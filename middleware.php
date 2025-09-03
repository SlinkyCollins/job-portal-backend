<?php
require_once 'headers.php';
require 'connect.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;

function validateJWT($required_role = null)
{
    if (file_exists(dirname(__DIR__) . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
    }

    if (empty($_ENV['JWT_SECRET'])) {
        error_log('Missing JWT_SECRET in .env');
        http_response_code(500);
        echo json_encode(['status' => false, 'msg' => 'Server configuration error']);
        exit;
    }

    $key = $_ENV['JWT_SECRET'];

    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';

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
