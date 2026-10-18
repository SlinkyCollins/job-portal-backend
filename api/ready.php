<?php

require_once __DIR__ . '/../config/headers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/api_response.php';

$result = $dbconnection->query('SELECT 1');

if ($result === false) {
    error_log('JobNet readiness check failed: ' . $dbconnection->error);
    apiResponse(false, 'Database connection failed', 503);
    $dbconnection->close();
    exit;
}

$result->free();
apiResponse(true, 'Service ready', 200, [
    'database' => 'connected',
]);

$dbconnection->close();
