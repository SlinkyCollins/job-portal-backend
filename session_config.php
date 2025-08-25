<?php
// Set session cookie params BEFORE session_start()
session_set_cookie_params([
    'lifetime' => 0,  // Session cookie (expires on browser close)
    'path' => '/',
    'domain' => '',  // Leave empty for current domain
    'secure' => true,  // Only over HTTPS
    'httponly' => true,  // JS can't access (security)
    'samesite' => 'None'  // Allows cross-site requests
]);

ini_set('session.gc_maxlifetime', 7200);  // 2 hours in seconds

session_name('JobNetSession');
session_start();  // Now start the session
error_log('Session ID: ' . session_id() . ', Cookie params: ' . print_r(session_get_cookie_params(), true));
?>