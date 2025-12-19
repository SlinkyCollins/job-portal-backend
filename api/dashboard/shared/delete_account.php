<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';
require_once __DIR__ . '/../../../vendor/autoload.php'; 

use Kreait\Firebase\Factory;

// 1. Validate JWT (Any Role)
$user = validateJWT();
$user_id = $user['user_id'];
$role = $user['role'];

$input = json_decode(file_get_contents('php://input'));
$confirmation = $input->confirmation ?? '';

if ($confirmation !== 'DELETE') {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid confirmation text']);
    exit;
}

try {
    $dbconnection->begin_transaction();

    // 2. Determine Table based on Role
    $table = ($role === 'employer') ? 'employers_table' : 'job_seekers_table';

    // 3. Fetch Cloudinary IDs
    // Note: Both tables use 'profile_pic_public_id'. Only seekers have 'cv_public_id'.
    $query = "SELECT u.firebase_uid, t.profile_pic_public_id ";
    if ($role === 'job_seeker') {
        $query .= ", t.cv_public_id ";
    }
    $query .= "FROM users_table u LEFT JOIN $table t ON u.user_id = t.user_id WHERE u.user_id = ?";

    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $userData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // 4. Delete from Cloudinary
    if ($userData) {
        $uploadApi = $cloudinary->uploadApi();
        
        // Profile Pic (Both roles)
        if (!empty($userData['profile_pic_public_id'])) {
            try { $uploadApi->destroy($userData['profile_pic_public_id'], ['resource_type' => 'image']); } catch (Exception $e) {}
        }

        // CV (Seeker only)
        if ($role === 'job_seeker' && !empty($userData['cv_public_id'])) {
            try { $uploadApi->destroy($userData['cv_public_id'], ['resource_type' => 'raw']); } catch (Exception $e) {}
        }
    }

    // --- 5. Delete from Firebase Auth (Best Effort) ---
    if ($userData && !empty($userData['firebase_uid'])) {
        try {
            // Initialize Firebase Admin SDK
            $firebaseCredentials = null;

            // 1. Production: Check for JSON string in environment variable
            if (!empty($_ENV['FIREBASE_CREDENTIALS'])) {
                $firebaseCredentials = json_decode($_ENV['FIREBASE_CREDENTIALS'], true);
            }
            // 2. Local: Fallback to file path
            else {
                $firebaseCredentialsPath = dirname(__DIR__, 2) . '/' . ($_ENV['FIREBASE_CREDENTIALS_PATH'] ?? 'config/jobnet-af0a7-firebase-adminsdk-fbsvc-71e1856708.json');

                if (!file_exists($firebaseCredentialsPath)) {
                    // Throw exception to be caught below, logging the error but allowing DB delete to proceed
                    throw new Exception("Firebase credentials file missing at: " . $firebaseCredentialsPath);
                }
                $firebaseCredentials = $firebaseCredentialsPath;
            }

            $factory = (new Factory)->withServiceAccount($firebaseCredentials);
            $auth = $factory->createAuth();

            // Delete the user from Firebase Auth
            $auth->deleteUser($userData['firebase_uid']);

        } catch (\Kreait\Firebase\Exception\Auth\UserNotFound $e) {
            // User already deleted in Firebase, continue safely
        } catch (Exception $e) {
            // Log error but continue to ensure local DB account is deleted
            error_log("Firebase Delete Error for user $user_id: " . $e->getMessage());
        }
    }

    // --- 6. Delete the user account from DB ---
    $delStmt = $dbconnection->prepare("DELETE FROM users_table WHERE user_id = ?");
    $delStmt->bind_param('i', $user_id);
    $delStmt->execute();

    $dbconnection->commit();
    echo json_encode(['status' => true, 'message' => 'Account deleted successfully']);

} catch (Exception $e) {
    $dbconnection->rollback();
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
}
?>