<?php
require_once 'headers.php';
require 'connect.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Lcobucci\JWT\Token\Plain;

$data = json_decode(file_get_contents("php://input"), true);
$token = $data['token'] ?? null;

if (!$token) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'No token provided']);
    exit;
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$key = $_ENV('JWT_SECRET');
if (empty($key)) {
    error_log('Missing JWT_SECRET in .env');
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Server configuration error']);
    exit;
}

$factory = (new Factory)->withServiceAccount(json_decode($_ENV['FIREBASE_CREDENTIALS'] ?? '{}', true));
$auth = $factory->createAuth();

try {
    /** @var Plain $verifiedIdToken */
    $verifiedIdToken = $auth->verifyIdToken($token);
    $uid = $verifiedIdToken->claims()->get('sub');
    $email = $verifiedIdToken->claims()->get('email');
    $name = $verifiedIdToken->claims()->get('name') ?? '';

    $nameParts = explode(' ', $name);
    $firstname = $nameParts[0] ?? '';
    $lastname = implode(' ', array_slice($nameParts, 1)) ?? '';

    $query = "SELECT * FROM users_table WHERE email = ?";
    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $payload = [
                'user_id' => $user['user_id'],
                'role' => $user['role'],
                'email' => $user['email'],
                'exp' => time() + 900 // 15 mins
            ];
            $jwt = JWT::encode($payload, $key, 'HS256');
            echo json_encode([
                'status' => true,
                'msg' => 'Login successful',
                'user' => [
                    'user_id' => $user['user_id'],
                    'role' => $user['role'],
                    'email' => $user['email']
                ]
            ]);
            exit;
    } else {
        echo json_encode(['status' => false, 'newUser' => true, 'token' => $token]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    error_log("Token verification failed: " . $e->getMessage());
    echo json_encode(['status' => false, 'msg' => 'Invalid token: ' . $e->getMessage()]);
}

$dbconnection->close();
