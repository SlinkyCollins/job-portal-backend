<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/Validator.php';
require_once __DIR__ . '/../../../config/api_response.php';

$user = validateJWT(); // Works for ANY role
$user_id = $user['user_id'];

$data = json_decode(file_get_contents('php://input'));
$firstname = trim($data->firstName ?? '');
$lastname = trim($data->lastName ?? '');

$validator = new Validator([
    'firstName' => $firstname,
    'lastName' => $lastname,
]);

$validator->rule('firstName', 'required|min:2');
$validator->rule('lastName', 'required|min:2');

if (!$validator->validate()) {
    apiResponse(false, 'Invalid input data.', 400, [], $validator->errors());
    exit;
}

try {
    $updateUser = $dbconnection->prepare("UPDATE users_table SET firstname = ?, lastname = ? WHERE user_id = ?");
    $updateUser->bind_param('ssi', $firstname, $lastname, $user_id);
    
    if ($updateUser->execute()) {
        apiResponse(true, 'Account updated successfully.', 200, [
            'firstName' => $firstname,
            'lastName' => $lastname
        ]);
    } else {
        throw new Exception("Update failed: " . $updateUser->error);
    }
} catch (Exception $e) {
    apiResponse(false, 'Error updating account.', 500, [], ['error' => $e->getMessage()]);
    exit;
} finally {
    $updateUser->close();
    $dbconnection->close();
}
?>
