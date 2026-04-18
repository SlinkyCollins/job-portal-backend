<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$id = $input->id ?? null;
$name = $input->name ?? '';

if (!$id || empty($name)) {
    apiResponse(false, 'Category ID and name required', 400);
    exit;
}

// Check duplicate (excluding current)
$check = $dbconnection->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
$check->bind_param('si', $name, $id);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    apiResponse(false, 'Category name already exists', 409);
    exit;
}

$stmt = $dbconnection->prepare("UPDATE categories SET name = ? WHERE id = ?");
$stmt->bind_param('si', $name, $id);

if ($stmt->execute()) {
    apiResponse(true, 'Category updated successfully', 200);
} else {
    apiResponse(false, 'Failed to update category', 500);
}
?>