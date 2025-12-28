<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Kreait\Firebase\Factory;

// 1. Validate JWT
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

    // 2. Fetch Cloudinary/Firebase Info
    $table = ($role === 'employer') ? 'employers_table' : 'job_seekers_table';

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

    // 3. Delete External Files (Cloudinary)
    if ($userData) {
        $uploadApi = $cloudinary->uploadApi();

        if (!empty($userData['profile_pic_public_id'])) {
            try {
                $uploadApi->destroy($userData['profile_pic_public_id'], ['resource_type' => 'image']);
            } catch (Exception $e) {
            }
        }

        if ($role === 'job_seeker') {
            // Delete per-application CVs (excluding the default)
            $appCvQuery = "SELECT DISTINCT resume_public_id FROM applications_table WHERE seeker_id = ? AND resume_public_id IS NOT NULL";
            $appCvStmt = $dbconnection->prepare($appCvQuery);
            $appCvStmt->bind_param('i', $user_id);
            $appCvStmt->execute();
            $appCvResult = $appCvStmt->get_result();
            while ($row = $appCvResult->fetch_assoc()) {
                if ($row['resume_public_id'] !== $userData['cv_public_id']) {  // Avoid deleting default twice
                    try {
                        $uploadApi->destroy($row['resume_public_id'], ['resource_type' => 'raw']);
                    } catch (Exception $e) {
                    }
                }
            }
            $appCvStmt->close();

            // Delete default CV
            if (!empty($userData['cv_public_id'])) {
                try {
                    $uploadApi->destroy($userData['cv_public_id'], ['resource_type' => 'raw']);
                } catch (Exception $e) {
                }
            }
        }
    }

    // 4. Delete from Firebase Auth
    if ($userData && !empty($userData['firebase_uid'])) {
        try {
            if (!empty($_ENV['FIREBASE_CREDENTIALS'])) {
                $creds = json_decode($_ENV['FIREBASE_CREDENTIALS'], true);
            } else {
                $path = dirname(__DIR__, 2) . '/' . ($_ENV['FIREBASE_CREDENTIALS_PATH'] ?? 'config/jobnet-af0a7-firebase-adminsdk-fbsvc-71e1856708.json');
                if (file_exists($path))
                    $creds = $path;
            }

            if (isset($creds)) {
                $factory = (new Factory)->withServiceAccount($creds);
                $auth = $factory->createAuth();
                $auth->deleteUser($userData['firebase_uid']);
            }
        } catch (Exception $e) {
            error_log("Firebase Delete Error: " . $e->getMessage());
        }
    }

    // 5. Final User Delete (Database handles the rest!)
    // Because of ON DELETE CASCADE, this single line deletes:
    // - The User
    // - The Employer/Seeker profile
    // - The Company profile (if employer)
    // - All Jobs posted by them
    // - All Applications to those jobs
    // - All Saved Jobs entries
    $delStmt = $dbconnection->prepare("DELETE FROM users_table WHERE user_id = ?");
    $delStmt->bind_param('i', $user_id);
    $delStmt->execute();

    if ($delStmt->affected_rows === 0) {
        throw new Exception("User not found or already deleted");
    }

    $dbconnection->commit();
    echo json_encode(['status' => true, 'message' => 'Account deleted successfully']);

} catch (Exception $e) {
    $dbconnection->rollback();
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
}
?>