<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$name = $input->name ?? '';

if (empty($name)) {
    apiResponse(false, 'Category name required', 400);
    exit;
}

// Check duplicate
$check = $dbconnection->prepare("SELECT id FROM categories WHERE name = ?");
$check->bind_param('s', $name);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    apiResponse(false, 'Category already exists', 409);
    exit;
}

$stmt = $dbconnection->prepare("INSERT INTO categories (name) VALUES (?)");
$stmt->bind_param('s', $name);

if ($stmt->execute()) {
    apiResponse(true, 'Category added successfully', 201);
} else {
    apiResponse(false, 'Failed to add category', 500);
}
?>