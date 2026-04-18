<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/api_response.php';

try {
    $query = "SELECT id, name FROM tags ORDER BY name ASC";
    $result = $dbconnection->query($query);
    
    $tags = [];
    while ($row = $result->fetch_assoc()) {
        $tags[] = $row;
    }

    apiResponse(true, 'Tags retrieved successfully.', 200, ['data' => $tags]);

} catch (Exception $e) {
    apiResponse(false, 'An error occurred while retrieving tags.', 500);
}
?>
