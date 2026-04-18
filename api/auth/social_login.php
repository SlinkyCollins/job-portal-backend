<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/api_response.php';
require_once __DIR__ . '/../../vendor/autoload.php';
use Firebase\JWT\JWT;
use Kreait\Firebase\Factory;

if (file_exists(dirname(__DIR__) . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__) . '/..');
    $dotenv->load();
}

if (empty($_ENV['JWT_SECRET'])) {
    apiResponse(false, 'Server configuration error.', 500);
    exit;
}

$key = $_ENV['JWT_SECRET'];
$data = json_decode(file_get_contents("php://input"), true);
$token = $data['token'] ?? null;
$photoURL = $data['photoURL'] ?? '';

if (!$token) {
    apiResponse(false, 'No token provided.', 400);
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
    $uid = $verifiedIdToken->claims()->get('sub'); // This is the Firebase UID
    $email = $verifiedIdToken->claims()->get('email');
    $firebaseClaims = $verifiedIdToken->claims()->get('firebase', []);
    $provider = $firebaseClaims['sign_in_provider'] ?? 'unknown';

    // Map provider to DB column
    $idColumn = '';
    $providerName = '';
    if ($provider === 'google.com') {
        $idColumn = 'google_id';
        $providerName = 'google';
    } elseif ($provider === 'facebook.com') {
        $idColumn = 'facebook_id';
        $providerName = 'facebook';
    }

    // 1. Try to find user by Specific ID (Best match) OR Email (Legacy/Linking match)
    // We also check firebase_uid for backward compatibility
    $query = "SELECT * FROM users_table WHERE email = ? OR firebase_uid = ?";
    if ($idColumn) {
        $query .= " OR $idColumn = ?";
    }

    $stmt = $dbconnection->prepare($query);
    if ($idColumn) {
        $stmt->bind_param('sss', $email, $uid, $uid);
    } else {
        $stmt->bind_param('ss', $email, $uid);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userId = $user['user_id'];

        // Add suspended check here
        if ($user['suspended'] == 1) {
            apiResponse(false, 'Your account has been suspended. Please contact support.', 403);
            exit;
        }

        // --- AUTO-LINKING LOGIC ---
        // If we found them by Email, but the Social ID column is empty, fill it now.
        if ($idColumn && empty($user[$idColumn])) {
            $updateId = $dbconnection->prepare("UPDATE users_table SET $idColumn = ? WHERE user_id = ?");
            $updateId->bind_param('si', $uid, $userId);
            $updateId->execute();
            $updateId->close();
        }

        // Update firebase_uid if empty (legacy support)
        if (empty($user['firebase_uid'])) {
            $updateFid = $dbconnection->prepare("UPDATE users_table SET firebase_uid = ? WHERE user_id = ?");
            $updateFid->bind_param('si', $uid, $userId);
            $updateFid->execute();
            $updateFid->close();
        }

        // Update profile_pic_url if needed
        if (!empty($photoURL)) {
            $table = ($user['role'] === 'employer') ? 'employers_table' : 'job_seekers_table';
            $updatePhoto = $dbconnection->prepare("UPDATE $table SET profile_pic_url = ? WHERE user_id = ? AND (profile_pic_url IS NULL OR profile_pic_url = '')");
            $updatePhoto->bind_param('si', $photoURL, $userId);
            $updatePhoto->execute();
            $updatePhoto->close();
        }

        // Update linked_providers array
        $currentProviders = json_decode($user['linked_providers'] ?? '[]', true);
        if ($providerName && !in_array($providerName, $currentProviders)) {
            $currentProviders[] = $providerName;
            $newProvidersJson = json_encode($currentProviders);

            $updateProv = $dbconnection->prepare("UPDATE users_table SET linked_providers = ? WHERE user_id = ?");
            $updateProv->bind_param('si', $newProvidersJson, $userId);
            $updateProv->execute();
            $updateProv->close();
        }

        // Generate JWT
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
        // User not found -> Send to Role Selection (Registration)
        apiResponse(false, 'User account not found. Continue registration.', 200, [
            'newUser' => true,
            'token' => $token
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("Token verification failed: " . $e->getMessage());
    apiResponse(false, 'Invalid token.', 401);
    exit;
}
?>
