<?php
session_start();
require_once 'database.php'; 
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);
$reservation = null;
$code = null;
$paymentStatus = 'Pending';

if (isset($_GET['code'])) {
    $code = mysqli_real_escape_string($mysqli, $_GET['code']); 
    $_SESSION['reservation_code'] = $code;
    $query = "SELECT * FROM reservations WHERE reservation_code = '$code'";
    $result = mysqli_query($mysqli, $query); 
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $tourType = '';
        if ($row['day_tour']) {
            $tourType = 'Day Tour';
        } elseif ($row['whole_day_morning_tour']) {
            $tourType = 'Whole Day Morning Tour';
        } elseif ($row['whole_day_night_tour']) {
            $tourType = 'Whole Day Night Tour';
        } elseif ($row['night_tour_am']) {
            $tourType = 'Night Tour AM';
        } elseif ($row['night_tour_pm']) {
            $tourType = 'Night Tour PM';
        } else {
            $tourType = 'Not specified';
        }
        
        $formattedTime = '';
        if (!empty($row['time'])) {
            $dt = DateTime::createFromFormat('H:i', $row['time']);
            if ($dt) {
                $formattedTime = $dt->format('h:i A');
            } else {
                $formattedTime = $row['time'];
            }
        }
        
        $reservation = [
            'reservation_code' => $row['reservation_code'],
            'guest_type' => $row['guest_type'] ?? 'guest',
            'reservation_type' => $row['reservation_type'] ?? 'public',
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'contact_number' => $row['contact_number'],
            'check_in' => $row['check_in'],
            'check_out' => $row['check_out'],
            'adults' => $row['adult_count'],
            'children' => $row['kid_count'] ?? 0,
            'pwd_senior' => $row['pwd_senior_count'] ?? $row['pwd_senior'] ?? 0,
            'total_amount' => $row['total_price'] ?? 0,
            'base_price' => $row['base_price'] ?? 0,
            'extras_price' => $row['extras_price'] ?? 0,
            'created_at' => $row['created_at'] ?? date('Y-m-d H:i:s'),
            'expires_at' => $row['expires_at'],
            'special_requests' => $row['special_requests'] ?? '',
            'tour_type' => $tourType,
            'time' => $formattedTime,
            'status' => $row['status'] ?? 'Pending',
            'payment_status' => $row['payment_status'] ?? 'Unpaid',
            'amount_paid' => $row['amount_paid'] ?? 0,
            'balance_due' => $row['balance_due'] ?? $row['total_price'],
            'extra_tent' => $row['extra_tent'] ?? 0,
            'additional_items' => $row['additional_items'] ?? '',
            'additional_fee' => $row['additional_fee'] ?? 0,
        ];
        
        if (empty($row['expires_at'])) {
            $expirationTime = time() + (3 * 60 * 60);
            $updateQuery = "UPDATE reservations SET expires_at = $expirationTime WHERE reservation_code = '$code'";
            if (mysqli_query($mysqli, $updateQuery)) {
                $reservation['expires_at'] = $expirationTime;
            }
        }
        
        $bookedRooms = [];
        $roomsQuery = "SELECT rb.*, r.name as room_name, r.capacity, r.description, r.day_tour_price, r.night_tour_price
                      FROM reservation_room rb 
                      JOIN rooms r ON rb.room_id = r.id 
                      WHERE rb.reservation_id = " . intval($row['id']);
        $roomsResult = mysqli_query($mysqli, $roomsQuery);
        
        if (!$roomsResult || mysqli_num_rows($roomsResult) == 0) {
            $roomsQuery = "SELECT rb.*, r.name as room_name, r.capacity, r.description, r.day_tour_price, r.night_tour_price
                          FROM reservation_room rb 
                          JOIN rooms r ON rb.room_id = r.id 
                          WHERE rb.reservation_code = '$code'";
            $roomsResult = mysqli_query($mysqli, $roomsQuery);
        }
        
        if ($roomsResult && mysqli_num_rows($roomsResult) > 0) {
            while ($roomRow = mysqli_fetch_assoc($roomsResult)) {
                $roomRow['quantity'] = $roomRow['quantity_booked'] ?? 1;
                $bookedRooms[] = $roomRow;
            }
        }
        $reservation['booked_rooms'] = $bookedRooms;
        
        $paymentQuery = "SELECT * FROM payments WHERE reservation_codes LIKE '%$code%' ORDER BY uploaded_at DESC LIMIT 1";
        $paymentResult = mysqli_query($mysqli, $paymentQuery);
        if ($paymentResult && mysqli_num_rows($paymentResult) > 0) {
            $paymentRow = mysqli_fetch_assoc($paymentResult);
            $paymentStatus = $paymentRow['status'];
        }
        
        $_SESSION['reservation_details'] = $reservation;
    } else {
        $_SESSION['error_message'] = "Reservation not found in database.";
        header("Location: booking_form.php");
        exit;
    }
} elseif (isset($_SESSION['reservation_details'])) {
    $reservation = $_SESSION['reservation_details'];
    $code = $_SESSION['reservation_code'] ?? null;
    if (!$code && isset($reservation['reservation_code'])) {
        $code = $reservation['reservation_code'];
        $_SESSION['reservation_code'] = $code;
    }
} else {
    $_SESSION['error_message'] = "No reservation details found. Please create a new reservation.";
    header("Location: booking_form.php");
    exit;
}

