<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/middleware.php';

$user = validateJWT();
$user_id = $user['user_id'];

$data = json_decode(file_get_contents('php://input'));
$providerId = $data->provider_id ?? ''; // e.g., 'google.com'
$socialUid = $data->social_uid ?? '';   

if (empty($providerId) || empty($socialUid)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Invalid provider data']);
    exit;
}

// 1. Map Provider ID to DB Column AND Short Name
$column = '';
$shortName = ''; // New variable for consistency

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

// 2. Security Check
$checkStmt = $dbconnection->prepare("SELECT user_id FROM users_table WHERE $column = ? AND user_id != ?");
$checkStmt->bind_param('si', $socialUid, $user_id);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['status' => false, 'message' => 'This account is already linked to another user.']);
    exit;
}
$checkStmt->close();

// 3. Fetch current providers
$stmt = $dbconnection->prepare("SELECT linked_providers FROM users_table WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$current_providers = json_decode($row['linked_providers'] ?? '[]', true);
$stmt->close();

// 4. Add Short Name if not exists (e.g., add 'google', not 'google.com')
if (!in_array($shortName, $current_providers)) {
    $current_providers[] = $shortName;
}
// Also check for the long name just in case legacy data exists, to avoid duplicates
if (!in_array($providerId, $current_providers) && $providerId !== $shortName) {
    // Optional: You can choose to NOT add the long name if you want strict consistency
    // $current_providers[] = $providerId; 
}

$new_providers_json = json_encode($current_providers);

// 5. Update User
$updateStmt = $dbconnection->prepare("UPDATE users_table SET $column = ?, linked_providers = ? WHERE user_id = ?");
$updateStmt->bind_param('ssi', $socialUid, $new_providers_json, $user_id);

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