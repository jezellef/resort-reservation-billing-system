<?php
session_start();

$mysqli = require 'database.php';

// Properly handle null user_id
$user_id = $_SESSION['user_id'] ?? null;
$reservation_type = $user_id ? 'user' : 'guest';

// Basic reservation details
$check_in = $_POST['check_in'];
$check_out = $_POST['check_out'];
$first_name = trim($_POST['first_name']);
$last_name = trim($_POST['last_name']);
$email = trim($_POST['email']);
$contact_number = trim($_POST['contact_number']);
$adult_count = intval($_POST['adult_count']);
$kid_count = intval($_POST['kid_count'] ?? 0);
$tour_type = $_POST['tour_type'];
$special_requests = $_POST['special_requests'] ?? '';

// Process extras and add-ons
// Handle extra tent - required for groups over 50
$total_guests = $adult_count + $kid_count;
$extra_tent = isset($_POST['extra_tent']) ? 1 : ($total_guests > 50 ? 1 : 0);
$tent_amount = $extra_tent ? 800.00 : 0.00;

// Handle corkage fee
$corkage_quantity = isset($_POST['corkage_fee']) ? intval($_POST['corkage_quantity'] ?? 1) : 0;
$corkage_fee_amount = 100.00;
$total_corkage_fee = $corkage_quantity * $corkage_fee_amount;

// Handle pet fee
$pet_quantity = isset($_POST['pet_fee']) ? intval($_POST['pet_quantity'] ?? 1) : 0;
$pet_fee_amount = 200.00;
$total_pet_fee = $pet_quantity * $pet_fee_amount;

// Process time information
$raw_time = $_POST['time'] ?? '';
if (empty($raw_time)) {
    if ($tour_type == 'day_tour') {
        $time = '9:00 AM to 6:00 PM';
    } elseif ($tour_type == 'night_tour') {
        $time = '8:00 PM to 7:00 AM (next day)';
    } elseif ($tour_type == 'whole_day_morning' || $tour_type == 'whole_day') {
        $time = '9:00 AM to 7:00 AM (next day)';
    } elseif ($tour_type == 'whole_day_night') {
        $time = '8:00 PM to 6:00 PM (next day)';
    } else {
        $time = '9:00 AM to 6:00 PM';
    }
} else {
    // Handle different time formats
    if (strpos($raw_time, ' to ') !== false) {
        $time = $raw_time;
    } elseif ($raw_time === '09:00:00') {
        $time = $tour_type === 'day_tour' ? '9:00 AM to 6:00 PM' : '9:00 AM to 7:00 AM (next day)';
    } elseif ($raw_time === '20:00:00') {
        $time = $tour_type === 'night_tour' ? '8:00 PM to 7:00 AM (next day)' : '8:00 PM to 6:00 PM (next day)';
    } else {
        $time = $raw_time;
    }
}

// Generate unique reservation code
$reservation_code = 'P1' . time() . rand(1000, 9999);
$expires_at = time() + 86400; // 24 hours expiration

// Pricing data structure matches the one in the form
$pricing_data = [
    'whole_day' => [
        'brackets' => [
            ['max' => 10, 'price' => 12000],
            ['max' => 15, 'price' => 13000],
            ['max' => 20, 'price' => 15000],
            ['max' => 25, 'price' => 16000],
            ['max' => 30, 'price' => 18000],
        ],
        'additional_per_person' => 600
    ],
    'day_tour' => [
        'brackets' => [
            ['max' => 10, 'price' => 7000],
            ['max' => 15, 'price' => 8000],
            ['max' => 20, 'price' => 9000],
            ['max' => 25, 'price' => 10000],
            ['max' => 30, 'price' => 11000],
        ],
        'additional_per_person' => 400
    ],
    'night_tour' => [
        'brackets' => [
            ['max' => 10, 'price' => 8000],
            ['max' => 15, 'price' => 9000],
            ['max' => 20, 'price' => 10000],
            ['max' => 25, 'price' => 11000],
            ['max' => 30, 'price' => 12000],
        ],
        'additional_per_person' => 500
    ]
];
// Set same pricing for whole day variants
$pricing_data['whole_day_morning'] = $pricing_data['whole_day'];
$pricing_data['whole_day_night'] = $pricing_data['whole_day'];

// Function to calculate the reservation price
function calculatePrice($type, $guests, $pricing, $tent, $tentCost, $corkQty, $corkCost, $petQty, $petCost) {
    $base = 0;
    // Find appropriate bracket for guest count
    foreach ($pricing[$type]['brackets'] as $bracket) {
        if ($guests <= $bracket['max']) {
            $base = $bracket['price'];
            break;
        }
    }
    // For groups larger than highest bracket
    if ($base === 0) {
        $base = end($pricing[$type]['brackets'])['price'];
        $base += ($guests - 30) * $pricing[$type]['additional_per_person'];
    }
    // Calculate additional fees
    $extras = ($tent ? $tentCost : 0) + ($corkQty * $corkCost) + ($petQty * $petCost);
    return [$base, $extras, $base + $extras];
}

