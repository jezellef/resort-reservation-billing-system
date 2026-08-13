<?php
$servername = "localhost";
$username = "u291458526_resort_user";
$password = "r@inboWforest123!";
$database = "u291458526_resort_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
