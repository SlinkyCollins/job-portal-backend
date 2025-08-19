<?php
require_once 'headers.php';
require 'session_config.php';
require 'connect.php';


$data = json_decode(file_get_contents("php://input"));
$useremail = $data->mail;
$userpassword = $data->pword;

if (!$useremail || !$userpassword) {
    http_response_code(400);
    echo json_encode(['status' => false, 'msg' => 'Email and password are required']);
    exit;
}

$queryemail = "SELECT * FROM users_table WHERE email= ?";
$stmt = $dbconnection->prepare($queryemail);
$stmt->bind_param('s', $useremail);
$execute = $stmt->execute();

if ($execute) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userID = $user['user_id'];
        $userRole = $user['role'];
        $hashedpassword = $user['password'];
        $verifypassword = password_verify($userpassword, $hashedpassword);

        if ($verifypassword){
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $userID,
                'role' => $userRole,
            ];
            http_response_code(200);
            $response = [
                'status' => true,
                'msg' => 'Login successful',
                'verifypassword' => $verifypassword,
                'user' => [
                    'id' => $userID,
                    'role' => $userRole,
                ]
            ];
            echo json_encode($response);
        } else {
            http_response_code(401);
            $response = [
                'status' => false,
                'msg' => 'Incorrect password',
                'verifypassword' => $verifypassword,
            ];
            echo json_encode($response);
        }
    } else {
        http_response_code(404);
        $response = [
            'status' => false,
            'msg' => 'User not found, please try signing up',
        ];
        echo json_encode($response);
    }
} else {
    http_response_code(500); // Internal Server Error (Query or Connection Failed)
    $response = [
        'status' => false,
        'msg' => 'Login failed, please try again later',
    ];
    echo json_encode($response);
}

$dbconnection->close();

?>
