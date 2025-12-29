<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$id = $input->id ?? null;
$name = $input->name ?? '';

if (!$id || empty($name)) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Category ID and name required']);
    exit;
}

// Check duplicate (excluding current)
$check = $dbconnection->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
$check->bind_param('si', $name, $id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['status' => false, 'message' => 'Category name already exists']);
    exit;
}

$stmt = $dbconnection->prepare("UPDATE categories SET name = ? WHERE id = ?");
$stmt->bind_param('si', $name, $id);

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'message' => 'Category updated successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to update category']);
}
?>