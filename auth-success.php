<?php
require_once 'headers.php';
require 'session_config.php';

if (!isset($_SESSION['user']['role'])) {
    header('Location: https://jobnet.vercel.app/login');
    exit;
}

$role = $_SESSION['user']['role'];
$dashboard = $role === 'job_seeker' ? 'jobseeker' : ($role === 'employer' ? 'employer' : 'admin');
header("Location: https://jobnet.vercel.app/dashboard/$dashboard");
exit;