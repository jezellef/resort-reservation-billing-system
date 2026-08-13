<?php
session_start();
include 'db_connect.php';
include 'email_config.php';

if (!isset($_SESSION['admin_id'])) {
    die("Unauthorized access.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $payment_id = $_POST['payment_id'];
    $reservation_id = $_POST['reservation_id'];
    $guest_email = $_POST['email'];
    $action = $_POST['action'];

    if ($action == "approve") {
        $new_reservation_status = "Confirmed"; 
        $new_payment_status = "Paid"; 
        $subject = "Payment Approved!";
        $body = "<p>Dear Guest,</p>
                 <p>We have received your payment. Your reservation is now confirmed.</p>
                 <p>Thank you for choosing Rainbow Resort!</p>";

        // ✅ Update payments table
        $sql1 = "UPDATE payments SET status = 'Approved' WHERE id = ?";
        if ($stmt1 = $conn->prepare($sql1)) {
            $stmt1->bind_param("i", $payment_id);
            $payment_update = $stmt1->execute();
            $stmt1->close();
        } else {
            $payment_update = false;
        }

        // ✅ Update reservations table
        $sql2 = "UPDATE reservations SET status = ?, payment_status = ? WHERE id = ?";
        if ($stmt2 = $conn->prepare($sql2)) {
            $stmt2->bind_param("ssi", $new_reservation_status, $new_payment_status, $reservation_id);
            $reservation_update = $stmt2->execute();
            $stmt2->close();
        } else {
            $reservation_update = false;
        }

    } else { // Reject Payment
        $new_payment_status = "Rejected";
        $subject = "Payment Rejected";
        $body = "<p>Dear Guest,</p>
                 <p>Unfortunately, your payment was rejected. Please contact us for assistance.</p>";

        // ✅ Update payments table
        $sql3 = "UPDATE payments SET status = 'Rejected' WHERE id = ?";
        if ($stmt3 = $conn->prepare($sql3)) {
            $stmt3->bind_param("i", $payment_id);
            $payment_update = $stmt3->execute();
            $stmt3->close();
        } else {
            $payment_update = false;
        }

        $reservation_update = true; // No need to update reservations if rejecting payment
    }

    // ✅ Send email notification
    send_email($guest_email, $subject, $body);

    // ✅ Check if all updates were successful
    if (($action == "approve" && $payment_update && $reservation_update) || ($action == "reject" && $payment_update)) {
        $_SESSION['payment_message'] = "Payment successfully $action!";
        $_SESSION['payment_status'] = "success";
    } else {
        $_SESSION['payment_message'] = "An error occurred while processing the payment.";
        $_SESSION['payment_status'] = "error";
    }

    header("Location: verify_payment.php");
    exit();
}
?>
