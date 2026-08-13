<?php
session_start();
include 'db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(["status" => "error", "message" => "You must be logged in to reserve."]);
        exit();
    }

    // Check if session dates exist
    if (!isset($_SESSION['check_in']) || !isset($_SESSION['check_out'])) {
        echo json_encode(["status" => "error", "message" => "Check-in and check-out dates are missing."]);
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $check_in = $_SESSION['check_in'];
    $check_out = $_SESSION['check_out'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(["status" => "error", "message" => "Invalid email format."]);
        exit();
    }

    // Validate phone number (basic check for numbers only)
    if (!preg_match('/^[0-9+\s-]+$/', $phone)) {
        echo json_encode(["status" => "error", "message" => "Invalid phone number."]);
        exit();
    }

    // Insert reservation
    $sql = "INSERT INTO reservations (user_id, check_in, check_out, first_name, last_name, email, phone, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssss", $user_id, $check_in, $check_out, $first_name, $last_name, $email, $phone);

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Reservation successful."]);
    } else {
        echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    }

    $stmt->close();
    $conn->close();
}
?>
