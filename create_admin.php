<?php
// Script to create admin user
$mysqli = require 'database.php';

$username = "admin";
$password = "admin123";
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Check if admin user already exists
$check = $mysqli->prepare("SELECT id FROM admins WHERE username = ?");
$check->bind_param("s", $username);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    echo "Admin user already exists. Updating password.<br>";
    $stmt = $mysqli->prepare("UPDATE admins SET password_hash = ? WHERE username = ?");
    $stmt->bind_param("ss", $hashed_password, $username);
} else {
    echo "Creating new admin user.<br>";
    $stmt = $mysqli->prepare("INSERT INTO admins (username, password_hash) VALUES (?, ?)");
    $stmt->bind_param("ss", $username, $hashed_password);
}

if ($stmt->execute()) {
    echo "Admin user created/updated successfully!";
} else {
    echo "Error: " . $stmt->error;
}
?>