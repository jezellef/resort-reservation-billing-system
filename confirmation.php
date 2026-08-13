<?php
session_start();

// Check if reservation code is in the session
if (!isset($_SESSION['reservation_code'])) {
    echo "No reservation code found in session. Please try booking again.";
    exit;
}

// Include database connection
$mysqli = require 'database.php';

// Get reservation code from session
$reservation_code = $_SESSION['reservation_code'];

// Initialize variables
$reservation = [];
$remaining_balance = 0;
$selected_rooms = [];

// Fetch reservation details from database
try {
    // Query to get reservation information
    $query = "SELECT * FROM reservations WHERE reservation_code = ?";
    
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("s", $reservation_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        $reservation = $row;
    } else {
        throw new Exception("Reservation not found");
    }
    
    $stmt->close();

    // Fetch room details for this reservation
    $roomQuery = "SELECT r.*, rr.quantity_booked as quantity, rr.tour_type 
                 FROM reservation_room rr 
                 JOIN rooms r ON rr.room_id = r.id 
                 WHERE rr.reservation_id = ?";
    
    $stmt = $mysqli->prepare($roomQuery);
    $stmt->bind_param("i", $reservation['id']);
    $stmt->execute();
    $roomResult = $stmt->get_result();
    
    while ($roomRow = $roomResult->fetch_assoc()) {
        $selected_rooms[] = $roomRow;
    }
    
    $stmt->close();
    
} catch (Exception $e) {
    // Log error for debugging
    error_log("Database error: " . $e->getMessage());
    
    echo "Error retrieving reservation details. Please contact support.";
    exit;
}

// Determine tour type from reservation data
$tourType = "Day Tour"; // Default

if ($reservation['day_tour'] == 1) {
    $tourType = 'Day Tour (8AM-5PM)';
} else if ($reservation['night_tour_am'] == 1) {
    $tourType = 'Overnight AM (9AM-7AM)';
} else if ($reservation['night_tour_pm'] == 1) {
    $tourType = 'Overnight PM (6PM-4PM)';
} else if ($reservation['whole_day_morning_tour'] == 1 && $reservation['whole_day_night_tour'] == 1) {
    $tourType = 'Whole Day (8AM-6PM next day)';
}

// Calculate total guests
$adults = intval($reservation['adult_count']);
$children = intval($reservation['kid_count']);
$pwdSenior = intval($reservation['pwd_senior_count'] ?? $reservation['pwd_senior'] ?? 0);
$totalGuests = $adults + $children + $pwdSenior;

// Format dates
$checkInDate = new DateTime($reservation['check_in']);
$checkOutDate = new DateTime($reservation['check_out']);
$formattedCheckIn = $checkInDate->format('F j, Y');
$formattedCheckOut = $checkOutDate->format('F j, Y');

// Determine if it's a day tour or overnight stay
$isDayTour = ($reservation['check_in'] === $reservation['check_out']);

// Calculate nights
$nights = $isDayTour ? 1 : round((strtotime($reservation['check_out']) - strtotime($reservation['check_in'])) / (60 * 60 * 24));

// Variable to track if Room ID 28 (Private Room) is selected
$hasPrivateRoom = false;
$has30PlusGuests = false;
$has50PlusGuests = false;

foreach ($selected_rooms as $room) {
    if ($room['id'] == 28) {
        $hasPrivateRoom = true;
        if ($totalGuests >= 30) {
            $has30PlusGuests = true;
        }
        if ($totalGuests >= 50) {
            $has50PlusGuests = true;
        }
        break;
    }
}

// USE STORED VALUES FROM DATABASE - DO NOT RECALCULATE
$roomsPrice = floatval($reservation['base_price'] ?? 0);
$extrasPrice = floatval($reservation['extras_price'] ?? 0);
$additionalFee = floatval($reservation['additional_fee'] ?? 0);
$totalAmount = floatval($reservation['total_amount'] ?? $reservation['total_price'] ?? 0);

