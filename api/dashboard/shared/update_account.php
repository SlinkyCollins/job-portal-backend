<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

$user = validateJWT('job_seeker');
$user_id = $user['user_id'];

$accountData = json_decode(file_get_contents('php://input'));
$firstname = trim($accountData->firstName ?? '');
$lastname = trim($accountData->lastName ?? '');
$email = trim($accountData->email ?? '');
$phoneNumber = trim($accountData->phoneNumber ?? '');

$errors = [];

// --- 1. Fetch Current User Data (Security Check) ---
$stmt = $dbconnection->prepare("SELECT email, linked_providers FROM users_table WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$currentUser = $result->fetch_assoc();
$stmt->close();

if (!$currentUser) {
    http_response_code(404);
    echo json_encode(['status' => false, 'msg' => 'User not found.']);
    exit;
}

// Check if user is a social login user
$linkedProviders = json_decode($currentUser['linked_providers'] ?? '[]', true);
$isSocialUser = !empty($linkedProviders) && is_array($linkedProviders) && count($linkedProviders) > 0;

// 1. Validate First Name (Required, Min length 2)
if (empty($firstname)) {
    $errors[] = 'First name is required.';
} elseif (strlen($firstname) < 2) {
    $errors[] = 'First name must be at least 2 characters.';
}

// 2. Validate Last Name (Required, Min length 2)
if (empty($lastname)) {
    $errors[] = 'Last name is required.';
} elseif (strlen($lastname) < 2) {
    $errors[] = 'Last name must be at least 2 characters.';
}

// Validate Email Logic
if ($isSocialUser) {
    // If social user tries to change email, block it or silently revert to original
    if ($email !== $currentUser['email']) {
        $errors[] = 'Social login users cannot change their email address.';
    }
} else {
    // Regular user validation
    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format.';
    }
}

// 4. Validate Phone Number (Required)
if (empty($phoneNumber)) {
    $errors[] = 'Phone number is required.';
}

// Return errors if validation fails
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'errors' => $errors]);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbconnection->begin_transaction();

    try {
        // 1. Update users_table
        // If social user, we use the OLD email (ignore input). If regular, we use NEW email.
        $emailToSave = $isSocialUser ? $currentUser['email'] : $email;

        $updateUser = $dbconnection->prepare("UPDATE users_table SET firstname = ?, lastname = ?, email = ? WHERE user_id = ?");
        $updateUser->bind_param('sssi', $firstname, $lastname, $emailToSave, $user_id);
        
        if (!$updateUser->execute()) {
            throw new Exception("Failed to update user info: " . $updateUser->error);
        }
        $updateUser->close();

        // 2. Update job_seekers_table
        $updateSeeker = $dbconnection->prepare("UPDATE job_seekers_table SET phone = ? WHERE user_id = ?");
        $updateSeeker->bind_param('si', $phoneNumber, $user_id);
        
        if (!$updateSeeker->execute()) {
            throw new Exception("Failed to update phone number: " . $updateSeeker->error);
        }
        $updateSeeker->close();

        $dbconnection->commit();
        echo json_encode(['status' => true, 'msg' => 'Account settings updated successfully.']);

    } catch (Exception $e) {
        $dbconnection->rollback();
        http_response_code(500);
        echo json_encode(['status' => false, 'msg' => 'Account settings update failed.', 'error' => $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => false, 'msg' => 'Invalid request method.']);
}

$dbconnection->close();
?>