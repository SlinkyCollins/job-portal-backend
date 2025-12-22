<?php
// 1. SECURITY CHECK: Ensure this is running from the Command Line Interface (CLI)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die("Access Denied: This script can only be run from the command line.");
}

require_once __DIR__ . '/../../config/database.php';

// 2. Get arguments from command line
// Usage: php create_admin_cli.php email password firstname lastname
if ($argc < 5) {
    die("Usage: php create_admin_cli.php <email> <password> <firstname> <lastname>\n");
}

$email = $argv[1];
$password = $argv[2];
$firstname = $argv[3];
$lastname = $argv[4];

// 3. Hash and Insert
$hashed_password = password_hash($password, PASSWORD_DEFAULT);
$role = 'admin';
$terms = 1;

// Check if email exists
$check = $dbconnection->prepare("SELECT user_id FROM users_table WHERE email = ?");
$check->bind_param('s', $email);
$check->execute();
if ($check->get_result()->num_rows > 0) {
    die("Error: User with this email already exists.\n");
}

$query = "INSERT INTO users_table (firstname, lastname, email, password, role, terms_accepted) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $dbconnection->prepare($query);
$stmt->bind_param('sssssi', $firstname, $lastname, $email, $hashed_password, $role, $terms);

if ($stmt->execute()) {
    echo "✅ Admin user [$email] created successfully!\n";
} else {
    echo "❌ Error: " . $dbconnection->error . "\n";
}
?>