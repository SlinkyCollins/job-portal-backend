<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Kreait\Firebase\Factory;

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

$data = json_decode(file_get_contents("php://input"), true);
$token = $data['token'] ?? null;
$photoURL = $data['photoURL'] ?? '';

if (!$token) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'No token provided']);
    exit;
}

// Load Firebase credentials: Use JSON string from env (prod) or file path (local)
if (!empty($_ENV['FIREBASE_CREDENTIALS'])) {
    // Production: Decode JSON string from env
    $firebaseCredentials = json_decode($_ENV['FIREBASE_CREDENTIALS'], true);
} else {
    // Local: Load from file path
    $firebaseCredentialsPath = dirname(__DIR__, 2) . '/' . ($_ENV['FIREBASE_CREDENTIALS_PATH'] ?? 'config/jobnet-af0a7-firebase-adminsdk-fbsvc-71e1856708.json');
    if (!file_exists($firebaseCredentialsPath)) {
        http_response_code(500);
        echo json_encode(['status' => false, 'msg' => 'Firebase config file missing']);
        exit;
    }
    $firebaseCredentials = $firebaseCredentialsPath;  // Pass path directly
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

    $query = "SELECT * FROM users_table WHERE email = ?";
    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();

        // Update profile_pic_url if provided and not already set
        if (!empty($photoURL)) {
            $updatePhoto = $dbconnection->prepare("UPDATE job_seekers_table SET profile_pic_url = ? WHERE user_id = ? AND (profile_pic_url IS NULL OR profile_pic_url = '')");
            $updatePhoto->bind_param('si', $photoURL, $user['user_id']);
            $updatePhoto->execute();
            $updatePhoto->close();
        }

        // Update linked_providers if empty (in users_table)
        $linkedProvidersJson = json_encode([$provider]);
        $updateProviders = $dbconnection->prepare("UPDATE users_table SET linked_providers = ? WHERE user_id = ? AND (linked_providers IS NULL OR linked_providers = '' OR linked_providers = '[]')");
        $updateProviders->bind_param('si', $linkedProvidersJson, $user['user_id']);
        $updateProviders->execute();
        $updateProviders->close();

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
        echo json_encode(['status' => false, 'newUser' => true, 'token' => $token]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(401);
    error_log("Token verification failed: " . $e->getMessage());
    echo json_encode(['status' => false, 'msg' => 'Invalid token']);
    exit;
}
?>