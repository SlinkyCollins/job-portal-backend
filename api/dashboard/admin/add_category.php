<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$name = $input->name ?? '';

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Category name required']);
    exit;
}

// Check duplicate
$check = $dbconnection->prepare("SELECT id FROM categories WHERE name = ?");
$check->bind_param('s', $name);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['status' => false, 'message' => 'Category already exists']);
    exit;
}

$stmt = $dbconnection->prepare("INSERT INTO categories (name) VALUES (?)");
$stmt->bind_param('s', $name);

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'message' => 'Category added successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to add category']);
}
?>