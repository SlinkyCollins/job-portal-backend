<?php
require_once __DIR__ . '/../../config/headers.php';
require_once __DIR__ . '/../../config/database.php';

$data = json_decode(file_get_contents("php://input"));
$firstname = $data->fname;
$lastname = $data->lname;
$email = strtolower($data->mail);
$userpassword = $data->pword;
$userRole = $data->role;
$terms = $data->terms;

// Basic validation
if (
    empty($firstname) || empty($lastname) || empty($email) ||
    empty($userpassword) || empty($userRole) || !$terms
) {
    echo json_encode(['status' => false, 'msg' => 'All fields are required and terms must be accepted.']);
    exit;
}

if (strlen($userpassword) < 6) {
    echo json_encode(['status' => false, 'msg' => 'Password must be at least 6 characters long']);
    exit;
}

try {
    // Check for existing email
    $queryone = "SELECT 1 FROM users_table WHERE email= ?";
    $stmt = $dbconnection->prepare($queryone);
    $stmt->bind_param('s', $email);
    $execute = $stmt->execute();
    if (!$execute) {
        $response = [
            'status' => false,
            'msg' => 'Query execution failed or no internet connection available: ' . $stmt->error
        ];
        echo json_encode($response);
        exit;
    }
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        http_response_code(403);
        echo json_encode(['status' => false, 'msg' => 'Email already exists']);
        $stmt->close();
        exit;
    }
    $stmt->close();

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