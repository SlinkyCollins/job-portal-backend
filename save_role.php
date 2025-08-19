<?php
require_once 'headers.php';
require 'session_config.php';
require 'connect.php';
require 'vendor/autoload.php';

use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Lcobucci\JWT\Token\Plain;

$data = json_decode(file_get_contents("php://input"));
$token = $data->token;
$role = $data->role;

$factory = (new Factory)->withServiceAccount('jobnet-af0a7-firebase-adminsdk-fbsvc-71e1856708.json');
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
    $stmt->execute();

    $userId = $dbconnection->insert_id;
    $_SESSION['user'] = ['id' => $userId, 'role' => $role];

    echo json_encode(['status' => true, 'user' => $_SESSION['user']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Error saving role: ' . $e->getMessage()]);
}

$dbconnection->close();
?>