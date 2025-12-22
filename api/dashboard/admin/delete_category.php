<?php
    require_once __DIR__ . '/../../../config/headers.php';
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../config/middleware.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$id = $input->id ?? null;

if (!$id) {
    http_response_code(400);
    echo json_encode(['status' => false, 'message' => 'Category ID required']);
    exit;
}

// Your DDL says: ON DELETE SET NULL. So jobs will just lose their category, not be deleted. Safe!
$stmt = $dbconnection->prepare("DELETE FROM categories WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    echo json_encode(['status' => true, 'message' => 'Category deleted successfully']);
} else {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Failed to delete category']);
}
?>