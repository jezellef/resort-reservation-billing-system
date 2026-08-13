<?php
ob_start(); // Start output buffering
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

function simple_log($message) {
    $log = "[" . date("Y-m-d H:i:s") . "] " . $message . "\n";
    file_put_contents("booking_debug.log", $log, FILE_APPEND);
    error_log($message);
}

simple_log("=== BOOKING PROCESS STARTED ===");
simple_log("REQUEST_METHOD: " . $_SERVER["REQUEST_METHOD"]);
simple_log("X-Requested-With: " . ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? 'not set'));
simple_log("POST action: " . ($_POST['action'] ?? 'not set'));

$is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
$is_save_for_later = ($is_ajax && isset($_POST['action']) && $_POST['action'] === 'save_for_later');

simple_log("is_ajax: " . ($is_ajax ? 'true' : 'false'));
simple_log("is_save_for_later: " . ($is_save_for_later ? 'true' : 'false'));

function checkRoomAvailabilityForBooking($mysqli, $room_id, $check_in, $check_out, $quantity_needed = 1) {
    $roomQuery = "SELECT real_quantity, name, status FROM rooms WHERE id = ?";
    $stmt = $mysqli->prepare($roomQuery);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $roomResult = $stmt->get_result();
    
    if ($roomResult->num_rows === 0) {
        throw new Exception("Room not found: ID $room_id");
    }
    
    $room = $roomResult->fetch_assoc();
    $stmt->close();
    
    if ($room['status'] !== 'Available') {
        throw new Exception("Room '{$room['name']}' is currently unavailable");
    }
    
    if ($check_in === $check_out) {
        $bookedQuery = "
            SELECT COALESCE(SUM(rr.quantity_booked), 0) as total_booked
            FROM reservation_room rr
            JOIN reservations r ON rr.reservation_id = r.id
            WHERE rr.room_id = ? 
            AND r.status IN ('Approved', 'Checked In', 'Pending')
            AND (
                (r.check_in = ? AND r.check_out = ?) OR
                (r.check_in <= ? AND r.check_out > ?)
            )
        ";
        $stmt = $mysqli->prepare($bookedQuery);
        $stmt->bind_param("issss", $room_id, $check_in, $check_out, $check_in, $check_in);
    } else {
        $bookedQuery = "
            SELECT COALESCE(SUM(rr.quantity_booked), 0) as total_booked
            FROM reservation_room rr
            JOIN reservations r ON rr.reservation_id = r.id
            WHERE rr.room_id = ? 
            AND r.status IN ('Approved', 'Checked In', 'Pending')
            AND NOT (r.check_out <= ? OR r.check_in >= ?)
        ";
        $stmt = $mysqli->prepare($bookedQuery);
        $stmt->bind_param("iss", $room_id, $check_in, $check_out);
    }
    
    $stmt->execute();
    $bookedResult = $stmt->get_result();
    $bookedData = $bookedResult->fetch_assoc();
    $total_booked = $bookedData['total_booked'];
    $stmt->close();
    
    $available_quantity = $room['real_quantity'] - $total_booked;
    
    if ($available_quantity < $quantity_needed) {
        throw new Exception("Insufficient availability for room '{$room['name']}'. Requested: $quantity_needed, Available: $available_quantity");
    }
    
    return true;
}

function return_error($message) {
    global $is_save_for_later;
    simple_log("ERROR: " . $message);
    
    if ($is_save_for_later) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $message]);
        exit();
    } else {
        $_SESSION['booking_error'] = $message;
        header("Location: booking_form.php");
        exit();
    }
}

function return_success($reservation_code, $reservation_id = null, $reservation_type = 'public') {
    global $is_save_for_later;
    simple_log("SUCCESS: Reservation created - " . $reservation_code);
    
    if ($is_save_for_later) {
        // Clean any output that might have been generated
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: application/json');
        http_response_code(200);
        
        $response = json_encode([
            'success' => true,
            'message' => 'Reservation saved successfully',
            'reservation_code' => $reservation_code,
            'reservation_type' => $reservation_type
        ]);
        
        echo $response;
        exit();
    } else {
        $_SESSION['reservation_code'] = $reservation_code;
        header("Location: confirmation.php?reference=" . urlencode($reservation_code));
        exit();
    }
}