if (!$reservation || !$code) {
    $_SESSION['error_message'] = "Incomplete reservation details. Please create a new reservation.";
    header("Location: booking_form.php");
    exit;
}

$current_time = time();
if (isset($reservation['expires_at']) && $current_time > $reservation['expires_at']) {
    $_SESSION['error_message'] = "Your reservation has expired. Please create a new one.";
    unset($_SESSION['reservation_details']);
    header("Location: booking_form.php");
    exit;
}

// Get guest counts
$adults = intval($reservation['adults'] ?? 1);
$children = intval($reservation['children'] ?? 0);
$pwd_senior = intval($reservation['pwd_senior'] ?? 0);
$totalGuests = $adults + $children + $pwd_senior;

// Calculate dates
$checkIn = new DateTime($reservation['check_in']);
$checkOut = new DateTime($reservation['check_out']);
$sameDay = $checkIn->format('Y-m-d') === $checkOut->format('Y-m-d');
$nights = $sameDay ? 1 : $checkIn->diff($checkOut)->days;

// Check if private room is booked
$isPrivateRoom = false;
if (isset($reservation['booked_rooms'])) {
    foreach ($reservation['booked_rooms'] as $room) {
        if ($room['room_id'] == 28) {
            $isPrivateRoom = true;
            break;
        }
    }
}

// Get stored amounts
$storedTotal = floatval($reservation['total_amount'] ?? 0);
$storedBasePrice = floatval($reservation['base_price'] ?? 0);
$storedExtrasPrice = floatval($reservation['extras_price'] ?? 0);
$storedEntranceFees = floatval($reservation['entrance_fees'] ?? 0);
$storedVatAmount = floatval($reservation['vat_amount'] ?? 0);

// Calculate room prices
$roomsTotal = 0;
$roomsBreakdown = [];

if (isset($reservation['booked_rooms']) && !empty($reservation['booked_rooms'])) {
    foreach ($reservation['booked_rooms'] as $room) {
        $quantity = intval($room['quantity']);
        $tourType = $room['tour_type'] ?? 'day_tour';
        
        if ($room['room_id'] == 28) {
            $roomPrice = calculatePrivateRoomPrice($tourType, $totalGuests, $nights);
        } else {
            $basePrice = $sameDay ? floatval($room['day_tour_price'] ?? 0) : floatval($room['night_tour_price'] ?? 0);
            $roomPrice = $sameDay ? $basePrice : $basePrice * $nights;
        }
        
        $roomSubtotal = $roomPrice * $quantity;
        $roomsTotal += $roomSubtotal;
        
        $roomsBreakdown[] = [
            'name' => $room['room_name'],
            'quantity' => $quantity,
            'price' => $roomPrice,
            'subtotal' => $roomSubtotal,
            'tour_type' => $tourType
        ];
    }
}

// Use stored room total if available and valid
if ($storedBasePrice > 0) {
    $roomsTotal = $storedBasePrice;
}

// Determine tour type for entrance fee calculation
$displayIsDayTour = $sameDay;
if (!$displayIsDayTour && isset($reservation['booked_rooms'])) {
    foreach ($reservation['booked_rooms'] as $room) {
        if (isset($room['tour_type']) && $room['tour_type'] === 'day_tour') {
            $displayIsDayTour = true;
            break;
        }
    }
}

