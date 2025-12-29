<?php
require_once __DIR__ . '/../../../config/headers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/middleware.php';

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

echo json_encode(['status' => true, 'data' => $categories]);
$dbconnection->close();
?>