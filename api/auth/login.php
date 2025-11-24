<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;


if (file_exists(dirname(__DIR__) . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__ ) . '/..');
    $dotenv->load();
}

if (empty($_ENV['JWT_SECRET'])) {
    error_log('Missing JWT_SECRET in .env');
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Server configuration error']);
    exit;
}

$key = $_ENV['JWT_SECRET'];

$data = json_decode(file_get_contents("php://input"));
$useremail = $data->mail ?? '';
$userpassword = $data->pword ?? '';

if (!$useremail || !$userpassword) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Email and password are required']);
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
            $payload = [
                'user_id' => $user['user_id'],
                'role' => $user['role'],
                'email' => $user['email'],
                'exp' => time() + 10800,
                'iat' => time()
            ];
            $jwt = JWT::encode($payload, $key, 'HS256');
            echo json_encode([
                'status' => true,
                'msg' => 'Login successful',
                'token' => $jwt,
                'user' => [
                    'user_id' => $user['user_id'],
                    'role' => $user['role'],
                    'email' => $user['email']
                ]
            ]);
            exit;
        } else {
            http_response_code(401);
            echo json_encode(['status' => false, 'msg' => 'Incorrect password']);
            exit;
        }
    } else {
        http_response_code(404);
        echo json_encode(['status' => false, 'msg' => 'User not found, please try signing up']);
        exit;
    }
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Login failed, please try again later']);
    exit;
}