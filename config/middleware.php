<?php
require_once __DIR__ . '/headers.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/api_response.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Firebase\JWT\JWT;

function validateJWT($required_role = null)
{
    if (file_exists(dirname(__DIR__) . '/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
        $dotenv->load();
    }

    if (empty($_ENV['JWT_SECRET'])) {
        error_log('Missing JWT_SECRET in .env');
        apiResponse(false, 'Server configuration error.', 500);
        exit;
    }

    $key = $_ENV['JWT_SECRET'];

    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? '';

    if (!$auth || !str_starts_with($auth, 'Bearer ')) {
        apiResponse(false, 'No token provided.', 401);
        exit;
    }

    $jwt = str_replace('Bearer ', '', $auth);

    try {
        $decoded = JWT::decode($jwt, new \Firebase\JWT\Key($key, 'HS256'));
        $user_id = $decoded->user_id;
        $role = $decoded->role;

        // UPDATED LOGIC: Support Array or String
        if ($required_role) {
            if (is_array($required_role)) {
                // Check if user's role is in the allowed list
                if (!in_array($role, $required_role)) {
                    apiResponse(false, 'Access denied. Role not authorized.', 403);
                    exit;
                }
            } else {
                // Check exact match for single string
                if ($role !== $required_role) {
                    apiResponse(false, "Access denied. Requires $required_role role.", 403);
                    exit;
                }
            }
        }

        return ['user_id' => $user_id, 'role' => $role];
    } catch (Exception $e) {
        apiResponse(false, 'Token expired or invalid.', 401);
        exit;
    }
}
?>    
