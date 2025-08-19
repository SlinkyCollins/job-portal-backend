<?php
$allowedOrigins = ['http://localhost:4200', 'https://jobnet.vercel.app', 'https://690784b1d1a5.ngrok-free.app'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    header("Access-Control-Allow-Origin: https://jobnet.vercel.app"); // Default to prod
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); 
header('Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS');
header('Content-Type: application/json');
header("Content-Security-Policy: default-src 'self' https://*.firebaseapp.com https://*.googleapis.com; script-src 'self' 'unsafe-inline';");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}