// RECALCULATE entrance fees for display (don't trust stored value for private rooms)
$entranceFees = 0;
if (!$hasPrivateRoom) {
    // Regular rooms: calculate with nights multiplier
    $entranceFees += ($adults * $adultFeeRate) * $nights;
    $entranceFees += ($children * $childFeeRate) * $nights;
    $entranceFees += ($pwd_senior * $pwdSeniorFeeRate) * $nights;
} elseif ($hasPrivateRoom && $totalGuests > 30) {
    // For private rooms with excess guests, recalculate
    $excessGuests = $totalGuests - 30;
    $tourTypeForFee = '';
    
    foreach ($selected_rooms as $room) {
        if ($room['id'] == 28) {
            $tourTypeForFee = $room['tour_type'] ?? 'day_tour';
            break;
        }
    }
    
    $excessFeeRate = 0;
    if ($tourTypeForFee === 'day_tour') {
        $excessFeeRate = 400;
    } elseif ($tourTypeForFee === 'overnight_pm') {
        $excessFeeRate = 500;
    } elseif ($tourTypeForFee === 'whole_day' || $tourTypeForFee === 'overnight_am') {
        $excessFeeRate = 600;
    }
    
    // For day tours, don't multiply by nights; for overnight, multiply by nights
    if ($tourTypeForFee === 'day_tour') {
        $entranceFees = $excessGuests * $excessFeeRate;
    } else {
        $entranceFees = ($excessGuests * $excessFeeRate) * $nights;
    }
}

// Calculate total with VAT already included - EXACTLY like saved_billing.php
$totalWithVAT = $roomsPrice + $entranceFees + $extrasPrice + $additionalFee;

// Extract VAT that's already included - VAT = Total × (12/112)
$vatAmount = round($totalWithVAT * (12/112), 2);

// Calculate subtotal by removing VAT
$subtotalBeforeVAT = $totalWithVAT - $vatAmount;

// Use database total if available
if ($totalAmount > 0) {
    $totalWithVAT = $totalAmount;
    $vatAmount = round($totalAmount * (12/112), 2);
    $subtotalBeforeVAT = $totalAmount - $vatAmount;
} else {
    $totalAmount = $totalWithVAT;
}

// Calculate balance
$amount_paid = floatval($reservation['amount_paid'] ?? 0);
$remaining_balance = $totalAmount - $amount_paid;

// Display total
$displayTotal = $totalAmount;

// Check for second house
$hasSecondHouse = ($reservation['add_second_house'] == 1);

// Determine tour type for entrance fee breakdown display
$displayIsDayTour = $isDayTour;
if (!$displayIsDayTour && !empty($selected_rooms)) {
    foreach ($selected_rooms as $room) {
        if (isset($room['tour_type']) && $room['tour_type'] === 'day_tour') {
            $displayIsDayTour = true;
            break;
        }
    }
}

// Set fee rates for display breakdown only
$adultFeeRate = $displayIsDayTour ? 200 : 250;
$childFeeRate = $displayIsDayTour ? 150 : 200;
$pwdSeniorFeeRate = $displayIsDayTour ? 160 : 200;