// Set fee rates based on tour type
$adultFeeRate = $displayIsDayTour ? 200 : 250;
$childFeeRate = $displayIsDayTour ? 150 : 200;
$pwdSeniorFeeRate = $displayIsDayTour ? 160 : 200;

// Calculate entrance fees - EXACTLY like booking_form.php
$entranceFees = 0;
if (!$isPrivateRoom) {
    // Regular booking: charge all guests PER NIGHT
    $entranceFees += ($adults * $adultFeeRate) * $nights;
    $entranceFees += ($children * $childFeeRate) * $nights;
    $entranceFees += ($pwd_senior * $pwdSeniorFeeRate) * $nights;
} else {
    // Private room: FREE for first 30 guests, charge excess
    if ($totalGuests > 30) {
        $excessGuests = $totalGuests - 30;
        $tourType = '';
        
        if (isset($reservation['booked_rooms'])) {
            foreach ($reservation['booked_rooms'] as $room) {
                if ($room['room_id'] == 28) {
                    $tourType = $room['tour_type'] ?? 'day_tour';
                    break;
                }
            }
        }
        
        // Charge per excess guest based on tour type
        $excessFeePerGuest = 0;
        if ($tourType === 'day_tour') {
            $excessFeePerGuest = 400;
        } elseif ($tourType === 'overnight_pm') {
            $excessFeePerGuest = 500;
        } elseif ($tourType === 'whole_day' || $tourType === 'overnight_am') {
            $excessFeePerGuest = 600;
        }
        
        // For day tours, don't multiply by nights; for overnight, multiply by nights
        if ($tourType === 'day_tour') {
            $entranceFees = $excessGuests * $excessFeePerGuest;
        } else {
            $entranceFees = ($excessGuests * $excessFeePerGuest) * $nights;
        }
    }
}

// Get extras fee - second house
$extrasFee = floatval($storedExtrasPrice ?? 0);

// Check if second house was added
$has30PlusGuests = ($isPrivateRoom && $totalGuests >= 30);
if (isset($reservation['add_second_house']) && $reservation['add_second_house'] && !$has30PlusGuests) {
    if ($extrasFee == 0) {
        $extrasFee = $sameDay ? 5000 : 5000 * $nights;
    }
}

// Additional fee
$additionalFee = floatval($reservation['additional_fee'] ?? 0);

// Calculate total with VAT already included - EXACTLY like booking_form.php
$totalWithVAT = $roomsTotal + $entranceFees + $extrasFee + $additionalFee;

// Extract VAT that's already included - VAT = Total × (12/112)
$vatAmount = round($totalWithVAT * (12/112), 2);

// Calculate subtotal by removing VAT
$subtotalBeforeVAT = $totalWithVAT - $vatAmount;

// Total amount (VAT included)
$totalAmount = $totalWithVAT;

// Calculate balance due
$amountPaid = floatval($reservation['amount_paid'] ?? 0);
$balanceDue = $totalAmount - $amountPaid;

function calculatePrivateRoomPrice($tourType, $totalGuests, $nights = 1) {
    $basePrice = 0;
    $additionalPerHead = 0;
    $additionalGuestCount = 0;
    
    if ($tourType === 'day_tour') {
        if ($totalGuests <= 10) $basePrice = 8000;
        elseif ($totalGuests <= 15) $basePrice = 9000;
        elseif ($totalGuests <= 20) $basePrice = 10000;
        elseif ($totalGuests <= 25) $basePrice = 11000;
        elseif ($totalGuests <= 30) $basePrice = 12000;
        else {
            $basePrice = 12000;
            $additionalGuestCount = $totalGuests - 30;
            $additionalPerHead = 400;
        }
    } elseif ($tourType === 'overnight_pm') {
        if ($totalGuests <= 10) $basePrice = 9000;
        elseif ($totalGuests <= 15) $basePrice = 10000;
        elseif ($totalGuests <= 20) $basePrice = 11000;
        elseif ($totalGuests <= 25) $basePrice = 12000;
        elseif ($totalGuests <= 30) $basePrice = 13000;
        else {
            $basePrice = 13000;
            $additionalGuestCount = $totalGuests - 30;
            $additionalPerHead = 500;
        }
    } elseif ($tourType === 'whole_day' || $tourType === 'overnight_am') {
        if ($totalGuests <= 10) $basePrice = 12000;
        elseif ($totalGuests <= 15) $basePrice = 13000;
        elseif ($totalGuests <= 20) $basePrice = 15000;
        elseif ($totalGuests <= 25) $basePrice = 16000;
        elseif ($totalGuests <= 30) $basePrice = 18000;
        else {
            $basePrice = 18000;
            $additionalGuestCount = $totalGuests - 30;
            $additionalPerHead = 600;
        }
    }
    
    $totalRoomPrice = $basePrice + ($additionalGuestCount * $additionalPerHead);
    if ($tourType !== 'day_tour') {
        $totalRoomPrice *= $nights;
    }
    return $totalRoomPrice;
}

