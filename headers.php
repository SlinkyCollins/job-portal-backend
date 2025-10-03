<?php
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");
header("Vary: Origin");
header("Content-Type: application/json");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://*.firebaseapp.com https://*.googleapis.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; connect-src 'self' https://job-portal-backend-rua3.onrender.com https://*.firebaseio.com https://*.firebaseapp.com https://*.googleapis.com; frame-src 'self' https://*.firebaseapp.com");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}