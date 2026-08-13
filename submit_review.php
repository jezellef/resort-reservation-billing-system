<?php
$servername = "localhost";
$username = "root";
$password = "";
$database = "resort_db";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$name = $_POST['name'];
$visit_date = $_POST['visit_date'];
$rating = $_POST['rating'];
$message = $_POST['message'];

$sql = "INSERT INTO reviews (name, visit_date, rating, message) 
        VALUES ('$name', '$visit_date', '$rating', '$message')";

if ($conn->query($sql) === TRUE) {
    echo "<script>alert('Review submitted successfully!'); window.location.href='contact_p1.php';</script>";
}

$conn->close();
?>
