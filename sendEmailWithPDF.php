<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

function sendEmailWithPDF($reservation) {
    $mail = new PHPMailer(true);

    try {
        // Email configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'your_email@gmail.com'; // Your Gmail
        $mail->Password = 'your_app_password'; // App Password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Sender and recipient
        $mail->setFrom('your_email@gmail.com', 'Rainbow Forest Paradise');
        $mail->addAddress($reservation['email'], $reservation['name']); // Recipient

        // Subject and body content
        $mail->isHTML(true);
        $mail->Subject = 'Reservation Confirmed - ' . $reservation['code'];
        $mail->Body = "
            <h2>Reservation Confirmed</h2>
            <p>✓ Thank you for your reservation!</p>
            <p><strong>Reservation Code:</strong> {$reservation['code']}</p>
            <p><strong>Name:</strong> {$reservation['name']}</p>
            <p><strong>Email:</strong> {$reservation['email']}</p>
            <p><strong>Contact Number:</strong> {$reservation['contact']}</p>
            <p><strong>Tour Type:</strong> {$reservation['tour_type']}</p>
            <p><strong>Check-in:</strong> {$reservation['check_in']}</p>
            <p><strong>Check-out:</strong> {$reservation['check_out']}</p>
            <p><strong>Adults:</strong> {$reservation['adults']}</p>
            <p><strong>Kids:</strong> {$reservation['kids']}</p>
            <p><strong>Total Paid:</strong> ₱{$reservation['amount']}</p>
            <br>
            <p>Please find your full confirmation PDF attached. Present this upon arrival.</p>
            <p>Need help? Contact support@rainbowforestparadise.com</p>
        ";

        // Add PDF attachment (ensure $pdfPath is correct)
        $pdfPath = generatePDF($reservation); // Assuming you have generatePDF() function that returns the file path
        $mail->addAttachment($pdfPath);

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
