<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
require_once 'db_connect.php';
require_once 'email_config.php'; 
require_once 'generate_pdf.php'; // Include PDF generation

if (isset($_POST['action']) && isset($_POST['reservation_code'])) {
    $action = $_POST['action'];
    $reservation_code = $_POST['reservation_code'];
    
    // Handle Approval and Rejection actions for all reservation types (private/public combined)
    if ($action == 'approve' || $action == 'reject') {
        // Update reservation status (approve/reject)
        $status = ($action == 'approve') ? 'Approved' : 'Rejected';
        $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE reservation_code = ?");
        $stmt->bind_param("ss", $status, $reservation_code);
        if ($stmt->execute()) {
            // Get reservation details after status update
            $stmt = $conn->prepare("SELECT * FROM reservations WHERE reservation_code = ?");
            $stmt->bind_param("s", $reservation_code);
            $stmt->execute();
            $result = $stmt->get_result();
            $reservation = $result->fetch_assoc();
            
            // Prepare reservation details for the email
            $reservation_details = [
                'code' => $reservation['reservation_code'],
                'name' => $reservation['first_name'] . ' ' . $reservation['last_name'],
                'email' => $reservation['email'],
                'contact' => $reservation['contact_number'],
                'check_in' => $reservation['check_in'],
                'check_out' => $reservation['check_out'],
                'adults' => $reservation['adult_count'],
                'kids' => $reservation['kid_count'],
                'amount' => number_format($reservation['total_price'], 2),
            ];

            // Generate PDF for the reservation (only for approved reservations)
            if ($action == 'approve') {
                $pdf_path = generatePDF($reservation_details);
                
                // Email content for approved reservation
                $subject = "Reservation Approved - Rainbow Forest Paradise Resort and Campsite";
                $body = "
                    Greetings {$reservation_details['name']},<br><br>
                    We are happy to let you know that your reservation code <strong>{$reservation_details['code']}</strong> has been approved!<br><br>
                    Attached is your reservation confirmation with full details. Kindly present it upon arrival at the resort.<br><br>
                    If you have any questions, feel free to contact us.<br><br>
                    Warm regards,<br>
                    <b>Rainbow Forest Paradise Resort and Campsite</b>
                ";
                
                // Send the email with PDF attached
                send_email($reservation_details['email'], $subject, $body, $pdf_path);
            } else {
                // Email content for rejected reservation
                $subject = "Reservation Update - Rainbow Forest Paradise Resort and Campsite";
                $body = "
                    Greetings {$reservation_details['name']},<br><br>
                    We regret to inform you that your reservation code <strong>{$reservation_details['code']}</strong> has been declined.<br><br>
                    Unfortunately, the reservation was not approved due to insufficient payment or other reasons.<br><br>
                    If you have any questions or would like further assistance, please feel free to contact us.<br><br>
                    Warm regards,<br>
                    <b>Rainbow Forest Paradise Resort and Campsite</b>
                ";
                
                // Send the email (without PDF for rejected)
                send_email($reservation_details['email'], $subject, $body);
            }
        }
    }
}
?>
