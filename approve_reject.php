<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'config.php';
require_once 'generate_pdf.php';
require_once __DIR__ . '/vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $reservation_id = $_POST["reservation_id"];
    $action = $_POST["action"]; // 'Approved' or 'Rejected'

    // Validate
    if (!in_array($action, ['Approved', 'Rejected'])) {
        header("Location: admin.php?status=error&message=" . urlencode("Invalid action."));
        exit;
    }

    // Fetch reservation details
    $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $reservation = $result->fetch_assoc();

    if (!$reservation) {
        header("Location: admin.php?status=error&message=" . urlencode("Reservation not found."));
        exit;
    }

    // ADDED: Fetch room details for PDF generation
    $roomStmt = $conn->prepare("
        SELECT rr.*, r.name as room_name, r.capacity 
        FROM reservation_room rr
        JOIN rooms r ON rr.room_id = r.id
        WHERE rr.reservation_id = ?
    ");
    $roomStmt->bind_param("i", $reservation_id);
    $roomStmt->execute();
    $roomResult = $roomStmt->get_result();
    
    $rooms = [];
    $isPrivateReservation = false;
    $hasSecondHouse = false;
    
    while ($room = $roomResult->fetch_assoc()) {
        $rooms[] = [
            'room_id' => $room['room_id'],
            'room_name' => $room['room_name'],
            'quantity' => $room['quantity_booked'] ?? 1,
            'tour_type' => $room['tour_type'] ?? 'day_tour'
        ];
        
        // Check if private room (ID 28) is booked
        if ($room['room_id'] == 28) {
            $isPrivateReservation = true;
        }
    }
    $roomStmt->close();
    
    // Check if second house was added (based on extras_price or explicit flag)
    $extrasPrice = floatval($reservation['extras_price'] ?? 0);
    if ($extrasPrice >= 3000 || ($reservation['add_second_house'] ?? 0)) {
        $hasSecondHouse = true;
    }
    
    // Add room data to reservation array for PDF generation
    $reservation['rooms'] = $rooms;
    $reservation['is_private_reservation'] = $isPrivateReservation;
    $reservation['has_second_house'] = $hasSecondHouse;

    // Update status
    $updateStmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $updateStmt->bind_param("si", $action, $reservation_id);
    
    if ($updateStmt->execute()) {
        // If approved, generate PDF and send email
        if ($action == 'Approved') {
            // FIXED: Handle tour_type logic properly
            $tourType = 'day_tour'; // default
            
            // First check if there's a default_tour_type
            if (!empty($reservation['default_tour_type'])) {
                $tourType = $reservation['default_tour_type'];
            } 
            // Then check room-specific tour types
            elseif (!empty($rooms)) {
                $tourType = $rooms[0]['tour_type']; // Use first room's tour type
            }
            // Fallback to old column-based detection
            elseif ($reservation['whole_day_morning_tour'] || $reservation['whole_day_night_tour']) {
                $tourType = 'whole_day';
            } elseif ($reservation['night_tour_am']) {
                $tourType = 'overnight_am';
            } elseif ($reservation['night_tour_pm']) {
                $tourType = 'overnight_pm';
            } elseif ($reservation['day_tour']) {
                $tourType = 'day_tour';
            }
            
            $reservation['tour_type'] = $tourType;
            
            $checkin = $reservation['check_in'];
            $checkout = $reservation['check_out'];
            $totalAmount = $reservation['total_price'];
            $amountPaid = $reservation['amount_paid'];
            $remainingBalance = $totalAmount - $amountPaid;

            // Generate PDF
            $pdfPath = generatePDF($reservation);

            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.hostinger.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'guest_reservations@rainbowforestparadiseresortandcampsite.com';
                $mail->Password = '@RainbowForest2022';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                            
                $mail->setFrom('guest_reservations@rainbowforestparadiseresortandcampsite.com', 'Rainbow Forest Paradise Resort and Campsite');
                $mail->addAddress($reservation['email'], $reservation['first_name'] . ' ' . $reservation['last_name']);

                $mail->isHTML(true);
                $mail->Subject = 'Reservation Approved - Rainbow Forest Paradise Resort';
                
                $mail->Body = '
                <html>
                <head>
                 <style>
                  .email-container {
                    font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    background-color: #e6f2e6;
                    color: #2e4d2e;
                  }
                  .header {
                    text-align: center;
                    padding: 10px;
                    background-color: #b3ddb3;
                    border-radius: 10px 10px 0 0;
                  }
                  .logo {
                    max-width: 90px;
                    margin-bottom: 10px;
                    border-radius: 50px;
                  }
                  .content {
                    background-color: #ffffff;
                    padding: 25px;
                    border-radius: 0 0 10px 10px;
                    box-shadow: 0 4px 8px rgba(0, 64, 0, 0.1);
                  }
                  .footer {
                    margin-top: 30px;
                    font-size: 12px;
                    color: #557a55;
                    text-align: center;
                  }
                  h2 {
                    margin: 10px 0;
                    color: #2f5e2f;
                  }
                  ul {
                    padding-left: 20px;
                  }
                  ul li {
                    margin-bottom: 5px;
                  }
                </style>
                </head>
                <body>
                  <div class="email-container">
                    <div class="header">
                    <img src="https://rainbowforestparadiseresortandcampsite.com/images/rainbow-logo.png" alt="Rainbow Forest Paradise Resort Logo" class="logo">
                      <h2>Rainbow Forest Paradise Resort and Campsite</h2>
                    </div>
                
                    <div class="content">
                      <p>Dear <strong>' . htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) . '</strong>,</p>
                      <p>We are excited to inform you that your reservation has been <strong>approved</strong>!</p>
                      
                      <p><strong>Reservation Details:</strong></p>
                        <ul>
                          <li><strong>Reservation Code:</strong> ' . htmlspecialchars($reservation['reservation_code']) . '</li>
                          <li><strong>Check-in:</strong> ' . htmlspecialchars($checkin) . '</li>
                          <li><strong>Check-out:</strong> ' . htmlspecialchars($checkout) . '</li>
                        </ul>
                        
                        <p><strong>Payment Summary:</strong></p>
                        <ul>
                          <li><strong>Total Amount:</strong> ₱' . number_format($totalAmount, 2) . '</li>
                          <li><strong>Amount Paid:</strong> ₱' . number_format($amountPaid, 2) . '</li>
                          <li><strong>Remaining Balance:</strong> ₱' . number_format($remainingBalance, 2) . '</li>
                        </ul>
                        
                        <p>Please find the attached PDF file with the full details of your booking.</p>
                
                      <p>We look forward to welcoming you at Rainbow Forest Paradise Resort and Campsite. If you have any questions, feel free to reply to this email.</p>
                
                      <p>Warm regards,<br>
                      <strong>Rainbow Forest Paradise Resort and Campsite</strong></p>
                    </div>
                
                    <div class="footer">
                      📍 Brgy. Cuyambay, Tanay, Rizal<br>
                      📞 0912-345-6789 | 🌐 www.rainbowforestparadiseresortandcampsite.com
                    </div>
                  </div>
                </body>
                </html>
                ';
                $mail->addAttachment($pdfPath);
                if ($mail->send()) {
                    header("Location: admin.php?status=approved&email=sent&id=" . urlencode($reservation_id));
                    exit;
                } 
            } catch (Exception $e) {
                header("Location: admin.php?status=approved&email=failed&error=" . urlencode($mail->ErrorInfo) . "&id=" . urlencode($reservation_id));
                exit;
            }
        } else {
            // Rejection email logic remains the same...
            $requiredDownPayment = $reservation['base_price'] * 0.4;
            $amountPaid = $reservation['amount_paid'];
            $rejectionReason = "";
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.hostinger.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'guest_reservations@rainbowforestparadiseresortandcampsite.com';
                $mail->Password = '@RainbowForest2022';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;
                $mail->setFrom('guest_reservations@rainbowforestparadiseresortandcampsite.com', 'Rainbow Forest Paradise Resort and Campsite');
                $mail->addAddress($reservation['email'], $reservation['first_name'] . ' ' . $reservation['last_name']);
                $mail->Subject = 'Reservation Rejected';
                $mail->isHTML(true); 
                
                $mail->Body = '
                <html>
                <head>
                 <style>
                  .email-container {
                    font-family: Segoe UI, Tahoma, Geneva, Verdana, sans-serif;
                    padding: 20px;
                    background-color: #e6f2e6;
                    color: #5a2a2a;
                  }
                  .header {
                    text-align: center;
                    padding: 10px;
                    background-color: #e6f2e6;
                    border-radius: 10px 10px 0 0;
                  }
                  .logo {
                    max-width: 90px;
                    margin-bottom: 10px;
                    border-radius: 50px;
                  }
                  .content {
                    background-color: #ffffff;
                    padding: 25px;
                    border-radius: 0 0 10px 10px;
                    box-shadow: 0 4px 8px rgba(128, 0, 0, 0.1);
                  }
                  .footer {
                    margin-top: 30px;
                    font-size: 12px;
                    color: #7a4b4b;
                    text-align: center;
                  }
                  h2 {
                    margin: 10px 0;
                    color: #8b0000;
                  }
                  ul {
                    padding-left: 20px;
                  }
                 </style>
                </head>
                <body>
                  <div class="email-container">
                    <div class="header">
                      <img src="https://rainbowforestparadiseresortandcampsite.com/images/rainbow-logo.png" alt="Rainbow Forest Paradise Resort Logo" class="logo">
                      <h2>Rainbow Forest Paradise Resort and Campsite</h2>
                    </div>
                
                    <div class="content">
                      <p>Dear <strong>' . htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) . '</strong>,</p>
                      <p>We regret to inform you that your reservation has been <strong>rejected</strong>.</p>
                
                      <p><strong>Reservation Details:</strong></p>
                      <ul>
                        <li><strong>Reservation Code:</strong> ' . htmlspecialchars($reservation['reservation_code']) . '</li>
                        <li><strong>Status:</strong> Rejected</li>
                      </ul>
                
                      <p><strong>Reason:</strong><br>' . nl2br(htmlspecialchars($rejectionReason)) . '</p>';
                
                if ($amountPaid > 0) {
                    $mail->Body .= '
                      <p><strong>Payment Summary:</strong></p>
                      <ul>
                        <li><strong>Amount Paid:</strong> ₱' . number_format($amountPaid, 2) . '</li>
                        <li><strong>Required Down Payment (40%):</strong> ₱' . number_format($requiredDownPayment, 2) . '</li>
                      </ul>';
                }
                
                $mail->Body .= '
                      <p>If you believe this was made in error or would like to inquire further, feel free to reach out to us through our Facebook page or reply directly to this email.</p>
                
                      <p>We appreciate your interest and hope to serve you in the future.</p>
                
                      <p>Warm regards,<br>
                      <strong>Rainbow Forest Paradise Resort and Campsite</strong></p>
                    </div>
                
                    <div class="footer">
                      📍 Brgy. Cuyambay, Tanay, Rizal<br>
                      📞 0912-345-6789 | 🌐 www.rainbowforestparadiseresortandcampsite.com
                    </div>
                  </div>
                </body>
                </html>
                ';

                if ($mail->send()) {
                    header("Location: admin.php?status=rejected&email=sent&reason=" . urlencode($rejectionReason) . "&id=" . urlencode($reservation_id));
                    exit;
                }
            } catch (Exception $e) {
                header("Location: admin.php?status=rejected&email=failed&error=" . urlencode($mail->ErrorInfo) . "&id=" . urlencode($reservation_id));
                exit;
            }
        }
    } else {
        header("Location: admin.php?status=error&message=" . urlencode("Failed to update reservation status."));
        exit;
    }

    $stmt->close();
    $updateStmt->close();
    $conn->close();
} else {
    header("Location: admin.php?status=error&message=" . urlencode("Invalid request."));
    exit;
}
?>