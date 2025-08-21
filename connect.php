<?php
$host= getenv("DB_HOST");
$username= getenv("DB_USER");
$password= getenv("DB_PASS");
$db= getenv("DB_NAME");


$dbconnection = new mysqli($host, $username, $password, $db);

if ($dbconnection->connect_error) {
    echo 'not connected'.$dbconnection->connect_error;
    // echo json_encode(['status' => false, 'msg' => 'Database connection failed']);
    // exit;
} else {
    // echo 'connection established';
    // echo json_encode(['status' => true, 'msg' => 'Database connection established']);
    // exit;
}