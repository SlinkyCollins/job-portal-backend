<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';
require_once __DIR__ . '/../../../config/api_response.php';

validateJWT('admin');

$query = "SELECT c.*, COUNT(j.job_id) AS job_count 
          FROM categories c 
          LEFT JOIN jobs_table j ON c.id = j.category_id AND j.status = 'active' 
          GROUP BY c.id 
          ORDER BY c.name ASC";
$result = $dbconnection->query($query);

$categories = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
}

apiResponse(true, 'Categories retrieved successfully.', 200, ['data' => $categories]);
$dbconnection->close();
?>
