<?php
$localhost='localhost';
$username='root';
$password='';
$db='jobportal_db';


$dbconnection = new mysqli($localhost, $username, $password, $db);

if ($dbconnection->connect_error) {
    echo 'not connected'.$dbconnection->connect_error;
    // echo json_encode(['status' => false, 'msg' => 'Database connection failed']);
    // exit;
} else {
    // echo 'connection established';
    // echo json_encode(['status' => true, 'msg' => 'Database connection established']);
    // exit;
}