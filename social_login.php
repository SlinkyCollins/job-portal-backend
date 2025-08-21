<?php
require_once 'headers.php';
require 'session_config.php';
require 'connect.php';
require 'vendor/autoload.php';

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

$factory = (new Factory)->withServiceAccount(__DIR__ . '/config/jobnet-af0a7-firebase-adminsdk-fbsvc-71e1856708.json');
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
        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => $user['user_id'], 'role' => $user['role']];
        echo json_encode(['status' => true, 'user' => $_SESSION['user']]);
    } else {
        echo json_encode(['status' => false, 'newUser' => true, 'token' => $token]);
    }
} catch (Exception $e) {
    http_response_code(401);
    error_log("Token verification failed: " . $e->getMessage());
    echo json_encode(['status' => false, 'msg' => 'Invalid token: ' . $e->getMessage()]);
}

$dbconnection->close();
?>