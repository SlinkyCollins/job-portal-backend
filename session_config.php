<?php
session_name('JobNetSession');
// Set session cookie params BEFORE session_start()
session_set_cookie_params([
    'lifetime' => 0,  // Session cookie (expires on browser close)
    'path' => '/',
    'domain' => '',  // Leave empty for current domain
    'secure' => true,  // Only over HTTPS
    'httponly' => true,  // JS can't access (security)
    'samesite' => 'None'  // Allows cross-site requests
]);

ini_set('session.gc_maxlifetime', 14400);  // 4 hours in seconds
ini_set('session.cookie_lifetime', 0);  // Browser close

session_start();  // Now start the session
?>