function sendReservationEmail($email, $first_name, $last_name, $reservation_code, $check_in, $check_out, $total_amount, $amount_paid = 0, $is_saved = false, $reservation_details = []) {
    simple_log("Sending email to: " . $email);
    
    $subject = $is_saved ? "Reservation Saved - " . $reservation_code : "Reservation Confirmation - " . $reservation_code;
    $guest_name = htmlspecialchars($first_name . ' ' . $last_name);
    $balance = $total_amount - $amount_paid;
    
    $adults = $reservation_details['adults'] ?? 0;
    $children = $reservation_details['children'] ?? 0;
    $pwd_senior = $reservation_details['pwd_senior'] ?? 0;
    $total_guests = $adults + $children + $pwd_senior;
    $selected_rooms = $reservation_details['rooms'] ?? [];
    $tour_type = $reservation_details['tour_type'] ?? 'Day Tour';
    $is_day_tour = ($check_in === $check_out);
    $nights = $is_day_tour ? 1 : max(1, (strtotime($check_out) - strtotime($check_in)) / (24 * 60 * 60));
    
    $hasPrivateRoom = false;
    foreach ($selected_rooms as $room) {
        if (isset($room['room_id']) && $room['room_id'] == 28) {
            $hasPrivateRoom = true;
            break;
        }
    }
    
    $displayIsDayTour = $is_day_tour;
    if (!$displayIsDayTour && !empty($selected_rooms)) {
        foreach ($selected_rooms as $room) {
            if (isset($room['tour_type']) && $room['tour_type'] === 'day_tour') {
                $displayIsDayTour = true;
                break;
            }
        }
    }
    
    $entranceFees = 0;
    if (!$hasPrivateRoom) {
        // Regular booking: multiply by nights
        if ($displayIsDayTour) {
            $entranceFees += ($adults * 200) * $nights;
            $entranceFees += ($children * 150) * $nights;
            $entranceFees += ($pwd_senior * 160) * $nights;
        } else {
            $entranceFees += ($adults * 250) * $nights;
            $entranceFees += ($children * 200) * $nights;
            $entranceFees += ($pwd_senior * 200) * $nights;
        }
    } else {
        // Private room with excess guests
        if ($totalGuests > 30) {
            $excessGuests = $totalGuests - 30;
            $privateTourType = '';
            
            foreach ($selected_rooms as $room) {
                if (isset($room['room_id']) && $room['room_id'] == 28) {
                    $privateTourType = $room['tour_type'] ?? 'day_tour';
                    break;
                }
            }
            
            $excessFeePerGuest = 0;
            if ($privateTourType === 'day_tour') {
                $excessFeePerGuest = 400;
            } elseif ($privateTourType === 'overnight_pm') {
                $excessFeePerGuest = 500;
            } elseif ($privateTourType === 'whole_day' || $privateTourType === 'overnight_am') {
                $excessFeePerGuest = 600;
            }
            
            // For day tours, don't multiply by nights; for overnight, multiply by nights
            if ($privateTourType === 'day_tour') {
                $entranceFees = $excessGuests * $excessFeePerGuest;
            } else {
                $entranceFees = ($excessGuests * $excessFeePerGuest) * $nights;
            }
        }
    }
        
    $room_details_html = '';
    if (!empty($selected_rooms)) {
        $room_details_html = '<h3 style="color: #2f5e2f; margin-top: 20px;">Selected Accommodations:</h3><ul style="padding-left: 20px;">';
        foreach ($selected_rooms as $room) {
            $room_tour_type = '';
            switch($room['tour_type'] ?? 'day_tour') {
                case 'day_tour':
                    $room_tour_type = 'Day Tour (8AM-5PM)';
                    break;
                case 'overnight_am':
                    $room_tour_type = 'Overnight AM (9AM-7AM)';
                    break;
                case 'overnight_pm':
                    $room_tour_type = 'Overnight PM (6PM-4PM)';
                    break;
                case 'whole_day':
                    $room_tour_type = 'Whole Day (8AM-6PM next day)';
                    break;
                default:
                    $room_tour_type = ucfirst($room['tour_type']);
            }
            
            $room_details_html .= '<li style="margin-bottom: 10px;">';
            $room_details_html .= '<strong>' . htmlspecialchars($room['name']) . '</strong><br>';
            $room_details_html .= 'Quantity: ' . htmlspecialchars($room['quantity']) . ' | ';
            $room_details_html .= 'Capacity: ' . htmlspecialchars($room['capacity']) . ' guests per room<br>';
            $room_details_html .= 'Tour Type: ' . $room_tour_type;
            $room_details_html .= '</li>';
        }
        $room_details_html .= '</ul>';
    }
    
    $vat_amount = round($total_amount * (12/112), 2);
    $subtotal_before_vat = $total_amount - $vat_amount;
    
    if ($is_saved) {
        $message = '
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
                h3 {
                    color: #2f5e2f;
                    margin-top: 20px;
                }
                ul {
                    padding-left: 20px;
                }
                ul li {
                    margin-bottom: 5px;
                }
                .button {
                    display: inline-block;
                    padding: 12px 24px;
                    background-color: #28a745;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    margin: 10px 0;
                }
                .important-info {
                    background-color: #fff3cd;
                    padding: 15px;
                    border-left: 4px solid #ffc107;
                    margin: 15px 0;
                    border-radius: 0 5px 5px 0;
                }
                .reservation-details {
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 15px 0;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <img src="https://rainbowforestparadiseresortandcampsite.com/images/rainbow-logo.png" alt="Rainbow Forest Paradise Resort Logo" class="logo">
                    <h2>Reservation Saved Successfully!</h2>
                </div>
                <div class="content">
                    <p>Dear <strong>' . $guest_name . '</strong>,</p>
                    <p>Your reservation has been saved successfully! You can continue with payment at any time using the link below.</p>
                    
                    <div class="reservation-details">
                        <h3>Reservation Details:</h3>
                        <ul>
                            <li><strong>Reservation Code:</strong> ' . htmlspecialchars($reservation_code) . '</li>
                            <li><strong>Check-in Date:</strong> ' . date('F j, Y', strtotime($check_in)) . '</li>
                            <li><strong>Check-out Date:</strong> ' . date('F j, Y', strtotime($check_out)) . '</li>
                            <li><strong>Duration:</strong> ' . ($is_day_tour ? 'Day Visit' : $nights . ' night' . ($nights > 1 ? 's' : '')) . '</li>
                            <li><strong>Guests:</strong> ' . $adults . ' Adult' . ($adults > 1 ? 's' : '') . ', ' . $children . ' Child' . ($children > 1 ? 'ren' : '') . ($pwd_senior > 0 ? ', ' . $pwd_senior . ' PWD/Senior' : '') . '</li>
                        </ul>
                        
                        ' . $room_details_html . '
                        
                        <h3 style="color: #2f5e2f; margin-top: 20px;">Payment Summary:</h3>
                        <ul>
                            <li><strong>Subtotal (before VAT):</strong> ₱' . number_format($subtotal_before_vat, 2) . '</li>
                            <li><strong>VAT (12%):</strong> ₱' . number_format($vat_amount, 2) . '</li>
                            <li><strong>Total Amount (VAT included):</strong> ₱' . number_format($total_amount, 2) . '</li>
                            <li><strong>Required Downpayment (40%):</strong> ₱' . number_format($total_amount * 0.4, 2) . '</li>
                        </ul>
                    </div>
                    
                    <div style="text-align: center; margin: 20px 0;">
                        <a href="https://rainbowforestparadiseresortandcampsite.com/booking_form.php?reservation_code=' . urlencode($reservation_code) . '&continue=true&adults=' . $adults . '&children=' . $children . '&pwd_senior=' . $pwd_senior . '" class="button">
                            Continue to Payment →
                        </a>
                    </div>
                    
                    <div class="important-info">
                        <h3>Important Information</h3>
                        <ul>
                            <li>Your reservation will expire in 3 hours if payment is not completed</li>
                            <li>You can access your reservation anytime using code: <strong>' . htmlspecialchars($reservation_code) . '</strong></li>
                            <li>A minimum downpayment of 40% is required to confirm your booking</li>
                        </ul>
                    </div>
                    
                    <p>If you have any questions, please don\'t hesitate to contact us!</p>
                    <p>Warm regards,<br>
                    <strong>Rainbow Forest Paradise Resort and Campsite</strong></p>
                </div>
                <div class="footer">
                    📍 Brgy. Cuyambay, Tanay, Rizal<br>
                    📞 0960 587 7561 | 🌐 www.rainbowforestparadiseresortandcampsite.com <br>
                    📧 rainbowforestparadise2020@gmail.com
                </div>
            </div>
        </body>
        </html>
        ';
    } else {
        $message = '
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
                h3 {
                    color: #2f5e2f;
                    margin-top: 20px;
                }
                ul {
                    padding-left: 20px;
                }
                ul li {
                    margin-bottom: 5px;
                }
                .next-steps {
                    background-color: #e8f4fd;
                    padding: 15px;
                    border-left: 4px solid #2196F3;
                    margin: 15px 0;
                    border-radius: 0 5px 5px 0;
                }
                .reservation-details {
                    background-color: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 15px 0;
                }
                .payment-summary {
                    background-color: #fff3cd;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 15px 0;
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="header">
                    <img src="https://rainbowforestparadiseresortandcampsite.com/images/rainbow-logo.png" alt="Rainbow Forest Paradise Resort Logo" class="logo">
                    <h2>Reservation Confirmed!</h2>
                </div>
                <div class="content">
                    <p>Dear <strong>' . $guest_name . '</strong>,</p>
                    <p>Thank you for your reservation! Your booking has been received and is being processed.</p>
                    
                    <div class="reservation-details">
                        <h3>Reservation Details:</h3>
                        <ul>
                            <li><strong>Reservation Code:</strong> ' . htmlspecialchars($reservation_code) . '</li>
                            <li><strong>Check-in Date:</strong> ' . date('F j, Y', strtotime($check_in)) . '</li>
                            <li><strong>Check-out Date:</strong> ' . date('F j, Y', strtotime($check_out)) . '</li>
                            <li><strong>Duration:</strong> ' . ($is_day_tour ? 'Day Visit' : $nights . ' night' . ($nights > 1 ? 's' : '')) . '</li>
                            <li><strong>Guests:</strong> ' . $adults . ' Adult' . ($adults > 1 ? 's' : '') . ', ' . $children . ' Child' . ($children > 1 ? 'ren' : '') . ($pwd_senior > 0 ? ', ' . $pwd_senior . ' PWD/Senior' : '') . '</li>
                        </ul>
                        
                        ' . $room_details_html . '
                    </div>
                    
                    <div class="payment-summary">
                        <h3>Payment Summary:</h3>
                        <ul>
                            <li><strong>Subtotal (before VAT):</strong> ₱' . number_format($subtotal_before_vat, 2) . '</li>
                            <li><strong>VAT (12%):</strong> ₱' . number_format($vat_amount, 2) . '</li>
                            <li><strong>Total Amount (VAT included):</strong> ₱' . number_format($total_amount, 2) . '</li>
                            <li><strong>Amount Paid:</strong> ₱' . number_format($amount_paid, 2) . '</li>
                            <li><strong>Remaining Balance:</strong> ₱' . number_format($balance, 2) . '</li>
                        </ul>
                    </div>
                    
                    <div class="next-steps">
                        <h3>📝 Next Steps</h3>
                        <ul>
                            <li>We will review your payment and confirm your reservation within 24 hours</li>
                            <li>You will receive a confirmation email once approved</li>
                            <li>Please keep your reservation code for your records</li>
                            <li>Present your reservation code <strong>' . htmlspecialchars($reservation_code) . '</strong> upon arrival</li>
                            ' . ($balance > 0 ? '<li><strong>Important:</strong> You have a remaining balance of ₱' . number_format($balance, 2) . ' to be paid upon arrival</li>' : '') . '
                        </ul>
                    </div>
                    
                    <p>We look forward to welcoming you at Rainbow Forest Paradise Resort and Campsite. If you have any questions, feel free to reply to this email.</p>
                    <p>Warm regards,<br>
                    <strong>Rainbow Forest Paradise Resort and Campsite</strong></p>
                </div>
                <div class="footer">
                    📍 Brgy. Cuyambay, Tanay, Rizal<br>
                    📞 0960 587 7561 | 🌐 www.rainbowforestparadiseresortandcampsite.com <br>
                    📧 rainbowforestparadise2020@gmail.com
                </div>
            </div>
        </body>
        </html>
        ';
    }
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Rainbow Forest Paradise Resort and Campsite <rainbowforestparadise2020@gmail.com>" . "\r\n";
    $headers .= "Reply-To: rainbowforestparadise2020@gmail.com" . "\r\n";
    
    $result = mail($email, $subject, $message, $headers);
    simple_log("Email send result: " . ($result ? 'success' : 'failed'));
    return $result;
}

function processFileUpload($file, $upload_dir) {
    simple_log("Processing file upload to: " . $upload_dir);
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    $file_name = basename($file['name']);
    $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
    $new_file_name = uniqid() . '_' . time() . '.' . $file_ext;
    $target_path = $upload_dir . $new_file_name;
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    if (!in_array(strtolower($file_ext), $allowed_types)) {
        throw new Exception("Only JPG, PNG, GIF, and PDF files are allowed.");
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new Exception("File size cannot exceed 5MB.");
    }
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        simple_log("File uploaded successfully: " . $target_path);
        return $target_path;
    } else {
        throw new Exception("Failed to upload payment receipt.");
    }
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    return_error("Invalid request method");
}

if (!file_exists('database.php')) {
    return_error("Database configuration file not found");
}

simple_log("Including database.php");
require_once 'database.php';

if (!isset($mysqli)) {
    return_error("Database connection variable not set");
}

if (!$mysqli || $mysqli->connect_errno) {
    return_error("Database connection failed: " . ($mysqli->connect_error ?? 'Unknown error'));
}

simple_log("Database connection successful");

try {
    simple_log("Starting transaction");
    if (!$mysqli->begin_transaction()) {
        throw new Exception("Failed to start transaction: " . $mysqli->error);
    }
    
    $required_fields = ['check_in', 'check_out', 'first_name', 'last_name', 'email', 'contact_number', 'adults'];
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    simple_log("Required fields validation passed");
    
    $check_in = trim($_POST['check_in']);
    $check_out = trim($_POST['check_out']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['contact_number']);
    $adults = intval($_POST['adults']);
    $children = intval($_POST['children'] ?? 0);
    $pwd_senior = intval($_POST['pwd_senior'] ?? 0);
    $special_requests = trim($_POST['special_requests'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }
    
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_in) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $check_out)) {
        throw new Exception("Invalid date format");
    }
    
    simple_log("Basic validation passed");
    
    $reservation_code = 'RES-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    simple_log("Generated reservation code: " . $reservation_code);
    
    $room_ids = $_POST['room_id'] ?? [];
    $room_quantities = $_POST['room_quantity'] ?? [];
    
    simple_log("Raw room_quantities: " . print_r($room_quantities, true));
    simple_log("Raw room_ids: " . print_r($room_ids, true));
    
    $room_tour_types = $_POST['room_tour_type'] ?? [];
    $default_tour_type = $_POST['default_tour_type'] ?? 'day_tour';
    
    if (empty($room_ids)) {
        throw new Exception("No rooms selected");
    }
    
    simple_log("Room IDs: " . print_r($room_ids, true));
    simple_log("Room quantities: " . print_r($room_quantities, true));
    
    simple_log("=== CHECKING ROOM AVAILABILITY ===");
    foreach ($room_ids as $index => $room_id) {
        $quantity = intval($room_quantities[$index]);
        simple_log("Checking availability for room $room_id, quantity $quantity");
        checkRoomAvailabilityForBooking($mysqli, $room_id, $check_in, $check_out, $quantity);
        simple_log("Room $room_id availability confirmed");
    }
    
    $basePrice = 0;
    $is_day_tour = ($check_in === $check_out);
    $nights = $is_day_tour ? 1 : max(1, (strtotime($check_out) - strtotime($check_in)) / (24 * 60 * 60));
    $reservation_type = 'public';
    $isPrivateRoom = false;
    $total_guests = $adults + $children + $pwd_senior;
    
    simple_log("Is day tour: " . ($is_day_tour ? 'yes' : 'no'));
    simple_log("Nights: " . $nights);
    simple_log("Total guests: " . $total_guests);
    
    // Check if private room is selected
    foreach ($room_ids as $room_id) {
        if ($room_id == 28) {
            $isPrivateRoom = true;
            $reservation_type = 'private';
            break;
        }
    }
    
    simple_log("Is private room: " . ($isPrivateRoom ? 'yes' : 'no'));
    
    // Store room details for email
    $selected_rooms_for_email = [];
    
    foreach ($room_ids as $index => $room_id) {
        simple_log("Processing room ID: " . $room_id);
        
        $stmt = $mysqli->prepare("SELECT name, day_tour_price, night_tour_price, capacity, real_quantity FROM rooms WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Failed to prepare room query: " . $mysqli->error);
        }
        $stmt->bind_param("i", $room_id);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("Failed to execute room query: " . $stmt->error);
        }
        $result = $stmt->get_result();
        $room = $result->fetch_assoc();
        $stmt->close();
        
        if (!$room) {
            throw new Exception("Room not found: " . $room_id);
        }
        
        simple_log("Room found: " . $room['name']);
        
        $quantity = intval($room_quantities[$index] ?? 0);
        if ($quantity <= 0 || $quantity > $room['real_quantity']) {
            throw new Exception("Invalid quantity for room " . $room['name'] . ". Requested: $quantity, Available: " . $room['real_quantity']);
        }
        
        $tour_type = isset($room_tour_types[$index]) ? $room_tour_types[$index] : $default_tour_type;
        
        // Store room details for email
        $selected_rooms_for_email[] = [
            'room_id' => $room_id,
            'name' => $room['name'],
            'quantity' => $quantity,
            'capacity' => $room['capacity'],
            'tour_type' => $tour_type
        ];
        
        if ($room_id == 28) {
            // Private room pricing - base rate only (up to 30 guests)
            // Excess guest fees are calculated separately as entrance fees
            if ($tour_type === 'day_tour') {
                if ($total_guests <= 10) {
                    $room_price = 8000;
                } else if ($total_guests <= 15) {
                    $room_price = 9000;
                } else if ($total_guests <= 20) {
                    $room_price = 10000;
                } else if ($total_guests <= 25) {
                    $room_price = 11000;
                } else {
                    // 26+ guests use the 30-guest rate
                    $room_price = 12000;
                }
            } else if ($tour_type === 'overnight_pm') {
                if ($total_guests <= 10) {
                    $room_price = 9000;
                } else if ($total_guests <= 15) {
                    $room_price = 10000;
                } else if ($total_guests <= 20) {
                    $room_price = 11000;
                } else if ($total_guests <= 25) {
                    $room_price = 12000;
                } else {
                    // 26+ guests use the 30-guest rate
                    $room_price = 13000;
                }
            } else {
                // overnight_am or whole_day
                if ($total_guests <= 10) {
                    $room_price = 12000;
                } else if ($total_guests <= 15) {
                    $room_price = 13000;
                } else if ($total_guests <= 20) {
                    $room_price = 15000;
                } else if ($total_guests <= 25) {
                    $room_price = 16000;
                } else {
                    // 26+ guests use the 30-guest rate
                    $room_price = 18000;
                }
            }
            if (!$is_day_tour) {
                $room_price *= $nights;
            }
        } else {
            $room_price = $is_day_tour ? floatval($room['day_tour_price']) : floatval($room['night_tour_price']);
            if (!$is_day_tour) {
                $room_price *= $nights;
            }
        }
        $basePrice += $room_price * $quantity;
        simple_log("Room price calculated: " . $room_price . " x " . $quantity . " = " . ($room_price * $quantity));
    }
    
    simple_log("Base price total: " . $basePrice);
    
    // Calculate extras (second house)
    $extrasPrice = 0;
    $add_second_house = 0;
    
    if (isset($_POST['add_second_house']) && $_POST['add_second_house'] == 'on') {
        $add_second_house = 1;
        // Only charge if not automatically included (private room with 30+ guests)
        if (!($isPrivateRoom && $total_guests >= 30)) {
            $second_house_fee = $is_day_tour ? 5000 : 5000 * $nights;
            $extrasPrice += $second_house_fee;
            simple_log("Second house fee added: " . $second_house_fee);
        } else {
            simple_log("Second house automatically included (30+ guests in private room) - no charge");
        }
    } elseif ($isPrivateRoom && $total_guests >= 30) {
        // Auto-add second house for private room with 30+ guests
        $add_second_house = 1;
        simple_log("Second house automatically added for 30+ guests in private room");
    }
    
    // Determine tour type for entrance fees
    $displayIsDayTour = $is_day_tour;
    if (!$displayIsDayTour && !empty($room_tour_types)) {
        foreach ($room_tour_types as $tour_type) {
            if ($tour_type === 'day_tour') {
                $displayIsDayTour = true;
                break;
            }
        }
    }
    
    simple_log("Display is day tour: " . ($displayIsDayTour ? 'yes' : 'no'));
    
    // Calculate entrance fees - EXACTLY like booking_form.php
    $entranceFees = 0;
    if (!$isPrivateRoom) {
        // Regular booking: charge all guests PER NIGHT
        $adultFeeRate = $displayIsDayTour ? 200 : 250;
        $childFeeRate = $displayIsDayTour ? 150 : 200;
        $pwdSeniorFeeRate = $displayIsDayTour ? 160 : 200;
        
        $entranceFees += ($adults * $adultFeeRate) * $nights;
        $entranceFees += ($children * $childFeeRate) * $nights;
        $entranceFees += ($pwd_senior * $pwdSeniorFeeRate) * $nights;
        
        simple_log("Regular entrance fees: Adults($adults x $adultFeeRate x $nights nights) + Children($children x $childFeeRate x $nights nights) + PWD/Senior($pwd_senior x $pwdSeniorFeeRate x $nights nights) = $entranceFees");
    } else {
        // Private room: FREE for first 30 guests, charge excess
        if ($total_guests > 30) {
            $excessGuests = $total_guests - 30;
            $privateTourType = '';
            
            // Find the private room's tour type
            foreach ($room_ids as $index => $room_id) {
                if ($room_id == 28) {
                    $privateTourType = isset($room_tour_types[$index]) ? $room_tour_types[$index] : $default_tour_type;
                    break;
                }
            }
            
            // Charge per excess guest based on tour type
            $excessFeePerGuest = 0;
            if ($privateTourType === 'day_tour') {
                $excessFeePerGuest = 400;
            } elseif ($privateTourType === 'overnight_pm') {
                $excessFeePerGuest = 500;
            } elseif ($privateTourType === 'whole_day' || $privateTourType === 'overnight_am') {
                $excessFeePerGuest = 600;
            }
            
            // For day tours, don't multiply by nights; for overnight, multiply by nights
            if ($privateTourType === 'day_tour') {
                $entranceFees = $excessGuests * $excessFeePerGuest;
                simple_log("Private room excess entrance fees (day tour): $excessGuests excess guests x $excessFeePerGuest = $entranceFees");
            } else {
                $entranceFees = ($excessGuests * $excessFeePerGuest) * $nights;
                simple_log("Private room excess entrance fees (overnight): $excessGuests excess guests x $excessFeePerGuest x $nights nights = $entranceFees");
            }
        } else {
            simple_log("Private room: FREE entrance for all $total_guests guests (under 30)");
        }
    }
    
    // Calculate total with VAT already included
    $totalWithVAT = $basePrice + $entranceFees + $extrasPrice;
    
    // Extract VAT that's already included - VAT = Total × (12/112)
    $vatAmount = round($totalWithVAT * (12/112), 2);
    
    // Calculate subtotal by removing VAT
    $subtotalBeforeVAT = $totalWithVAT - $vatAmount;
    
    // Total amount (VAT included)
    $totalPrice = $totalWithVAT;
    
    $amount_paid = floatval($_POST['amount_paid'] ?? 0);
    $balance_due = $totalPrice - $amount_paid;
    
    simple_log("=== PRICING SUMMARY ===");
    simple_log("Base Price (Rooms): " . $basePrice);
    simple_log("Entrance Fees: " . $entranceFees);
    simple_log("Extras (Second House): " . $extrasPrice);
    simple_log("Subtotal before VAT: " . $subtotalBeforeVAT);
    simple_log("VAT (12% component): " . $vatAmount);
    simple_log("Total (VAT-inclusive): " . $totalPrice);
    simple_log("Amount Paid: " . $amount_paid);
    simple_log("Balance Due: " . $balance_due);
    
    $payment_method = $_POST['payment_method'] ?? '';
    $payment_reference = '';
    $payment_receipt_path = null;
    
    if (!$is_save_for_later && $amount_paid > 0) {
        if ($payment_method == 'GCASH') {
            $payment_reference = trim($_POST['gcash_reference'] ?? '');
            if (isset($_FILES['gcash_payment_receipt']) && $_FILES['gcash_payment_receipt']['error'] == 0) {
                $payment_receipt_path = processFileUpload($_FILES['gcash_payment_receipt'], 'payment_receipts/');
            }
        } else if ($payment_method == 'RCBC') {
            $payment_reference = trim($_POST['bank_reference'] ?? '');
            if (isset($_FILES['bank_payment_receipt']) && $_FILES['bank_payment_receipt']['error'] == 0) {
                $payment_receipt_path = processFileUpload($_FILES['bank_payment_receipt'], 'payment_receipts/');
            }
        }
    }
    
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $status = $is_save_for_later ? 'saved' : 'pending';
    $payment_status = ($amount_paid > 0) ? 'partial' : 'unpaid';
    $expires_at = time() + (3 * 60 * 60);
    
    $columns_result = $mysqli->query("DESCRIBE reservations");
    $existing_columns = [];
    while ($row = $columns_result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    simple_log("Existing columns: " . implode(', ', $existing_columns));
    
    $insert_fields = [];
    $insert_values = [];
    $bind_types = '';
    $bind_params = [];
    
    // Initialize all tour type booleans to 0
    $day_tour = 0;
    $whole_day_morning_tour = 0;
    $whole_day_night_tour = 0;
    $night_tour_am = 0;
    $night_tour_pm = 0;
    
    // Determine which tour type(s) are being used
    // Priority: If ANY room has a specific tour type, set that boolean
    foreach ($room_tour_types as $tour_type) {
        switch ($tour_type) {
            case 'day_tour':
                $day_tour = 1;
                break;
            case 'overnight_am':
                $night_tour_am = 1;
                break;
            case 'overnight_pm':
                $night_tour_pm = 1;
                break;
            case 'whole_day':
                $whole_day_morning_tour = 1;
                break;
            case 'overnight_special':
                $whole_day_night_tour = 1;
                break;
        }
    }
    
    // If no specific tour type was set in room_tour_types, use the default
    if ($day_tour == 0 && $night_tour_am == 0 && $night_tour_pm == 0 && 
        $whole_day_morning_tour == 0 && $whole_day_night_tour == 0) {
        
        switch ($default_tour_type) {
            case 'day_tour':
                $day_tour = 1;
                break;
            case 'overnight_am':
                $night_tour_am = 1;
                break;
            case 'overnight_pm':
                $night_tour_pm = 1;
                break;
            case 'whole_day':
                $whole_day_morning_tour = 1;
                break;
            case 'overnight_special':
                $whole_day_night_tour = 1;
                break;
            default:
                $day_tour = 1; // fallback to day tour
        }
    }
    
    simple_log("Tour type booleans: day_tour=$day_tour, night_tour_am=$night_tour_am, night_tour_pm=$night_tour_pm, whole_day_morning=$whole_day_morning_tour, whole_day_night=$whole_day_night_tour");

    
    $field_map = [
        'reservation_code' => ['s', $reservation_code],
        'user_id' => ['i', $user_id],
        'check_in' => ['s', $check_in],
        'check_out' => ['s', $check_out],
        'first_name' => ['s', $first_name],
        'last_name' => ['s', $last_name],
        'email' => ['s', $email],
        'contact_number' => ['s', $contact_number],
        'adults' => ['i', $adults],
        'adult_count' => ['i', $adults],
        'children' => ['i', $children],
        'kid_count' => ['i', $children],
        'pwd_senior' => ['i', $pwd_senior],
        'pwd_senior_count' => ['i', $pwd_senior],
        'special_requests' => ['s', $special_requests],
        'total_amount' => ['d', $totalPrice],
        'total_price' => ['d', $totalPrice],
        'base_price' => ['d', $basePrice],
        'extras_price' => ['d', $extrasPrice],
        'entrance_fees' => ['d', $entranceFees],
        'vat_amount' => ['d', $vatAmount],
        'subtotal_before_vat' => ['d', $subtotalBeforeVAT],
        'amount_paid' => ['d', $amount_paid],
        'balance' => ['d', $balance_due],
        'balance_due' => ['d', $balance_due],
        'payment_method' => ['s', $payment_method],
        'payment_reference' => ['s', $payment_reference],
        'payment_receipt' => ['s', $payment_receipt_path],
        'status' => ['s', $status],
        'payment_status' => ['s', $payment_status],
        'reservation_type' => ['s', $reservation_type],
        'guest_type' => ['s', 'guest'],
        'add_second_house' => ['i', $add_second_house],
        'default_tour_type' => ['s', $default_tour_type],
        'expires_at' => ['i', $expires_at],
        'created_at' => ['s', date('Y-m-d H:i:s')],
        'day_tour' => ['i', $day_tour],
        'whole_day_morning_tour' => ['i', $whole_day_morning_tour],
        'whole_day_night_tour' => ['i', $whole_day_night_tour],
        'night_tour_am' => ['i', $night_tour_am],
        'night_tour_pm' => ['i', $night_tour_pm]
    ];
    
    foreach ($field_map as $field => $data) {
        if (in_array($field, $existing_columns)) {
            $insert_fields[] = $field;
            $insert_values[] = '?';
            $bind_types .= $data[0];
            $bind_params[] = $data[1];
        }
    }
    
    $sql = "INSERT INTO reservations (" . implode(', ', $insert_fields) . ") VALUES (" . implode(', ', $insert_values) . ")";
    simple_log("Insert SQL: " . $sql);
    
    $stmt = $mysqli->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare insert statement: " . $mysqli->error);
    }
    
    if (!empty($bind_params)) {
        $stmt->bind_param($bind_types, ...$bind_params);
    }
    
    simple_log("Executing insert statement");
    if (!$stmt->execute()) {
        $error = $stmt->error;
        $stmt->close();
        throw new Exception("Failed to insert reservation: " . $error);
    }
    
    $reservation_id = $mysqli->insert_id;
    $stmt->close();
    simple_log("Reservation inserted with ID: " . $reservation_id);
    
    if ($amount_paid > 0 && !empty($payment_method) && !$is_save_for_later) {
        simple_log("Inserting payment record");
        $payment_status_for_table = 'Pending';
        
        $payment_stmt = $mysqli->prepare("
            INSERT INTO payments (
                user_id, 
                reservation_codes, 
                payment_method, 
                reference_number, 
                status, 
                file_path, 
                amount_paid, 
                payment_date
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$payment_stmt) {
            throw new Exception("Failed to prepare payment insert: " . $mysqli->error);
        }
        
        $payment_stmt->bind_param("isssssd", 
            $user_id, 
            $reservation_code, 
            $payment_method, 
            $payment_reference, 
            $payment_status_for_table, 
            $payment_receipt_path, 
            $amount_paid
        );
        
        if (!$payment_stmt->execute()) {
            $payment_error = $payment_stmt->error;
            $payment_stmt->close();
            throw new Exception("Failed to insert payment record: " . $payment_error);
        }
        
        $payment_id = $mysqli->insert_id;
        $payment_stmt->close();
        simple_log("Payment record inserted with ID: " . $payment_id);
    }
    
    simple_log("=== INSERTING ROOM RESERVATIONS ===");
    simple_log("Room IDs array: " . print_r($room_ids, true));
    simple_log("Room quantities array: " . print_r($room_quantities, true));
    simple_log("Room tour types array: " . print_r($room_tour_types, true));
    
    foreach ($room_ids as $index => $room_id) {
        $quantity = isset($room_quantities[$index]) ? intval($room_quantities[$index]) : 0;
        $tour_type = isset($room_tour_types[$index]) ? $room_tour_types[$index] : $default_tour_type;
        
        simple_log("Processing room $index: ID=$room_id, Quantity=$quantity, Tour Type=$tour_type");
        
        if ($quantity <= 0) {
            simple_log("ERROR: Invalid quantity for room ID $room_id: quantity is $quantity");
            throw new Exception("Invalid quantity for room ID $room_id: quantity is $quantity");
        }
        
        simple_log("Inserting room reservation: Room $room_id, Qty $quantity, Tour $tour_type");
        
        $table_check = $mysqli->query("SHOW TABLES LIKE 'reservation_room'");
        if ($table_check->num_rows == 0) {
            simple_log("Creating reservation_room table");
            $create_sql = "CREATE TABLE reservation_room (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reservation_id INT NOT NULL,
                room_id INT NOT NULL,
                quantity_booked INT NOT NULL DEFAULT 1,
                tour_type VARCHAR(50) DEFAULT 'day_tour',
                check_in_date DATE NOT NULL,
                check_out_date DATE NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE,
                FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
                INDEX idx_room_dates (room_id, check_in_date, check_out_date)
            )";
            if (!$mysqli->query($create_sql)) {
                throw new Exception("Failed to create reservation_room table: " . $mysqli->error);
            }
        }
        
        $columns_result = $mysqli->query("DESCRIBE reservation_room");
        $existing_columns = [];
        while ($row = $columns_result->fetch_assoc()) {
            $existing_columns[] = $row['Field'];
            simple_log("reservation_room column: " . $row['Field'] . " (" . $row['Type'] . ")");
        }
        
        if (in_array('quantity_booked', $existing_columns)) {
            $stmt = $mysqli->prepare("INSERT INTO reservation_room (reservation_id, room_id, quantity_booked, tour_type, check_in_date, check_out_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisss", $reservation_id, $room_id, $quantity, $tour_type, $check_in, $check_out);
        } elseif (in_array('quantity', $existing_columns)) {
            $stmt = $mysqli->prepare("INSERT INTO reservation_room (reservation_id, room_id, quantity, tour_type, check_in_date, check_out_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisss", $reservation_id, $room_id, $quantity, $tour_type, $check_in, $check_out);
        } elseif (in_array('room_quantity', $existing_columns)) {
            $stmt = $mysqli->prepare("INSERT INTO reservation_room (reservation_id, room_id, room_quantity, tour_type, check_in_date, check_out_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiisss", $reservation_id, $room_id, $quantity, $tour_type, $check_in, $check_out);
        } else {
            $stmt = $mysqli->prepare("INSERT INTO reservation_room (reservation_id, room_id, tour_type, check_in_date, check_out_date) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iisss", $reservation_id, $room_id, $tour_type, $check_in, $check_out);
            simple_log("WARNING: No quantity column found, inserting without quantity");
        }
        
        if (!$stmt) {
            throw new Exception("Failed to prepare room reservation insert: " . $mysqli->error);
        }
        
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception("Failed to insert room reservation: " . $error);
        }
        
        simple_log("Room reservation inserted successfully for room $room_id with quantity $quantity");
        $stmt->close();
    }
    
    simple_log("Committing transaction");
    if (!$mysqli->commit()) {
        throw new Exception("Failed to commit transaction: " . $mysqli->error);
    }
    
    simple_log("Sending email notification");
    try {
        $reservation_details = [
            'adults' => $adults,
            'children' => $children,
            'pwd_senior' => $pwd_senior,
            'rooms' => $selected_rooms_for_email,
            'tour_type' => $default_tour_type
        ];
        
        sendReservationEmail($email, $first_name, $last_name, $reservation_code, $check_in, $check_out, $totalPrice, $amount_paid, $is_save_for_later, $reservation_details);
    } catch (Exception $e) {
        simple_log("Email sending failed: " . $e->getMessage());
    }
    
    return_success($reservation_code, $reservation_id, $reservation_type);
    
} catch (Exception $e) {
    simple_log("EXCEPTION: " . $e->getMessage());
    simple_log("Exception file: " . $e->getFile() . " line: " . $e->getLine());
    if (isset($mysqli) && $mysqli->ping()) {
        $mysqli->rollback();
        simple_log("Transaction rolled back");
    }
    return_error($e->getMessage());
}
?>