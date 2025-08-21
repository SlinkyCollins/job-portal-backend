<?php
// index.php

// Include your connection file
require_once __DIR__ . 'connect.php';

// Just to test
if ($dbconnection->connect_error) {
    echo "Database not connected: " . $dbconnection->connect_error;
} else {
    echo "Hello from Render! Database connection successful 🚀";
}
