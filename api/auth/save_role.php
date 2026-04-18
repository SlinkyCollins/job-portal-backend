<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/api_response.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Kreait\Firebase\Factory;
use Lcobucci\JWT\Token\Plain;
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
$token = $data->token;
$role = $data->role;
$photoURL = $data->photoURL ?? '';
$termsAccepted = $data->termsAccepted ?? false;

if (!$token || !$role || !in_array($role, ['job_seeker', 'employer'])) {
    apiResponse(false, 'Token and valid role are required.', 400);
    exit;
}

if ($termsAccepted !== true) {
    apiResponse(false, 'You must accept the Terms and Conditions.', 400);
    exit;
}

// Load Firebase credentials
if (!empty($_ENV['FIREBASE_CREDENTIALS'])) {
    $firebaseCredentials = json_decode($_ENV['FIREBASE_CREDENTIALS'], true);
} else {
    $firebaseCredentialsPath = dirname(__DIR__, 2) . '/' . ($_ENV['FIREBASE_CREDENTIALS_PATH'] ?? 'config/jobnet-af0a7-firebase-adminsdk-fbsvc-71e1856708.json');
    if (!file_exists($firebaseCredentialsPath)) {
        apiResponse(false, 'Firebase config file missing.', 500);
        exit;
    }
    $firebaseCredentials = $firebaseCredentialsPath;
}

$factory = (new Factory)->withServiceAccount($firebaseCredentials);
$auth = $factory->createAuth();

try {
    /** @var Plain $verifiedIdToken */
    $verifiedIdToken = $auth->verifyIdToken($token);
    $uid = $verifiedIdToken->claims()->get('sub');
    $email = $verifiedIdToken->claims()->get('email');
    $name = $verifiedIdToken->claims()->get('name') ?? '';
    $firebaseClaims = $verifiedIdToken->claims()->get('firebase', []);
    $provider = $firebaseClaims['sign_in_provider'] ?? 'unknown';
    
    $nameParts = explode(' ', $name);
    $firstname = $nameParts[0] ?? '';
    $lastname = implode(' ', array_slice($nameParts, 1)) ?? '';
} catch (Exception $e) {
    apiResponse(false, 'Invalid Firebase token.', 401);
    exit;
}

// Map provider to DB column
$googleId = null;
$facebookId = null;
$providerName = '';

if ($provider === 'google.com') {
    $googleId = $uid;
    $providerName = 'google';
} elseif ($provider === 'facebook.com') {
    $facebookId = $uid;
    $providerName = 'facebook';
}

// Check if user already exists (Safety check)
$checkQuery = "SELECT user_id FROM users_table WHERE email = ? OR firebase_uid = ?";
$checkStmt = $dbconnection->prepare($checkQuery);
$checkStmt->bind_param('ss', $email, $uid);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    apiResponse(false, 'User already exists.', 409);
    exit;
}
$checkStmt->close();

// Insert new user with specific ID columns
$query = "INSERT INTO users_table (firstname, lastname, email, role, firebase_uid, google_id, facebook_id, terms_accepted, linked_providers) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $dbconnection->prepare($query);
$termsInt = $termsAccepted ? 1 : 0;
$linkedProvidersJson = json_encode($providerName ? [$providerName] : []);

$stmt->bind_param('sssssssis', $firstname, $lastname, $email, $role, $uid, $googleId, $facebookId, $termsInt, $linkedProvidersJson);

if ($stmt->execute()) {
    $userId = $dbconnection->insert_id;

    // Create Role Table Entry
    $table = ($role === 'employer') ? 'employers_table' : 'job_seekers_table';
    $insertRole = $dbconnection->prepare("INSERT INTO $table (user_id, profile_pic_url) VALUES (?, ?)");
    $insertRole->bind_param('is', $userId, $photoURL);
    $insertRole->execute();
    $insertRole->close();

    $payload = ['user_id' => $userId, 'role' => $role, 'email' => $email, 'exp' => time() + 10800, 'iat' => time()];
    $jwt = JWT::encode($payload, $key, 'HS256');

    apiResponse(true, 'Role saved and login successful.', 200, [
        'token' => $jwt,
        'user' => [
            'user_id' => $userId,
            'role' => $role,
            'email' => $email
        ]
    ]);
} else {
    apiResponse(false, 'Unable to save role.', 500);
}
$stmt->close();
$dbconnection->close();
?>