// Calculate the pricing
$total_guests = $adult_count + $kid_count;
[$base_price, $extras_price, $total_price] = calculatePrice(
    $tour_type, 
    $total_guests, 
    $pricing_data, 
    $extra_tent, 
    $tent_amount, 
    $corkage_quantity, 
    $corkage_fee_amount, 
    $pet_quantity, 
    $pet_fee_amount
);

// Set tour type flags for the database
$day_tour = $whole_day_morning_tour = $whole_day_night_tour = $night_tour = 0;
switch($tour_type) {
    case 'day_tour': 
        $day_tour = 1; 
        break;
    case 'night_tour': 
        $night_tour = 1; 
        break;
    case 'whole_day_morning': 
        $whole_day_morning_tour = 1; 
        break;
    case 'whole_day_night': 
        $whole_day_night_tour = 1; 
        break;
    case 'whole_day':
        // Determine which whole day type based on start time
        if ($raw_time === '20:00:00' || strpos($time, '8:00 PM') !== false) {
            $whole_day_night_tour = 1;
        } else {
            $whole_day_morning_tour = 1;
        }
        break;
}

// Set initial statuses
$status = 'Pending';
$payment_status = 'Pending';

// Handle NULL user_id properly
if ($user_id === null) {
    // If no user is logged in, use a different prepared statement where user_id can be NULL
    $stmt = $mysqli->prepare("INSERT INTO p1_reservation (
        reservation_type,
        reservation_code,
        check_in,
        check_out,
        time,
        first_name,
        last_name,
        email,
        contact_number,
        adult_count,
        kid_count,
        special_requests,
        extra_tent,
        corkage_quantity,
        corkage_fee_amount,
        total_corkage_fee,
        pet_quantity,
        pet_fee_amount,
        total_pet_fee,
        base_price,
        extras_price,
        total_price,
        expires_at,
        status,
        day_tour,
        whole_day_morning_tour,
        whole_day_night_tour,
        night_tour,
        payment_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        die("Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param(
        "sssssssssiisiiidiiddddisiiiis",
        $reservation_type,
        $reservation_code,
        $check_in,
        $check_out,
        $time,
        $first_name,
        $last_name,
        $email,
        $contact_number,
        $adult_count,
        $kid_count,
        $special_requests,
        $extra_tent,
        $corkage_quantity,
        $corkage_fee_amount,
        $total_corkage_fee,
        $pet_quantity,
        $pet_fee_amount,
        $total_pet_fee,
        $base_price,
        $extras_price,
        $total_price,
        $expires_at,
        $status,
        $day_tour,
        $whole_day_morning_tour,
        $whole_day_night_tour,
        $night_tour,
        $payment_status
    );
} else {
    // If user is logged in, use the original prepared statement with user_id
    $stmt = $mysqli->prepare("INSERT INTO p1_reservation (
        reservation_type,
        user_id,
        reservation_code,
        check_in,
        check_out,
        time,
        first_name,
        last_name,
        email,
        contact_number,
        adult_count,
        kid_count,
        special_requests,
        extra_tent,
        corkage_quantity,
        corkage_fee_amount,
        total_corkage_fee,
        pet_quantity,
        pet_fee_amount,
        total_pet_fee,
        base_price,
        extras_price,
        total_price,
        expires_at,
        status,
        day_tour,
        whole_day_morning_tour,
        whole_day_night_tour,
        night_tour,
        payment_status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if (!$stmt) {
        die("Prepare failed: " . $mysqli->error);
    }

    $stmt->bind_param(
        "sissssssssiisiiidiiddddisiiiis",
        $reservation_type,
        $user_id,
        $reservation_code,
        $check_in,
        $check_out,
        $time,
        $first_name,
        $last_name,
        $email,
        $contact_number,
        $adult_count,
        $kid_count,
        $special_requests,
        $extra_tent,
        $corkage_quantity,
        $corkage_fee_amount,
        $total_corkage_fee,
        $pet_quantity,
        $pet_fee_amount,
        $total_pet_fee,
        $base_price,
        $extras_price,
        $total_price,
        $expires_at,
        $status,
        $day_tour,
        $whole_day_morning_tour,
        $whole_day_night_tour,
        $night_tour,
        $payment_status
    );
}

// Execute the query and handle the result
if ($stmt->execute()) {
    // Save reservation details in session for billing page
    $_SESSION['reservation_code'] = $reservation_code;
    $_SESSION['reservation_total'] = $total_price;
    $_SESSION['reservation_extras'] = $extras_price;
    $_SESSION['reservation_base'] = $base_price;
    $_SESSION['reservation_type'] = $tour_type;
    $_SESSION['reservation_date'] = $check_in;
    $_SESSION['reservation_guests'] = $total_guests;
    
    // Clean up session variables to prevent resubmission
    unset($_SESSION['check_in'], $_SESSION['check_out'], $_SESSION['tour_type'], $_SESSION['time']);
    $_SESSION['reservation_success'] = true;
    header("Location: billing.php");
    exit;
} else {
    $_SESSION['reservation_success'] = false;
    $_SESSION['error_message'] = $stmt->error;
    header("Location: error.php");
    exit;
}
?>