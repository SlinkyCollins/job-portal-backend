<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Kreait\Firebase\Factory;

// 1. Admin Check
$user = validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$target_id = $input->user_id ?? null;

if (!$target_id) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'User ID required']);
    exit;
}

try {
    $dbconnection->begin_transaction();

    // 2. Fetch User Info (Role & Firebase IDs)
    $checkStmt = $dbconnection->prepare("SELECT role, google_id, facebook_id FROM users_table WHERE user_id = ?");
    $checkStmt->bind_param('i', $target_id);
    $checkStmt->execute();
    $targetUser = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$targetUser) {
        http_response_code(404);
        throw new Exception("User not found");
    }

    if ($targetUser['role'] === 'admin') {
        http_response_code(403);
        throw new Exception("Cannot delete other admins");
    }

    // 3. Fetch Cloudinary Public IDs (Directly from DB columns)
    $ids_to_delete = [];

    if ($targetUser['role'] === 'job_seeker') {
        $stmt = $dbconnection->prepare("SELECT profile_pic_public_id, cv_public_id FROM job_seekers_table WHERE user_id = ?");
        $stmt->bind_param('i', $target_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        if ($data) {
            if (!empty($data['profile_pic_public_id'])) {
                $ids_to_delete[] = ['id' => $data['profile_pic_public_id'], 'type' => 'image'];
            }
            if (!empty($data['cv_public_id'])) {
                $ids_to_delete[] = ['id' => $data['cv_public_id'], 'type' => 'raw'];
            }
        }
        $stmt->close();

    } elseif ($targetUser['role'] === 'employer') {
        // 1. Fetch Personal Profile Pic (from employers_table)
        $stmt = $dbconnection->prepare("SELECT profile_pic_public_id FROM employers_table WHERE user_id = ?");
        $stmt->bind_param('i', $target_id);
        $stmt->execute();
        $data = $stmt->get_result()->fetch_assoc();

        if ($data && !empty($data['profile_pic_public_id'])) {
            $ids_to_delete[] = ['id' => $data['profile_pic_public_id'], 'type' => 'image'];
        }
        $stmt->close();

        // 2. Fetch Company Logo (from companies table) - NEW ADDITION
        $stmtComp = $dbconnection->prepare("SELECT logo_public_id FROM companies WHERE user_id = ?");
        $stmtComp->bind_param('i', $target_id);
        $stmtComp->execute();
        $compData = $stmtComp->get_result()->fetch_assoc();

        if ($compData && !empty($compData['logo_public_id'])) {
            $ids_to_delete[] = ['id' => $compData['logo_public_id'], 'type' => 'image'];
        }
        $stmtComp->close();
    }

    // 4. Delete from Cloudinary
    $uploadApi = $cloudinary->uploadApi();
    foreach ($ids_to_delete as $item) {
        try {
            $uploadApi->destroy($item['id'], ['resource_type' => $item['type']]);
        } catch (Exception $e) {
            // Log error but continue deleting user
            error_log("Cloudinary Delete Error: " . $e->getMessage());
        }
    }

    // 5. Delete from Firebase Auth
    $firebase_uids = [];
    if (!empty($targetUser['google_id']))
        $firebase_uids[] = $targetUser['google_id'];
    if (!empty($targetUser['facebook_id']))
        $firebase_uids[] = $targetUser['facebook_id'];

    if (!empty($firebase_uids)) {
        try {
            // Load Credentials
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

                foreach ($firebase_uids as $uid) {
                    try {
                        $auth->deleteUser($uid);
                    } catch (Exception $e) {
                        // Ignore if user not found
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Firebase Admin Delete Error: " . $e->getMessage());
        }
    }

    // 6. Final Database Delete (Cascade handles the rest)
    $delStmt = $dbconnection->prepare("DELETE FROM users_table WHERE user_id = ?");
    $delStmt->bind_param('i', $target_id);
    $delStmt->execute();

    $dbconnection->commit();
    echo json_encode(['status' => true, 'message' => 'User and all associated data deleted successfully']);

} catch (Exception $e) {
    $dbconnection->rollback();
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}
?>