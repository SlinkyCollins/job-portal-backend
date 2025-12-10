<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';
require_once __DIR__ . '/../../../vendor/autoload.php'; // Autoload for Firebase SDK

use Kreait\Firebase\Factory;

// 1. Validate JWT and Role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => false, 'message' => 'Unauthorized access']);
    exit;
}

// 2. Input Validation
$input = json_decode(file_get_contents('php://input'));
$confirmation = $input->confirmation ?? '';

if ($confirmation !== 'DELETE') {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid confirmation text']);
    exit;
}

// 3. Database Operation with Transaction
try {
    // Start transaction
    $dbconnection->begin_transaction();

    // --- STEP A: Fetch Data (Cloudinary IDs & Firebase UID) ---
    // We join users_table and job_seekers_table to get everything in one query
    $query = "
        SELECT 
            u.firebase_uid, 
            js.profile_pic_public_id, 
            js.cv_public_id 
        FROM users_table u
        LEFT JOIN job_seekers_table js ON u.user_id = js.user_id
        WHERE u.user_id = ?
    ";

    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();

    // --- STEP B: Delete files from Cloudinary (Best Effort) ---
    if ($userData) {
        $uploadApi = $cloudinary->uploadApi();

        // Delete Profile Photo
        if (!empty($userData['profile_pic_public_id'])) {
            try {
                $uploadApi->destroy($userData['profile_pic_public_id'], ['resource_type' => 'image']);
            } catch (Exception $e) {
                error_log("Failed to delete profile photo for user $user_id: " . $e->getMessage());
            }
        }

        // Delete CV
        if (!empty($userData['cv_public_id'])) {
            try {
                $uploadApi->destroy($userData['cv_public_id'], ['resource_type' => 'raw']);
            } catch (Exception $e) {
                error_log("Failed to delete CV for user $user_id: " . $e->getMessage());
            }
        }
    }

    // --- STEP C: Delete from Firebase Auth (Best Effort) ---
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

    // --- STEP D: Delete the user account from Local DB ---
    $delStmt = $dbconnection->prepare("DELETE FROM users_table WHERE user_id = ?");
    $delStmt->bind_param('i', $user_id);

    if (!$delStmt->execute()) {
        throw new Exception("Database execution failed");
    }

    if ($delStmt->affected_rows === 0) {
        throw new Exception("User not found or already deleted");
    }

    // Commit the changes
    $dbconnection->commit();

    // 4. Return Compatible JSON Response
    http_response_code(200);
    echo json_encode(['status' => true, 'message' => 'Account and associated data deleted successfully']);

} catch (Exception $e) {
    // Rollback changes if DB deletion failed
    $dbconnection->rollback();

    // Log the actual error internally
    error_log("Delete Account Error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to delete account. Please try again.']);
}
?>