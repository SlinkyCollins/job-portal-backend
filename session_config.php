<?php
session_name('JobNetSession');
session_set_cookie_params([
    'lifetime' => 1800,
    'path' => '/',
    'domain' => 'jobnet.vercel.app',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'None'
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    error_log('Session started, ID: ' . session_id());
}
?>