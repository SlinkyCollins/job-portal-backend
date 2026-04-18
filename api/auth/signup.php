<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/Validator.php';
require_once __DIR__ . '/../../config/api_response.php';

$data = json_decode(file_get_contents("php://input"));
$firstname = trim($data->fname ?? '');
$lastname = trim($data->lname ?? '');
$email = strtolower(trim($data->mail ?? ''));
$userpassword = $data->pword ?? '';
$userRole = $data->role ?? '';
$terms = $data->terms ?? null;

$validator = new Validator([
    'first_name' => $firstname,
    'last_name' => $lastname,
    'email' => $email,
    'password' => $userpassword,
    'role' => $userRole,
    'terms' => $terms,
]);
$validator->labels([
    'first_name' => 'First name',
    'last_name' => 'Last name',
    'email' => 'Email',
    'password' => 'Password',
    'role' => 'Role',
    'terms' => 'Terms and conditions',
]);

$validator->rule('first_name', 'required');
$validator->rule('last_name', 'required');
$validator->rule('email', 'required|email');
$validator->rule('password', 'required|min:' . Validator::PASSWORD_MIN_LENGTH);
$validator->rule('role', 'required|in:job_seeker,employer');
$validator->rule('terms', 'accepted');

if (!$validator->validate()) {
    apiResponse(false, 'Validation failed.', 400, [], $validator->errors());
    exit;
}

try {
    // Check for existing email
    $queryone = "SELECT user_id, google_id, facebook_id FROM users_table WHERE email = ?";
    $stmt = $dbconnection->prepare($queryone);
    $stmt->bind_param('s', $email);
    $execute = $stmt->execute();
    $result = $stmt->get_result();
    if (!$execute) {
        apiResponse(false, 'Unable to validate sign up details.', 500);
        exit;
    }
    if ($result->num_rows > 0) {
        $existingUser = $result->fetch_assoc();
        $isSocialUser = !empty($existingUser['google_id']) || !empty($existingUser['facebook_id']);

        if ($isSocialUser) {
            apiResponse(false, 'This email is already registered via Google/Facebook. Please log in using your social account or contact support to add a password.', 409);
        } else {
            apiResponse(false, 'Email already exists.', 409);
        }
        $stmt->close();
        exit;
    }

    // Start transaction
    $dbconnection->begin_transaction();

    $hash = password_hash($userpassword, PASSWORD_DEFAULT);

    $query = "INSERT INTO users_table (firstname, lastname, email, password, role, terms_accepted) VALUES (?, ?, ?, ?, ?, ?)";
    $prepare = $dbconnection->prepare($query);
    $prepare->bind_param('sssssi', $firstname, $lastname, $email, $hash, $userRole, $terms);
    $execute = $prepare->execute();
    if (!$execute) {
        throw new Exception('User insert failed: ' . $prepare->error);
    }
    $newUserId = $dbconnection->insert_id;
    $prepare->close();

    // Auto-create role-based record
    if ($userRole === 'job_seeker') {
        $insertJobSeeker = $dbconnection->prepare("INSERT INTO job_seekers_table (user_id) VALUES (?)");
        $insertJobSeeker->bind_param('i', $newUserId);
        if (!$insertJobSeeker->execute()) {
            throw new Exception('Job seeker insert failed: ' . $insertJobSeeker->error);
        }
        $insertJobSeeker->close();
    } elseif ($userRole === 'employer') {
        $insertEmployer = $dbconnection->prepare("INSERT INTO employers_table (user_id) VALUES (?)");
        $insertEmployer->bind_param('i', $newUserId);
        if (!$insertEmployer->execute()) {
            throw new Exception('Employer insert failed: ' . $insertEmployer->error);
        }
        $insertEmployer->close();
    }

    // Commit transaction
    $dbconnection->commit();

    apiResponse(true, 'User signed up successfully.', 201);

} catch (Exception $e) {
    $dbconnection->rollback();
    error_log('Sign up failed: ' . $e->getMessage());
    apiResponse(false, 'Sign up failed. Please try again later.', 500);
}

$dbconnection->close();
?>
