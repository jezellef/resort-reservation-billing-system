<?php
session_start();
require_once 'db_connect.php';
require_once 'email_config.php';
require_once 'vendor/autoload.php'; // for PDF generation (e.g. dompdf)

// Get reservation code from POST
if (isset($_POST['reservation_code'])) {
    $code = $_POST['reservation_code'];

    // Update status to Confirmed
    $stmt = $conn->prepare("UPDATE guest_reservation SET status = 'Confirmed' WHERE reservation_code = ?");
    $stmt->bind_param("s", $code);
    if ($stmt->execute()) {
        // Fetch reservation details
        $stmt = $conn->prepare("SELECT * FROM guest_reservation WHERE reservation_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $result = $stmt->get_result();
        $reservation = $result->fetch_assoc();

        // Generate confirmation email body
        $body = "<h2>Reservation Confirmed</h2>
        <p>Thank you for your payment, {$reservation['name']}!</p>
        <p>Reservation Code: {$reservation['reservation_code']}</p>
        <p>Tour Type: {$reservation['tour_type']}<br>
        Check-in: {$reservation['checkin_date']}<br>
        Check-out: {$reservation['checkout_date']}<br>
        Adults: {$reservation['adults']}<br>
        Kids: {$reservation['kids']}<br>
        Total Paid: ₱{$reservation['total_amount']}</p>
        <p>You may print this confirmation or present the code upon arrival.</p>";

        // Send email
        send_email($reservation['email'], 'Reservation Confirmed', $body);

        // Generate PDF confirmation
        $dompdf = new Dompdf\Dompdf();
        $dompdf->loadHtml($body);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents("pdf/{$code}_confirmation.pdf", $dompdf->output());

        echo "Reservation confirmed and email sent.";
    } else {
        echo "Failed to confirm reservation.";
    }
} else {
    echo "No reservation code received.";
}
