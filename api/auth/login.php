<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/Validator.php';
require_once __DIR__ . '/../../config/api_response.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;


if (file_exists(dirname(__DIR__) . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/..');
    $dotenv->load();
}

if (empty($_ENV['JWT_SECRET'])) {
    error_log('Missing JWT_SECRET in .env');
    apiResponse(false, 'Server configuration error.', 500);
    exit;
}

$key = $_ENV['JWT_SECRET'];

$data = json_decode(file_get_contents("php://input"));
$useremail = strtolower(trim($data->mail ?? ''));
$userpassword = $data->pword ?? '';

$validator = new Validator([
    'email' => $useremail,
    'password' => $userpassword,
]);
$validator->labels([
    'email' => 'Email',
    'password' => 'Password',
]);

$validator->rule('email', 'required|email');
$validator->rule('password', 'required');

if (!$validator->validate()) {
    apiResponse(false, 'Validation failed.', 400, [], $validator->errors());
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
            if ($user['suspended'] == 1) {
                apiResponse(false, 'Your account has been suspended. Please contact support.', 403);
                exit;
            }
            $payload = [
                'user_id' => $user['user_id'],
                'role' => $user['role'],
                'email' => $user['email'],
                'exp' => time() + 10800,
                'iat' => time()
            ];
            $jwt = JWT::encode($payload, $key, 'HS256');
            apiResponse(true, 'Login successful.', 200, [
                'token' => $jwt,
                'user' => [
                    'user_id' => $user['user_id'],
                    'role' => $user['role'],
                    'email' => $user['email']
                ]
            ]);
            exit;
        } else {
            apiResponse(false, 'Incorrect password.', 401);
            exit;
        }
    } else {
        apiResponse(false, 'User not found. Please try signing up.', 404);
        exit;
    }
} else {
    apiResponse(false, 'Login failed. Please try again later.', 500);
    exit;
}
