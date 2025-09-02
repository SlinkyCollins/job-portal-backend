<?php
require_once 'headers.php';
require 'connect.php';
require 'vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Lcobucci\JWT\Token\Plain;
use Firebase\JWT\JWT;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$data = json_decode(file_get_contents("php://input"));
$token = $data->token;
$role = $data->role;

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

    // Insert new user (no password for social)
    $query = "INSERT INTO users_table (firstname, lastname, email, role, firebase_uid) VALUES (?, ?, ?, ?, ?)";
    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param('sssss', $firstname, $lastname, $email, $role, $uid);
     if ($stmt->execute()) {
        $userId = $dbconnection->insert_id;
        // Issue JWT
        $key = $_ENV('JWT_SECRET');
        $payload = ['user_id' => $userId, 'role' => $role, 'email' => $email, 'exp' => time() + 900];
        $jwt = JWT::encode($payload, $key, 'HS256');
        setcookie('jwt', $jwt, [
            'expires' => time() + 900,
            'path' => '/',
            'domain' => 'jobnet.vercel.app',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        echo json_encode(['status' => true, 'msg' => 'Role saved and logged in']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => false, 'msg' => 'Error saving role']);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['status' => false, 'msg' => 'Invalid token: ' . $e->getMessage()]);
}

$dbconnection->close();
?>