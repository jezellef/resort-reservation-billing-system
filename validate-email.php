<?php

$mysqli = require __DIR__ . "/database.php";

// Get the email from the query string
$email = $_GET['email'] ?? '';

// Prepare the SQL statement to prevent SQL injection
$stmt = $mysqli->prepare("SELECT * FROM user WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

// Check if the email is available
$is_available = $result->num_rows == 0;

// Set the content type to JSON
header("Content-Type: application/json");

// Correctly format the JSON response
echo json_encode(["available" => $is_available]);

?>