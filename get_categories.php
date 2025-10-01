<?php
require_once 'headers.php';
require 'connect.php';

$query = "SELECT id, name FROM categories ORDER BY name ASC";
$result = $dbconnection->query($query);

if ($result && $result->num_rows > 0) {
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    $response = [
        'status' => true,
        'categories' => $categories
    ];
} else {
    $response = [
        'status' => false,
        'msg' => 'No categories found'
    ];
}

echo json_encode($response);
$dbconnection->close();