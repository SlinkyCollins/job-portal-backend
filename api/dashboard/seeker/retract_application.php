<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/cloudinary.php'; 

// Validate JWT and require job_seeker role
$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$input = json_decode(file_get_contents('php://input'));
$applicationId = $input->applicationId ?? null;

if (!$applicationId) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Application ID is required.']);
    exit;
}

try {
    $dbconnection->begin_transaction();

    // Fetch the application's resume_public_id and compare to default
    $fetchQuery = "SELECT a.resume_public_id, js.cv_public_id AS default_cv_public_id 
                   FROM applications_table a 
                   LEFT JOIN job_seekers_table js ON js.user_id = a.seeker_id 
                   WHERE a.application_id = ? AND a.seeker_id = ?";
    $fetchStmt = $dbconnection->prepare($fetchQuery);
    $fetchStmt->bind_param("ii", $applicationId, $user_id);
    $fetchStmt->execute();
    $appData = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    if ($appData && !empty($appData['resume_public_id']) && $appData['resume_public_id'] !== $appData['default_cv_public_id']) {
        // Delete custom CV from Cloudinary (not the default)
        try {
            $cloudinary->uploadApi()->destroy($appData['resume_public_id'], ['resource_type' => 'raw']);
        } catch (Exception $e) {
            // Log error but continue
            error_log("Failed to delete CV for application $applicationId: " . $e->getMessage());
        }
    }

    // Update application status to 'retracted' and clear resume fields
    $query = "UPDATE applications_table SET status = 'retracted', retracted_at = NOW(), cover_letter = NULL, resume_url = NULL, resume_filename = NULL, resume_public_id = NULL WHERE application_id = ? AND seeker_id = ?";
    $stmt = $dbconnection->prepare($query);
    $stmt->bind_param("ii", $applicationId, $user_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $dbconnection->commit();
            echo json_encode(['status' => true, 'msg' => 'Application retracted successfully']);
        } else {
            $dbconnection->rollback();
            echo json_encode(['status' => false, 'msg' => 'Application not found or already retracted']);
        }
    } else {
        $dbconnection->rollback();
        echo json_encode(['status' => false, 'msg' => 'Failed to retract: ' . $stmt->error]);
    }

    $stmt->close();

} catch (Exception $e) {
    $dbconnection->rollback();
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Retraction failed: ' . $e->getMessage()]);
}

$dbconnection->close();
?>