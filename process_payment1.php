<?php
session_start();
include 'db_connect.php';
include 'email_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reservation_id = $_POST['reservation_id'];
    $action = $_POST['action'];
    $guest_email = $_POST['email'];

    if ($action == "approve") {
        $new_status = "Paid";
        $subject = "Payment Approved!";
        $body = "<p>Dear Guest,</p>
                 <p>We have received your payment. Your reservation is now confirmed.</p>
                 <p>Thank you for choosing Rainbow Resort!</p>";
    } else {
        $new_status = "Rejected";
        $subject = "Payment Rejected";
        $body = "<p>Dear Guest,</p>
                 <p>Unfortunately, your payment was rejected. Please contact us for assistance.</p>";
    }

    // Update reservation status in the database
    $sql = "UPDATE reservations SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $reservation_id);

    if ($stmt->execute()) {
        send_email($guest_email, $subject, $body);
        echo ucfirst($action) . " successful!";
    } else {
        echo "Error processing payment.";
    }

    $stmt->close();
}

$conn->close();
?>
