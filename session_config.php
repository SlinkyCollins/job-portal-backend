<?php
session_name('JobNetSession');
session_start([
    'cookie_path' => '/JobPortal',
    'cookie_secure' => true,
    'cookie_samesite' => 'None'
]);
?>