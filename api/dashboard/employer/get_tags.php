<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';

try {
    $query = "SELECT id, name FROM tags ORDER BY name ASC";
    $result = $dbconnection->query($query);
    
    $tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags[] = $row;
    }

    echo json_encode(["status" => true, "data" => $tags]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => false, "message" => $e->getMessage()]);
}
?>