function formatTourType($tourType) {
    $tourTypeMap = [
        'day_tour' => 'Day Tour (8AM-5PM)',
        'overnight_am' => 'Overnight AM (9AM-7AM)',
        'overnight_pm' => 'Overnight PM (6PM-4PM)',
        'whole_day' => 'Whole Day (8PM-6PM)',
        'overnight_special' => 'Overnight (2PM-12NN)'
    ];
    return $tourTypeMap[$tourType] ?? $tourType;
}

function getReservationTypeDisplay($reservation) {
    return ($reservation['reservation_type'] === 'private') ? 'Private Booking' : 'Public Booking';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Saved - Rainbow Forest Paradise</title>
    <link rel="stylesheet" href="styles/savedbilling.css">
    <style>
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
        
        .private-room-notice {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
            color: #155724;
        }
        
        .private-room-notice h4 {
            margin-top: 0;
            color: #155724;
        }
        
        .entrance-fee-notice {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .entrance-fee-notice h4 {
            margin-top: 0;
        }
        
        .entrance-fee-notice ul {
            margin: 10px 0 0 20px;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="saved-container">
        <div class="logo-container">
            <img src="images/logo.png" alt="Rainbow Forest Paradise Logo" onerror="this.style.display='none'">
        </div>
        <h1>Reservation Saved Successfully</h1>
        <?php if ($message): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="reservation-code">
            Reservation Code: <?php echo htmlspecialchars($reservation['reservation_code']); ?>
        </div>
        
        <div class="reservation-details">
            <h3>Reservation Summary</h3>
            <p><strong>Booking Type:</strong> <?php echo htmlspecialchars(getReservationTypeDisplay($reservation)); ?></p>
            <p><strong>Name:</strong> <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($reservation['email']); ?></p>
            <p><strong>Phone:</strong> <?php echo htmlspecialchars($reservation['contact_number']); ?></p>
            <p><strong>Check-in:</strong> <?php echo htmlspecialchars(date('F j, Y', strtotime($reservation['check_in']))); ?></p>
            <p><strong>Check-out:</strong> <?php echo htmlspecialchars(date('F j, Y', strtotime($reservation['check_out']))); ?></p>
            <p><strong>Duration:</strong> <?php echo $sameDay ? 'Day Visit' : $nights . ' night' . ($nights > 1 ? 's' : ''); ?></p>
            <p><strong>Adults:</strong> <?php echo htmlspecialchars($adults); ?></p>
            <p><strong>Children:</strong> <?php echo htmlspecialchars($children); ?></p>
            <?php if ($pwd_senior > 0): ?>
            <p><strong>PWD/Senior:</strong> <?php echo htmlspecialchars($pwd_senior); ?></p>
            <?php endif; ?>
            <p><strong>Total Guests:</strong> <?php echo htmlspecialchars($totalGuests); ?></p>
            <?php if (!empty($reservation['tour_type'])): ?>
            <p><strong>Tour Type:</strong> <?php echo htmlspecialchars($reservation['tour_type']); ?></p>
            <?php endif; ?>
            <?php if (!empty($reservation['special_requests'])): ?>
            <p><strong>Special Requests:</strong> <?php echo htmlspecialchars($reservation['special_requests']); ?></p>
            <?php endif; ?>
        </div>
        
        <?php if (!empty($roomsBreakdown)): ?>
        <div class="room-details">
            <h4>Booked Rooms</h4>
            <?php foreach ($roomsBreakdown as $room): ?>
            <div class="room-item">
                <strong><?php echo htmlspecialchars($room['name']); ?></strong><br>
                <small>Tour Type: <?php echo htmlspecialchars(formatTourType($room['tour_type'])); ?></small><br>
                Quantity: <?php echo $room['quantity']; ?> × ₱<?php echo number_format($room['price'], 2); ?> 
                <?php if (!$sameDay && $nights > 1): ?>
                    (<?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>)
                <?php endif; ?>
                = ₱<?php echo number_format($room['subtotal'], 2); ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <div class="fees-section">
            <h4>Fees Breakdown</h4>
            <div class="fees-grid">
                <?php if ($roomsTotal > 0): ?>
                <div>Room Total:</div>
                <div>₱<?php echo number_format($roomsTotal, 2); ?></div>
                <?php endif; ?>
                
                <div style="grid-column: 1 / -1;">
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 10px;">
                        <div>Entrance Fees:</div>
                        <div><?php echo $isPrivateRoom && $totalGuests <= 30 ? '<span style="color: #28a745; font-weight: bold;">FREE (Private Room)</span>' : '₱' . number_format($entranceFees, 2); ?></div>
                    </div>
                    
                   <?php if ($isPrivateRoom): ?>
                    <?php if ($totalGuests > 30): ?>
                        <?php 
                        $excessGuests = $totalGuests - 30;
                        $excessFeeRate = 0;
                        $tourTypeForFee = '';
                        
                        if (isset($reservation['booked_rooms'])) {
                            foreach ($reservation['booked_rooms'] as $room) {
                                if ($room['room_id'] == 28) {
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
                                (<?php echo $excessGuests; ?> × ₱<?php echo $excessFeeRate; ?>
                                <?php if ($tourTypeForFee !== 'day_tour'): ?>
                                    × <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>
                                <?php endif; ?>):
                            </div>
                            <div>₱<?php echo number_format($entranceFees, 2); ?></div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <?php if ($adults > 0): ?>
                    <div class="entrance-breakdown">
                        <div>• Adults (<?php echo $adults; ?> × ₱<?php echo $adultFeeRate; ?> × <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>):</div>
                        <div>₱<?php echo number_format($adults * $adultFeeRate * $nights, 2); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($children > 0): ?>
                    <div class="entrance-breakdown">
                        <div>• Children (<?php echo $children; ?> × ₱<?php echo $childFeeRate; ?> × <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>):</div>
                        <div>₱<?php echo number_format($children * $childFeeRate * $nights, 2); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($pwd_senior > 0): ?>
                    <div class="entrance-breakdown">
                        <div>• PWD/Senior (<?php echo $pwd_senior; ?> × ₱<?php echo $pwdSeniorFeeRate; ?> × <?php echo $nights; ?> night<?php echo $nights > 1 ? 's' : ''; ?>):</div>
                        <div>₱<?php echo number_format($pwd_senior * $pwdSeniorFeeRate * $nights, 2); ?></div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                </div>
                
                <?php if ($extrasFee > 0): ?>
                <div>Extra Tent/Second House:</div>
                <div>₱<?php echo number_format($extrasFee, 2); ?></div>
                <?php endif; ?>
                
                <?php if ($additionalFee > 0): ?>
                <div>Additional Items:</div>
                <div>₱<?php echo number_format($additionalFee, 2); ?></div>
                <?php endif; ?>
                
                <div style="font-weight: 500; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">Subtotal (before VAT):</div>
                <div style="font-weight: 500; border-top: 1px solid #ddd; padding-top: 10px; margin-top: 10px;">₱<?php echo number_format($subtotalBeforeVAT, 2); ?></div>
                
                <div>VAT (12% included):</div>
                <div>₱<?php echo number_format($vatAmount, 2); ?></div>
                
                <div class="total-row">Total Amount (VAT included):</div>
                <div class="total-row">₱<?php echo number_format($totalAmount, 2); ?></div>
            </div>
        </div>
        
        <?php if ($isPrivateRoom): ?>
        <div class="private-room-notice">
            <h4>🎉 Private Room Benefit</h4>
            <p><strong>Entrance fees are FREE for the first 30 guests!</strong></p>
            <?php if ($totalGuests > 30): ?>
            <p style="margin-top: 10px;">
                You have <?php echo ($totalGuests - 30); ?> guest(s) above 30. 
                Standard entrance fees apply for these additional guests.
            </p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!$isPrivateRoom && ($pwd_senior > 0)): ?>
        <div class="entrance-fee-notice">
            <h4>Important Note: Bring the following to avail discount</h4>
            <ul>
                <li>PWD ID</li>
                <li>PWD Notebook</li>
                <li>Senior ID</li>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="reservation-details">
            <h4>Payment Information</h4>
            <p><strong>Reservation Status:</strong> 
                <span class="status-badge status-<?php echo strtolower($reservation['status'] ?? 'pending'); ?>">
                    <?php echo htmlspecialchars($reservation['status'] ?? 'Pending'); ?>
                </span>
            </p>
            <p><strong>Payment Status:</strong> 
                <span class="status-badge status-<?php echo strtolower($reservation['payment_status'] ?? 'unpaid'); ?>">
                    <?php echo htmlspecialchars($reservation['payment_status'] ?? 'Unpaid'); ?>
                </span>
            </p>
            <p><strong>Amount Paid:</strong> ₱<?php echo number_format($amountPaid, 2); ?></p>
            <p><strong>Balance Due:</strong> ₱<?php echo number_format($balanceDue, 2); ?></p>
            <p><strong>Required Downpayment (40%):</strong> ₱<?php echo number_format($totalAmount * 0.4, 2); ?></p>
        </div>
        
        <div class="expiration-info">
            <p>Your reservation will expire if payment is not completed:</p>
            <div class="countdown-timer" id="countdown">Loading...</div>
            <p>Please use your reservation code when completing your payment later.</p>
        </div>
        
        <div class="continue-booking-section status-<?php echo strtolower($reservation['status'] ?? 'saved'); ?>">
            <h3>Ready to Complete Your Booking?</h3>
            <p>Continue to payment and finalize your reservation. All your details have been saved.</p>
            <a href="booking_form.php?reservation_code=<?php echo urlencode($code); ?>&continue=true" 
               class="continue-btn">
                Continue to Payment →
            </a>
        </div>
        
        <div style="margin-top: 30px; text-align: center;">
            <a href="index.php" class="btn btn-home">Go to Home</a>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const reservationData = {
                adults: <?php echo $adults; ?>,
                children: <?php echo $children; ?>,
                pwd_senior: <?php echo $pwd_senior; ?>,
                reservation_code: "<?php echo htmlspecialchars($code); ?>",
                check_in: "<?php echo $reservation['check_in']; ?>",
                check_out: "<?php echo $reservation['check_out']; ?>",
                total_amount: <?php echo $totalAmount; ?>,
                entrance_fees: <?php echo $entranceFees; ?>,
                rooms_total: <?php echo $roomsTotal; ?>,
                extras_fee: <?php echo $extrasFee; ?>,
                is_private_room: <?php echo $isPrivateRoom ? 'true' : 'false'; ?>
            };
            
            sessionStorage.setItem('saved_reservation_data', JSON.stringify(reservationData));
            
            const continueBtn = document.querySelector('.continue-btn');
            if (continueBtn) {
                const currentHref = continueBtn.href;
                const separator = currentHref.includes('?') ? '&' : '?';
                continueBtn.href = currentHref + separator + 
                    'adults=' + <?php echo $adults; ?> + 
                    '&children=' + <?php echo $children; ?> + 
                    '&pwd_senior=' + <?php echo $pwd_senior; ?>;
            }
            
            const reservationCode = "<?php echo htmlspecialchars($reservation['reservation_code']); ?>";
            const localStorageKey = `reservation_expiration_${reservationCode}`;
            let expirationTime = <?php echo isset($reservation['expires_at']) ? $reservation['expires_at'] * 1000 : 0; ?>;
            
            if (expirationTime === 0) {
                expirationTime = Date.now() + (3 * 60 * 60 * 1000);
            }
            
            localStorage.setItem(localStorageKey, expirationTime.toString());
            
            function updateCountdown() {
                const now = new Date().getTime();
                const distance = expirationTime - now;
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                document.getElementById("countdown").innerHTML = 
                    (hours < 10 ? "0" + hours : hours) + ":" +
                    (minutes < 10 ? "0" + minutes : minutes) + ":" +
                    (seconds < 10 ? "0" + seconds : seconds);
                
                if (distance < 0) {
                    clearInterval(x);
                    document.getElementById("countdown").innerHTML = "EXPIRED";
                    localStorage.removeItem(localStorageKey);
                    setTimeout(function() {
                        alert('Your reservation has expired. Please create a new booking.');
                        window.location.href = "booking_form.php";
                    }, 2000);
                }
            }
            
            updateCountdown();
            const x = setInterval(updateCountdown, 1000);
        });
    </script>
</body>
</html>