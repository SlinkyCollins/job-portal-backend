<?php
session_name('JobNetSession');
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/JobPortal',
    'domain' => 'job-portal-backend-rua3.onrender.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>