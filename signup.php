<?php
require 'connect.php';
header('Access-Control-Allow-Origin: http://localhost:4200');  
header('Access-Control-Allow-Headers:Content-Type');
header('Content-Type:application/json');

$data = json_decode(file_get_contents("php://input"));
$firstname = $data->fname;
$lastname = $data->lname;
$email = strtolower($data->mail);
$userpassword = $data->pword;
$userRole = $data->role;

if (strlen($userpassword) < 6) {
    $response = [
        'status' => false,
        'msg' => 'Password must be at least 6 characters long'
    ];
    echo json_encode($response);
    exit;
}

$queryone = "SELECT * FROM users_table WHERE email= ?";
$stmt = $dbconnection->prepare($queryone);
$stmt->bind_param('s', $email);
$execute = $stmt->execute();

if ($execute) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        http_response_code(403);
        $response = [
            'status' => true,
            'msg' => 'Email already exists',
        ];
        echo json_encode($response);
        exit;
    } else {
        $hash = password_hash($userpassword, PASSWORD_DEFAULT);

        $query = "INSERT INTO users_table (firstname, lastname, email, password, role) VALUES (?, ?, ?, ?, ?)";
        $prepare = $dbconnection->prepare($query);
        $prepare->bind_param('sssss', $firstname, $lastname, $email, $hash, $userRole);
        $execute = $prepare->execute();

        if ($execute) {
            http_response_code(201);
            $response = [
                'status' => true,
                'msg' => 'User signed up successfully',
            ];
            echo json_encode($response);
        } else {
            http_response_code(500);
            $response = [
                'status' => false,
                'msg' => 'Sign up failed due to an error, please try again'
            ];
            echo json_encode($response);
        }
    }
} else {
    $response = [
        'status' => false,
        'msg' => 'Query execution failed or no internet connection available: ' . $dbconnection->error
    ];
    echo json_encode($response);
    exit;
}

$dbconnection->close();

?>