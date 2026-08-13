<?php
session_start();
require_once 'database.php'; // Include your database connection
require 'email_config.php'; // Include your email configuration
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php'; // Include PHPMailer autoloader

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_email') {
    $reservation_code = $_POST['reservation_code'];
    $email = $_POST['email'];

    // Check in guest_reservation first
    $stmt = $mysqli->prepare("SELECT * FROM guest_reservation WHERE reservation_code = ?");
    $stmt->bind_param("s", $reservation_code);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $reservation_details = $result->fetch_assoc();
    } else {
        // If not found in guest_reservation, check in user_reservation
        $stmt = $mysqli->prepare("SELECT ur.*, u.first_name, u.last_name FROM user_reservation ur JOIN user u ON ur.user_id = u.id WHERE ur.reservation_code = ?");
        $stmt->bind_param("s", $reservation_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $reservation_details = $result->fetch_assoc();
        } else {
            $_SESSION['error_message'] = "Reservation not found.";
            header("Location: admin.php");
            exit();
        }
    }

    // Prepare the email content
    $subject = "Reservation Confirmation";
    $message = "<p>Greetings <strong>" . ($reservation_details['first_name'] ?? 'Guest') . " " . ($reservation_details['last_name'] ?? '') . "</strong>,</p>";
    $message .= "<p>Thank you for your reservation!</p>";
    $message .= "<p><strong>Reservation Code:</strong> " . $reservation_code . "</p>";

    if (isset($reservation_details['check_in'])) {
        $message .= "<p><strong>Check-in Date:</strong> " . $reservation_details['check_in'] . "<br>";
        $message .= "<strong>Check-out Date:</strong> " . $reservation_details['check_out'] . "<br>";
        $message .= "<strong>Total Guests:</strong> " . ($reservation_details['adult_count'] + $reservation_details['kid_count']) . "<br>";

        $tourTypeMap = ['Whole Day', 'Day Tour', 'Night Tour'];
        $tourType = isset($reservation_details['tour_type']) ? $tourTypeMap[(int)$reservation_details['tour_type']] : 'N/A';
        $message .= "<strong>Tour Type:</strong> " . $tourType . "<br>";
        $message .= "<strong>Total Price:</strong> ₱" . number_format($reservation_details['total_price'], 2) . "</p>";
    } else {
        $message .= "<p>Your reservation details are being processed. Please check back for updates.</p>";
    }

    $message .= "<hr>";
    $message .= "<p><strong>Payment Instructions:</strong><br>";
    $message .= "Please pay within <strong>3 hours</strong> to confirm your reservation.<br>";
    $message .= "To make the payment, copy your reservation code and visit our homepage.<br>";
    $message .= "There, you can search for your reservation code and proceed to the payment page where you can upload your proof of payment.</p>";

    $message .= "<hr>";
    $message .= "<p><strong>Tour Schedule Policies:</strong><br>";
    $message .= "<strong>Day Tour:</strong> 9:00 AM - 6:00 PM<br>";
    $message .= "<strong>Night Tour:</strong> 8:00 PM - 7:00 AM<br>";
    $message .= "<strong>Whole Day / 22-Hour Package:</strong><br>";
    $message .= "- 9:00 AM - 7:00 AM (next day)<br>";
    $message .= "- 8:00 PM - 6:00 PM (next day)</p>";

    $message .= "<p>We look forward to welcoming you!<br><br>";
    $message .= "Best Regards,<br>Rainbow Forest Paradise Resort and Campsite</p>";

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'formenterajezelle@gmail.com';
        $mail->Password   = 'imusldrrsklwrobg';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('rainbow@gmail.com', 'Rainbow Forest Paradise Resort and Campsite');
        $mail->addAddress($email);
        $mail->isHTML(true); // HTML format enabled
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $message;

        if ($mail->send()) {
            $_SESSION['success_message'] = "Email sent successfully to $email.";
        } else {
            $_SESSION['error_message'] = "Failed to send email.";
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = "Mailer Error: " . $mail->ErrorInfo;
    }

    header("Location: admin.php");
    exit();
}
?>
