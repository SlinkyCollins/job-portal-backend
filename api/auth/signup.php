<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/Validator.php';

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

$validator->rule('first_name', 'required');
$validator->rule('last_name', 'required');
$validator->rule('email', 'required|email');
$validator->rule('password', 'required|min:6');
$validator->rule('role', 'required|in:job_seeker,employer');
$validator->rule('terms', 'accepted');

if (!$validator->validate()) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => $validator->firstError()]);
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
        $response = [
            'status' => false,
            'msg' => 'Query execution failed or no internet connection available: ' . $stmt->error
        ];
        echo json_encode($response);
        exit;
    }
    if ($result->num_rows > 0) {
        $existingUser = $result->fetch_assoc();
        $isSocialUser = !empty($existingUser['google_id']) || !empty($existingUser['facebook_id']);

        if ($isSocialUser) {
            http_response_code(403);
            echo json_encode([
                'status' => false,
                'msg' => 'This email is already registered via Google/Facebook. Please log in using your social account or contact support to add a password.'
            ]);
        } else {
            http_response_code(403);
            echo json_encode(['status' => false, 'msg' => 'Email already exists']);
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
        http_response_code(500);
        $response = [
            'status' => false,
            'msg' => 'Sign up failed due to an error, please try again'
        ];
        echo json_encode($response);
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

    http_response_code(201);
    echo json_encode(['status' => true, 'msg' => 'User signed up successfully']);

} catch (Exception $e) {
    $dbconnection->rollback();
    http_response_code(500);
    echo json_encode(['status' => false, 'msg' => 'Sign up failed: ' . $e->getMessage()]);
}

$dbconnection->close();
?>
