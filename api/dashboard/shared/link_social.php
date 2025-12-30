<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT();
$user_id = $user['user_id'];

$data = json_decode(file_get_contents('php://input'));
$providerId = $data->provider_id ?? '';
$socialUid = $data->social_uid ?? $user['firebase_uid'] ?? '';  // Use provided or user's existing firebase_uid

if (empty($providerId) || empty($socialUid)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid provider data']);
    exit;
}

// Map Provider ID to DB Column AND Short Name
$column = '';
$shortName = '';

if ($providerId === 'google.com') {
    $column = 'google_id';
    $shortName = 'google';
} elseif ($providerId === 'facebook.com') {
    $column = 'facebook_id';
    $shortName = 'facebook';
} else {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Unsupported provider']);
    exit;
}

// Security Check: Ensure this UID isn't linked to another user
$checkStmt = $dbconnection->prepare("SELECT user_id FROM users_table WHERE firebase_uid = ? AND user_id != ?");
$checkStmt->bind_param('si', $socialUid, $user_id);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['status' => false, 'message' => 'This account is already linked to another user.']);
    exit;
}
$checkStmt->close();

// Fetch current providers
$stmt = $dbconnection->prepare("SELECT linked_providers FROM users_table WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$current_providers = json_decode($row['linked_providers'] ?? '[]', true);
$stmt->close();

// Add Short Name if not exists
if (!in_array($shortName, $current_providers)) {
    $current_providers[] = $shortName;
}
$new_providers_json = json_encode($current_providers);

// Update User: Set provider ID and firebase_uid if not set
$updateStmt = $dbconnection->prepare("UPDATE users_table SET $column = ?, firebase_uid = COALESCE(firebase_uid, ?), linked_providers = ? WHERE user_id = ?");
$updateStmt->bind_param('sssi', $socialUid, $socialUid, $new_providers_json, $user_id);

if ($updateStmt->execute()) {
    echo json_encode([
        'status' => true, 
        'message' => 'Account linked successfully',
        'linked_providers' => $current_providers
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Database update failed']);
}
?>