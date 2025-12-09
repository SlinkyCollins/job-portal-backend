<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
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
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Server configuration error']);
    exit;
}

$key = $_ENV['JWT_SECRET'];

$data = json_decode(file_get_contents("php://input"));
$token = $data->token;
$role = $data->role;
$photoURL = $data->photoURL ?? '';
// 1. Capture terms acceptance
$termsAccepted = $data->termsAccepted ?? false;

if (!$token || !$role || !in_array($role, ['job_seeker', 'employer'])) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Token and valid role are required']);
    exit;
}

// 2. Validate Terms Acceptance
if ($termsAccepted !== true) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'You must accept the Terms and Conditions']);
    exit;
}

// Load Firebase credentials
if (!empty($_ENV['FIREBASE_CREDENTIALS'])) {
    $firebaseCredentials = json_decode($_ENV['FIREBASE_CREDENTIALS'], true);
} else {
    $firebaseCredentialsPath = dirname(__DIR__, 2) . '/' . ($_ENV['FIREBASE_CREDENTIALS_PATH'] ?? 'config/jobnet-af0a7-firebase-adminsdk-fbsvc-71e1856708.json');
    if (!file_exists($firebaseCredentialsPath)) {
        http_response_code(500);
        echo json_encode(['status' => false, 'msg' => 'Firebase config file missing']);
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
    error_log('Firebase token verification failed: ' . $e->getMessage());
    http_response_code(401);
    echo json_encode(['status' => false, 'msg' => 'Invalid Firebase token']);
    exit;
}

// Check if user already exists
$checkQuery = "SELECT user_id FROM users_table WHERE firebase_uid = ?";
$checkStmt = $dbconnection->prepare($checkQuery);
$checkStmt->bind_param('s', $uid);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['status' => false, 'msg' => 'User already exists']);
    $checkStmt->close();
    exit;
}
$checkStmt->close();

// 3. Insert new user (Added terms_accepted to query)
// Ensure your users_table has a 'terms_accepted' column (TINYINT/BOOLEAN)
$query = "INSERT INTO users_table (firstname, lastname, email, role, firebase_uid, terms_accepted) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $dbconnection->prepare($query);
$termsInt = $termsAccepted ? 1 : 0;
$stmt->bind_param('sssssi', $firstname, $lastname, $email, $role, $uid, $termsInt);

if ($stmt->execute()) {
    $userId = $dbconnection->insert_id;

    // Auto-create role-based record
    if ($role === 'job_seeker') {
        $insertJobSeeker = $dbconnection->prepare("INSERT INTO job_seekers_table (user_id) VALUES (?)");
        $insertJobSeeker->bind_param('i', $userId);
        $insertJobSeeker->execute();

        if (!empty($photoURL)) {
            $updatePhoto = $dbconnection->prepare("UPDATE job_seekers_table SET profile_pic_url = ? WHERE user_id = ?");
            $updatePhoto->bind_param('si', $photoURL, $userId);
            $updatePhoto->execute();
            $updatePhoto->close();
        }

        $linkedProvidersJson = json_encode([$provider]);
        $updateUser = $dbconnection->prepare("UPDATE users_table SET linked_providers = ? WHERE user_id = ?");
        $updateUser->bind_param('si', $linkedProvidersJson, $userId);
        $updateUser->execute();
        $updateUser->close();

        $insertJobSeeker->close();
    } elseif ($role === 'employer') {
        $insertEmployer = $dbconnection->prepare("INSERT INTO employers_table (user_id) VALUES (?)");
        $insertEmployer->bind_param('i', $userId);
        $insertEmployer->execute();

        if (!empty($photoURL)) {
            $updatePhoto = $dbconnection->prepare("UPDATE employers_table SET profile_pic_url = ? WHERE user_id = ?");
            $updatePhoto->bind_param('si', $photoURL, $userId);
            $updatePhoto->execute();
            $updatePhoto->close();
        }

        $linkedProvidersJson = json_encode([$provider]);
        $updateUser = $dbconnection->prepare("UPDATE users_table SET linked_providers = ? WHERE user_id = ?");
        $updateUser->bind_param('si', $linkedProvidersJson, $userId);
        $updateUser->execute();
        $updateUser->close();

        $insertEmployer->close();
    }

    $payload = ['user_id' => $userId, 'role' => $role, 'email' => $email, 'exp' => time() + 10800, 'iat' => time()];
    $jwt = JWT::encode($payload, $key, 'HS256');
    echo json_encode([
        'status' => true,
        'msg' => 'Role saved and logged in',
        'token' => $jwt,
        'user' => [
            'user_id' => $userId,
            'role' => $role,
            'email' => $email
        ]
    ]);
} else {
    error_log('Database error: ' . $stmt->error);
    http_response_code(500);
     echo json_encode(['status' => false, 'msg' => 'Database error: Unable to save role']);
}
$stmt->close();
$dbconnection->close();
?>