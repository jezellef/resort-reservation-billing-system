<?php
session_start();
include 'db_connect.php';
include 'email_config.php';

// Check if the user is logged in and email is set
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email'])) {
    die("Unauthorized access. Please log in.");
}

$user_id = $_SESSION['user_id'];
$guest_email = $_SESSION['email']; // Email is now properly checked

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reservation_id = $_POST['reservation_id'];

    // Update reservation status to 'Canceled'
    $sql = "UPDATE reservations SET status = 'Canceled' WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $reservation_id, $user_id);

    if ($stmt->execute()) {
        // Send cancellation email
        $subject = "Reservation Canceled";
        $body = "<p>Dear Guest,</p>
                 <p>Your reservation has been successfully canceled.</p>
                 <p>We hope to see you again soon!</p>";

        send_email($guest_email, $subject, $body);

        echo "Success! Reservation has been canceled.";
    } else {
        echo "Error: Unable to cancel reservation.";
    }

    $stmt->close();
}

$conn->close();

?>
