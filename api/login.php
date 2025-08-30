<?php
require_once 'headers.php';
require 'connect.php';
require 'vendor/autoload.php';
use Firebase\JWT\JWT;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}


$data = json_decode(file_get_contents("php://input"));
$useremail = $data->mail ?? '';
$userpassword = $data->pword ?? '';

if (!$useremail || !$userpassword) {
    http_response_code(400);
    $response = ['status' => false, 'msg' => 'Email and password are required'];
    exit;
}

$queryemail = "SELECT * FROM users_table WHERE email = ?";
$stmt = $dbconnection->prepare($queryemail);
$stmt->bind_param('s', $useremail);
$execute = $stmt->execute();

if ($execute) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $hashedpassword = $user['password'];
        $verifypassword = password_verify($userpassword, $hashedpassword);

        if ($verifypassword) {
            $key = $_ENV['JWT_SECRET'];
            $payload = ['user_id' => $user['user_id'], 'role' => $user['role'], 'email' => $user['email'], 'exp' => time() + 900]; // 15 mins
            $jwt = JWT::encode($payload, $key, 'HS256');
            setcookie('jwt', $jwt, [
                'expires' => time() + 900,
                'path' => '/',
                'domain' => 'jobnet.vercel.app',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
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
            http_response_code(401);
            $response = ['status' => false, 'msg' => 'Incorrect password'];
        }
    } else {
        http_response_code(404);
        $response = ['status' => false, 'msg' => 'User not found, please try signing up'];
    }
} else {
    http_response_code(500);
    $response = ['status' => false, 'msg' => 'Login failed, please try again later'];
}

echo json_encode($response);
$dbconnection->close();