// Calculate entrance fee breakdown for display purposes only
$entranceFeeBreakdown = [];
if (!$hasPrivateRoom && $entranceFees > 0) {
    if ($adults > 0) {
        $entranceFeeBreakdown['adults'] = [
            'count' => $adults,
            'rate' => $adultFeeRate,
            'nights' => $nights,
            'total' => ($adults * $adultFeeRate) * $nights
        ];
    }
    if ($children > 0) {
        $entranceFeeBreakdown['children'] = [
            'count' => $children,
            'rate' => $childFeeRate,
            'nights' => $nights,
            'total' => ($children * $childFeeRate) * $nights
        ];
    }
    if ($pwdSenior > 0) {
        $entranceFeeBreakdown['pwd_senior'] = [
            'count' => $pwdSenior,
            'rate' => $pwdSeniorFeeRate,
            'nights' => $nights,
            'total' => ($pwd_senior * $pwdSeniorFeeRate) * $nights
        ];
    }
} else if ($hasPrivateRoom && $totalGuests > 30) {
    // Private room excess guest breakdown
    $excessGuests = $totalGuests - 30;
    $tourTypeForFee = '';
    
    foreach ($selected_rooms as $room) {
        if ($room['id'] == 28) {
            $tourTypeForFee = $room['tour_type'] ?? 'day_tour';
            break;
        }
    }
    
    $excessFeeRate = 0;
    if ($tourTypeForFee === 'day_tour') {
        $excessFeeRate = 400;
    } elseif ($tourTypeForFee === 'overnight_pm') {
        $excessFeeRate = 500;
    } elseif ($tourTypeForFee === 'whole_day' || $tourTypeForFee === 'overnight_am') {
        $excessFeeRate = 600;
    }
    
    // Calculate total based on tour type
    $excessTotal = ($tourTypeForFee === 'day_tour') 
        ? $excessGuests * $excessFeeRate 
        : ($excessGuests * $excessFeeRate) * $nights;
    
    $entranceFeeBreakdown['excess'] = [
        'count' => $excessGuests,
        'rate' => $excessFeeRate,
        'nights' => $nights,
        'tour_type' => $tourTypeForFee,
        'total' => $excessTotal
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Confirmation - Rainbow Forest Paradise Resort</title>
    <link rel="stylesheet" href="styles/booking-styles.css?v=1.1">
    <style>
        .container {
            max-width: 900px;
            margin: 30px auto;
            padding: 20px;
        }
        
        .confirmation-card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }
        
        .confirmation-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .confirmation-icon {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        
        .confirmation-icon .circle {
            width: 80px;
            height: 80px;
            background-color: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .confirmation-icon .checkmark {
            color: white;
            font-size: 40px;
            font-weight: bold;
        }
        
        .reservation-code {
            text-align: center;
            background-color: #f0f8ff;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-size: 18px;
            letter-spacing: 1px;
            border: 1px dashed #999;
        }
        
        .section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #4CAF50;
            display: inline-block;
        }
        
        .confirmation-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .detail-item {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 600;
            margin-right: 10px;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        .highlight {
            background-color: #e8f5e9;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 500;
        }
        
        .payment-summary {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .payment-row:last-child {
            border-bottom: none;
        }
        
        .payment-row.total {
            font-weight: bold;
            font-size: 18px;
            border-top: 2px solid #ddd;
            margin-top: 10px;
            padding-top: 15px;
        }
        
        .payment-row.subtotal-row {
            font-weight: 500;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }
        
        .entrance-breakdown {
            display: grid;
            grid-template-columns: 1fr auto;
            padding: 5px 0 5px 20px;
            font-size: 0.9em;
            color: #666;
            border-bottom: 1px solid #eee;
        }
        
        .entrance-breakdown:last-of-type {
            margin-bottom: 10px;
        }
        
        .balance-due {
            color: #e74c3c;
        }
        
        .payment-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-partial {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-unpaid {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .room-list {
            margin-top: 15px;
        }
        
        .room-item {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 4px solid #4CAF50;
        }
        
        .room-name {
            font-weight: bold;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .room-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 14px;
        }
        
        .private-room-pricing {
            margin-top: 10px;
            padding: 10px;
            background-color: #f0f8ff;
            border-radius: 4px;
            font-size: 13px;
            grid-column: 1 / -1;
        }
        
        .private-room-pricing .pricing-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .private-room-pricing .pricing-line {
            color: #555;
            margin-top: 3px;
        }
        
        .private-room-pricing .pricing-divider {
            border-top: 1px solid #ccc;
            margin: 5px 0;
            padding-top: 5px;
        }
        
        .special-notice {
            background-color: #e8f4fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .entrance-fee-notice {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .private-room-free-notice {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #155724;
        }
        
        .auto-inclusion-notice {
            background-color: #d4edda;
            color: #155724;
            padding: 8px;
            margin-top: 8px;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #388e3c;
        }
        
        .btn-secondary {
            background-color: #2196F3;
        }
        
        .btn-secondary:hover {
            background-color: #0b7dda;
        }
        
        .note {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .schedule-note {
            font-style: italic;
            color: #555;
            font-size: 14px;
        }
        
        @media screen and (max-width: 768px) {
            .confirmation-details {
                grid-template-columns: 1fr;
            }
            
            .room-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
   <?php include 'headers/header_p2.php'; ?>
    
    <div class="container">
        <div class="confirmation-card">
            <div class="confirmation-header">
                <h1>Booking Confirmation</h1>
                <p>Rainbow Forest Paradise Resort and Campsite</p>
            </div>
            
            <div class="confirmation-icon">
                <div class="circle">
                    <span class="checkmark">✓</span>
                </div>
            </div>
            
            <div class="special-notice">
                <h3>Thank You For Your Reservation!</h3>
                <p>Your booking request has been received and is being processed. We have sent a confirmation email to <strong><?php echo htmlspecialchars($reservation['email']); ?></strong>.</p>
            </div>
            
            <div class="reservation-code">
                <div class="detail-label">Reservation Code:</div>
                <div class="detail-value" style="font-size: 20px; letter-spacing: 2px; font-weight: bold;"><?php echo htmlspecialchars($reservation_code); ?></div>
                <div class="note">Please save this code for check-in and any future communications</div>
            </div>
            
            <div class="section">
                <h2 class="section-title">Booking Details</h2>
                <div class="confirmation-details">
                    <div class="detail-item">
                        <span class="detail-label">Guest Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email Address:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($reservation['email']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Contact Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($reservation['contact_number']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Tour Type:</span>
                        <span class="detail-value highlight"><?php echo htmlspecialchars($tourType); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Check-in Date:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($formattedCheckIn); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Check-out Date:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($formattedCheckOut); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Duration:</span>
                        <span class="detail-value">
                            <?php if ($isDayTour): ?>
                                Day Visit
                                <div class="schedule-note">8:00 AM to 5:00 PM</div>
                            <?php else: ?>
                                <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>
                                <div class="schedule-note">
                                    <?php
                                    if ($reservation['night_tour_am'] == 1) {
                                        echo "9:00 AM to 7:00 AM (next day)";
                                    } elseif ($reservation['night_tour_pm'] == 1) {
                                        echo "6:00 PM to 4:00 PM (next day)";
                                    } elseif ($reservation['whole_day_morning_tour'] == 1 && $reservation['whole_day_night_tour'] == 1) {
                                        echo "8:00 AM to 6:00 PM (next day)";
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Guests:</span>
                        <span class="detail-value">
                            <?php echo $adults; ?> Adult<?php echo $adults > 1 ? 's' : ''; ?>, 
                            <?php echo $children; ?> Child<?php echo $children > 1 ? 'ren' : ''; ?>
                            <?php if ($pwdSenior > 0): ?>
                                , <?php echo $pwdSenior; ?> PWD/Senior
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="section">
                <h2 class="section-title">Selected Accommodations</h2>
                
                <?php if (count($selected_rooms) > 0): ?>
                    <div class="room-list">
                        <?php foreach ($selected_rooms as $room): ?>
                            <div class="room-item">
                                <div class="room-name"><?php echo htmlspecialchars($room['name']); ?></div>
                                <div class="room-details">
                                    <div>
                                        <span class="detail-label">Quantity:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($room['quantity']); ?></span>
                                    </div>
                                    <div>
                                        <span class="detail-label">Capacity:</span>
                                        <span class="detail-value"><?php echo htmlspecialchars($room['capacity']); ?> guests per room</span>
                                    </div>
                                    <div>
                                        <span class="detail-label">Tour Type:</span>
                                        <span class="detail-value">
                                            <?php 
                                            $roomTourType = htmlspecialchars($room['tour_type']);
                                            switch ($roomTourType) {
                                                case 'day_tour':
                                                    echo "Day Tour (8AM-5PM)";
                                                    break;
                                                case 'overnight_am':
                                                    echo "Overnight AM (9AM-7AM)";
                                                    break;
                                                case 'overnight_pm':
                                                    echo "Overnight PM (6PM-4PM)";
                                                    break;
                                                case 'whole_day':
                                                    echo "Whole Day (8AM-6PM next day)";
                                                    break;
                                                default:
                                                    echo $roomTourType;
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <?php if ($room['id'] == 28): ?>
                                    <?php
                                    // Calculate private room pricing breakdown
                                    $tourTypeForPrice = $room['tour_type'] ?? 'day_tour';
                                    $baseGuestCount = min($totalGuests, 30);
                                    $excessGuests = max(0, $totalGuests - 30);
                                    
                                    // Determine base price
                                    $basePrice = 0;
                                    if ($tourTypeForPrice === 'day_tour') {
                                        if ($totalGuests <= 10) $basePrice = 8000;
                                        elseif ($totalGuests <= 15) $basePrice = 9000;
                                        elseif ($totalGuests <= 20) $basePrice = 10000;
                                        elseif ($totalGuests <= 25) $basePrice = 11000;
                                        else $basePrice = 12000;
                                    } elseif ($tourTypeForPrice === 'overnight_pm') {
                                        if ($totalGuests <= 10) $basePrice = 9000;
                                        elseif ($totalGuests <= 15) $basePrice = 10000;
                                        elseif ($totalGuests <= 20) $basePrice = 11000;
                                        elseif ($totalGuests <= 25) $basePrice = 12000;
                                        else $basePrice = 13000;
                                    } elseif ($tourTypeForPrice === 'whole_day' || $tourTypeForPrice === 'overnight_am') {
                                        if ($totalGuests <= 10) $basePrice = 12000;
                                        elseif ($totalGuests <= 15) $basePrice = 13000;
                                        elseif ($totalGuests <= 20) $basePrice = 15000;
                                        elseif ($totalGuests <= 25) $basePrice = 16000;
                                        else $basePrice = 18000;
                                    }
                                    
                                    // Determine per-head rate for excess
                                    $excessRate = 0;
                                    if ($tourTypeForPrice === 'day_tour') $excessRate = 400;
                                    elseif ($tourTypeForPrice === 'overnight_pm') $excessRate = 500;
                                    elseif ($tourTypeForPrice === 'whole_day' || $tourTypeForPrice === 'overnight_am') $excessRate = 600;
                                    
                                    $totalRoomPrice = $basePrice;
                                    if ($excessGuests > 0) {
                                        $totalRoomPrice += ($excessGuests * $excessRate);
                                    }
                                    
                                    if ($tourTypeForPrice !== 'day_tour') {
                                        $totalRoomPrice *= $nights;
                                    }
                                    ?>
                                    
                                    <div class="private-room-pricing">
                                        <div class="pricing-title">Price Breakdown:</div>
                                        <div class="pricing-line">
                                            • Base price (up to <?php echo $baseGuestCount; ?> guests): 
                                            <strong>₱<?php echo number_format($basePrice, 2); ?></strong>
                                            <?php if ($tourTypeForPrice !== 'day_tour' && $nights > 1): ?>
                                                per night
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($excessGuests > 0): ?>
                                        <div class="pricing-line">
                                            • Additional <?php echo $excessGuests; ?> guest<?php echo $excessGuests > 1 ? 's' : ''; ?> 
                                            (<?php echo $excessGuests; ?> × ₱<?php echo number_format($excessRate, 2); ?>): 
                                            <strong>₱<?php echo number_format($excessGuests * $excessRate, 2); ?></strong>
                                            <?php if ($tourTypeForPrice !== 'day_tour' && $nights > 1): ?>
                                                per night
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($tourTypeForPrice !== 'day_tour' && $nights > 1): ?>
                                        <div class="pricing-line pricing-divider">
                                            • Subtotal per night: ₱<?php echo number_format($basePrice + ($excessGuests * $excessRate), 2); ?>
                                        </div>
                                        <div class="pricing-line">
                                            • Total for <?php echo $nights; ?> nights: 
                                            <strong style="color: #4CAF50;">₱<?php echo number_format($totalRoomPrice, 2); ?></strong>
                                        </div>
                                        <?php else: ?>
                                        <div class="pricing-line pricing-divider">
                                            <strong style="color: #4CAF50;">Total: ₱<?php echo number_format($totalRoomPrice, 2); ?></strong>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No room details available.</p>
                <?php endif; ?>
                
                <?php if ($hasPrivateRoom): ?>
                    <?php if ($has30PlusGuests): ?>
                        <div class="auto-inclusion-notice">
                            <strong>Included:</strong> Second house is automatically included with your private room booking for 30+ guests.
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($has50PlusGuests): ?>
                        <div class="auto-inclusion-notice">
                            <strong>Included:</strong> Extra tent is automatically included with your private room booking for 50+ guests.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <?php if ($hasSecondHouse || $extrasPrice > 0): ?>
            <div class="section">
                <h2 class="section-title">Additional Options</h2>
                <div class="confirmation-details">
                    <?php if ($hasSecondHouse): ?>
                    <div class="detail-item">
                        <span class="detail-label">Second House:</span>
                        <span class="detail-value">
                            <?php if ($has30PlusGuests): ?>
                                <span style="color:#4CAF50;">Automatically included for 30+ guests</span>
                            <?php else: ?>
                                Added to reservation
                                <?php if (!$isDayTour && $nights > 1): ?>
                                    (<?php echo $nights; ?> nights)
                                <?php endif; ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($reservation['special_requests'])): ?>
            <div class="section">
                <h2 class="section-title">Special Requests</h2>
                <p><?php echo nl2br(htmlspecialchars($reservation['special_requests'])); ?></p>
            </div>
            <?php endif; ?>
            
            <div class="section">
                <h2 class="section-title">Payment Summary</h2>
                
                <div class="payment-summary">
                    <?php if ($roomsPrice > 0): ?>
                    <div class="payment-row">
                        <span>Room Total:</span>
                        <span>₱<?php echo number_format($roomsPrice, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="payment-row">
                        <span>Entrance Fees:</span>
                        <span><?php echo ($hasPrivateRoom && $totalGuests <= 30) ? '<span style="color: #28a745; font-weight: bold;">FREE (Private Room)</span>' : '₱' . number_format($entranceFees, 2); ?></span>
                    </div>
                    
                    <?php if ($hasPrivateRoom && $totalGuests > 30): ?>
                        <?php 
                        $excessGuests = $totalGuests - 30;
                        $excessFeeRate = 0;
                        $tourTypeForFee = '';
                        
                        if (isset($selected_rooms)) {
                            foreach ($selected_rooms as $room) {
                                if ($room['id'] == 28) {
                                    $tourTypeForFee = $room['tour_type'] ?? 'day_tour';
                                    break;
                                }
                            }
                        }
                        
                        if ($tourTypeForFee === 'day_tour') $excessFeeRate = 400;
                        elseif ($tourTypeForFee === 'overnight_pm') $excessFeeRate = 500;
                        elseif ($tourTypeForFee === 'whole_day' || $tourTypeForFee === 'overnight_am') $excessFeeRate = 600;
                        ?>
                        <div class="entrance-breakdown">
                            <div>• First 30 guests: FREE</div>
                            <div>₱0.00</div>
                        </div>
                        <div class="entrance-breakdown">
                            <div>• Additional <?php echo $excessGuests; ?> guest<?php echo $excessGuests > 1 ? 's' : ''; ?> 
                                (<?php echo $excessGuests; ?> × ₱<?php echo number_format($excessFeeRate, 2); ?>
                                <?php if ($tourTypeForFee !== 'day_tour'): ?>
                                    × <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>
                                <?php endif; ?>):
                            </div>
                            <div>₱<?php echo number_format($entranceFees, 2); ?></div>
                        </div>
                    <?php elseif (!$hasPrivateRoom && !empty($entranceFeeBreakdown)): ?>
                    <div id="entrance-breakdown-container">
                        <?php if (isset($entranceFeeBreakdown['adults'])): ?>
                        <div class="entrance-breakdown">
                            <div>• Adults (<?php echo $entranceFeeBreakdown['adults']['count']; ?> × ₱<?php echo $entranceFeeBreakdown['adults']['rate']; ?> × <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>):</div>
                            <div>₱<?php echo number_format($entranceFeeBreakdown['adults']['total'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($entranceFeeBreakdown['children'])): ?>
                        <div class="entrance-breakdown">
                            <div>• Children (<?php echo $entranceFeeBreakdown['children']['count']; ?> × ₱<?php echo $entranceFeeBreakdown['children']['rate']; ?> × <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>):</div>
                            <div>₱<?php echo number_format($entranceFeeBreakdown['children']['total'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (isset($entranceFeeBreakdown['pwd_senior'])): ?>
                        <div class="entrance-breakdown">
                            <div>• PWD/Senior (<?php echo $entranceFeeBreakdown['pwd_senior']['count']; ?> × ₱<?php echo $entranceFeeBreakdown['pwd_senior']['rate']; ?> × <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>):</div>
                            <div>₱<?php echo number_format($entranceFeeBreakdown['pwd_senior']['total'], 2); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($extrasPrice > 0): ?>
                    <div class="payment-row">
                        <span>Extra Tent/Second House:</span>
                        <span>₱<?php echo number_format($extrasPrice, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($additionalFee > 0): ?>
                    <div class="payment-row">
                        <span>Additional Items:</span>
                        <span>₱<?php echo number_format($additionalFee, 2); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="payment-row subtotal-row">
                        <span>Subtotal (before VAT):</span>
                        <span>₱<?php echo number_format($subtotalBeforeVAT, 2); ?></span>
                    </div>
                    
                    <div class="payment-row">
                        <span>VAT (12% included):</span>
                        <span>₱<?php echo number_format($vatAmount, 2); ?></span>
                    </div>
                    
                    <div class="payment-row total">
                        <span><strong>Total Amount (VAT included):</strong></span>
                        <span><strong>₱<?php echo number_format($displayTotal, 2); ?></strong></span>
                    </div>
                    
                    <div class="payment-row">
                        <span>Amount Paid:</span>
                        <span>₱<?php echo number_format($amount_paid, 2); ?></span>
                    </div>
                    
                    <div class="payment-row total">
                        <span>Balance Due:</span>
                        <span class="balance-due">₱<?php echo number_format(max(0, $remaining_balance), 2); ?></span>
                    </div>
                </div>
                
                <?php if ($hasPrivateRoom): ?>
                <div class="private-room-free-notice">
                    <h3>🎉 Private Room Benefit</h3>
                    <p><strong>Entrance fees are FREE for the first 30 guests!</strong></p>
                    <?php if ($totalGuests > 30): ?>
                    <p style="margin-top: 10px;">
                        You have <?php echo ($totalGuests - 30); ?> guest(s) above 30. 
                        Standard entrance fees apply for these additional guests.
                    </p>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!$hasPrivateRoom && ($pwdSenior > 0 || $adults > 0 || $children > 0)): ?>
                <div class="entrance-fee-notice">
                    <h3>Important Note: Bring the following to avail discount</h3>
                    <ul>
                        <li>PWD ID</li>
                        <li>PWD Notebook</li>
                        <li>Senior ID</li>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="section">
                <h2 class="section-title">What's Next?</h2>
                <ul>
                    <li>You will need to present your reservation code <strong><?php echo htmlspecialchars($reservation_code); ?></strong> upon arrival.</li>
                    <?php if ($remaining_balance > 0): ?>
                    <li><strong>Important:</strong> You have a remaining balance of ₱<?php echo number_format($remaining_balance, 2); ?> to be paid upon arrival.</li>
                    <?php endif; ?>
                    <li>If you have any questions, please contact us at rainbowforestparadise2020@gmail.com or call 0960 587 7561</li>
                </ul>
            </div>
            
            <div class="action-buttons">
                <a href="index.php" class="btn btn-secondary">Return to Home</a>
            </div>
        </div>
    </div>
    
    <?php include 'footers/footer.php'; ?>
    
    <script>
        // Celebration effect when page loads
        document.addEventListener('DOMContentLoaded', function() {
            celebrateConfirmation();
        });
        
        function celebrateConfirmation() {
            // Optional confetti effect
            const confettiCount = 150;
            const colors = ['#4CAF50', '#2196F3', '#FFC107', '#9C27B0', '#E91E63'];
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.style.position = 'fixed';
                confetti.style.width = (Math.random() * 10) + 5 + 'px';
                confetti.style.height = (Math.random() * 10) + 5 + 'px';
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.borderRadius = '50%';
                confetti.style.left = Math.random() * 100 + 'vw';
                confetti.style.top = '-20px';
                confetti.style.zIndex = '1000';
                document.body.appendChild(confetti);
                
                const animation = confetti.animate(
                    [
                        { transform: 'translate(0, 0)', opacity: 1 },
                        { transform: `translate(${Math.random() * 100 - 50}px, ${window.innerHeight}px) rotate(${Math.random() * 360}deg)`, opacity: 0 }
                    ],
                    {
                        duration: Math.random() * 3000 + 2000,
                        easing: 'cubic-bezier(0.215, 0.61, 0.355, 1)'
                    }
                );
                
                animation.onfinish = () => {
                    confetti.remove();
                };
            }
        }
    </script>
</body>
</html>