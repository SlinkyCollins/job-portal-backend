<?php
    require_once __DIR__ . '/../../../config/headers.php';
    require_once __DIR__ . '/../../../config/database.php';
    require_once __DIR__ . '/../../../config/middleware.php';
    require_once __DIR__ . '/../../../config/api_response.php';

validateJWT('admin');

$input = json_decode(file_get_contents('php://input'));
$id = $input->id ?? null;

if (!$id) {
    apiResponse(false, 'Category ID required', 400);
    exit;
}

// Your DDL says: ON DELETE SET NULL. So jobs will just lose their category, not be deleted. Safe!
$stmt = $dbconnection->prepare("DELETE FROM categories WHERE id = ?");
$stmt->bind_param('i', $id);

if ($stmt->execute()) {
    apiResponse(true, 'Category deleted successfully', 200);
} else {
    apiResponse(false, 'Failed to delete category', 500);
}
?>