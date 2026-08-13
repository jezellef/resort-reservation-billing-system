<?php
session_start();

// Temporary debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
require_once 'database.php';

// Validation and Sanitization Functions
function sanitizeInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}

function validateDate($date) {
    return (bool)strtotime($date);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validatePhoneNumber($phone) {
    // Basic phone number validation (adjust regex as needed)
    return preg_match('/^[0-9\-\(\)\/\+\s]{10,15}$/', $phone);
}

// Pricing Configuration
$PRICING_DATA = [
    'whole_day_morning' => [
        'brackets' => [
            ['max' => 10, 'price' => 12000],
            ['max' => 15, 'price' => 13000],
            ['max' => 20, 'price' => 15000],
            ['max' => 25, 'price' => 16000],
            ['max' => 30, 'price' => 18000]
        ],
        'additional_per_person' => 600,
        'displayName' => "Whole Day (Morning Start)",
        'timeFormat' => "9:00 AM to 7:00 AM (next day)"
    ],
    'whole_day_night' => [
        'brackets' => [
            ['max' => 10, 'price' => 12000],
            ['max' => 15, 'price' => 13000],
            ['max' => 20, 'price' => 15000],
            ['max' => 25, 'price' => 16000],
            ['max' => 30, 'price' => 18000]
        ],
        'additional_per_person' => 600,
        'displayName' => "Whole Day (Night Start)",
        'timeFormat' => "8:00 PM to 6:00 PM (next day)"
    ],
    'day_tour' => [
        'brackets' => [
            ['max' => 10, 'price' => 7000],
            ['max' => 15, 'price' => 8000],
            ['max' => 20, 'price' => 9000],
            ['max' => 25, 'price' => 10000],
            ['max' => 30, 'price' => 11000]
        ],
        'additional_per_person' => 400,
        'displayName' => "Day Tour",
        'timeFormat' => "9:00 AM to 6:00 PM"
    ],
    'night_tour' => [
        'brackets' => [
            ['max' => 10, 'price' => 8000],
            ['max' => 15, 'price' => 9000],
            ['max' => 20, 'price' => 10000],
            ['max' => 25, 'price' => 11000],
            ['max' => 30, 'price' => 12000]
        ],
        'additional_per_person' => 500,
        'displayName' => "Night Tour",
        'timeFormat' => "8:00 PM to 7:00 AM (next day)"
    ]
];

// Extra item pricing - UPDATED with fixed values
$EXTRAS_PRICES = [
    'extra_tent' => 800,     // Extra tent fee
    'corkage' => 100,        // Per bottle corkage fee - Fixed at 100
    'pet' => 200             // Per dog pet fee - Fixed at 200
];

// New Reservation Code Generation
function generateReservationCode() {
    $reservation_code = 'P1G' . time() . rand(1000, 9999);
    return $reservation_code;
}

// Pricing Calculation - FIXED to handle zero quantities correctly
function calculateTotalPrice($tour_type, $total_guests, $extras, $pricing_data, $extras_prices) {
    $pricing = $pricing_data[$tour_type];
    $base_price = 0;
    
    // Calculate base price
    foreach ($pricing['brackets'] as $bracket) {
        if ($total_guests <= $bracket['max']) {
            $base_price = $bracket['price'];
            break;
        }
    }
    
    // Handle guests beyond 30
    if ($base_price === 0) {
        $last_bracket = end($pricing['brackets']);
        $extra_guests = $total_guests - 30;
        $base_price = $last_bracket['price'] + ($extra_guests * $pricing['additional_per_person']);
    }
    
    // Calculate extras prices - FIXED to avoid treating empty or zero as 1
    $extra_tent_cost = (!empty($extras['extra_tent']) && $extras['extra_tent'] == 1) ? $extras_prices['extra_tent'] : 0;
    
    // Make sure corkage_quantity is explicitly checked for numeric value > 0
    $corkage_quantity = isset($extras['corkage_quantity']) && is_numeric($extras['corkage_quantity']) ? intval($extras['corkage_quantity']) : 0;
    $total_corkage_fee = ($corkage_quantity > 0) ? ($extras_prices['corkage'] * $corkage_quantity) : 0;
    
    // Make sure pet_quantity is explicitly checked for numeric value > 0
    $pet_quantity = isset($extras['pet_quantity']) && is_numeric($extras['pet_quantity']) ? intval($extras['pet_quantity']) : 0;
    $total_pet_fee = ($pet_quantity > 0) ? ($extras_prices['pet'] * $pet_quantity) : 0;
    
    // Sum up all extras
    $extras_price = $extra_tent_cost + $total_corkage_fee + $total_pet_fee;
    
    return [
        'base_price' => $base_price,
        'extras_price' => $extras_price,
        'total_price' => $base_price + $extras_price,
        'extra_tent_cost' => $extra_tent_cost,
        'total_corkage_fee' => $total_corkage_fee,
        'total_pet_fee' => $total_pet_fee
    ];
}

// Validate Input - UPDATED to reflect database changes
function validateInput($input, $check_in, $check_out, $email, $contact_number) {
    $errors = [];

    // Validate required fields
    $required_fields = ['first_name', 'last_name', 'email', 'contact_number', 'check_in', 'check_out', 'adult_count'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " is required.";
        }
    }

    // Validate that at least one tour type is selected
    if (empty($input['day_tour']) && empty($input['whole_day_morning_tour']) && 
        empty($input['whole_day_night_tour']) && empty($input['night_tour'])) {
        $errors[] = "Please select a tour type.";
    }

    // Validate dates
    if (!validateDate($check_in)) {
        $errors[] = "Invalid check-in date.";
    }
    if (!validateDate($check_out)) {
        $errors[] = "Invalid check-out date.";
    }
    if (strtotime($check_out) < strtotime($check_in)) {
        $errors[] = "Check-out date must be after check-in date.";
    }

    // Validate email
    if (!validateEmail($email)) {
        $errors[] = "Invalid email address.";
    }

    // Validate phone number
    if (!validatePhoneNumber($contact_number)) {
        $errors[] = "Invalid contact number.";
    }

    // Validate numeric inputs
    $numeric_fields = ['adult_count', 'kid_count'];
    foreach ($numeric_fields as $field) {
        // Set default value of 0 for kid_count if not set
        if ($field === 'kid_count' && !isset($input[$field])) {
            $input[$field] = 0;
        }
        
        $value = $input[$field] ?? 0;
        if (!is_numeric($value) || $value < 0) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . " must be a non-negative number.";
        }
    }

    // Validate total guest count
    $total_guests = (intval($input['adult_count'] ?? 0) + intval($input['kid_count'] ?? 0));
    if ($total_guests > 100) {
        $errors[] = "Total number of guests cannot exceed 100.";
    }

    // Validate extra tent requirement for large groups
    if ($total_guests > 50 && empty($input['extra_tent'])) {
        $errors[] = "An extra tent is required for groups larger than 50 people.";
    }

    // Validate corkage quantity if provided
    if (!empty($input['corkage_quantity'])) {
        if (!is_numeric($input['corkage_quantity']) || $input['corkage_quantity'] < 0) {
            $errors[] = "Please specify a valid number of bottles for corkage fee.";
        }
    }
    
    // Validate pet quantity if provided
    if (!empty($input['pet_quantity'])) {
        if (!is_numeric($input['pet_quantity']) || $input['pet_quantity'] < 0) {
            $errors[] = "Please specify a valid number of dogs for pet fee.";
        }
    }

    return $errors;
}

// Get the actual tour type based on form fields
function determineTourType($input) {
    if (!empty($input['day_tour'])) {
        return 'day_tour';
    } elseif (!empty($input['whole_day_morning_tour'])) {
        return 'whole_day_morning';
    } elseif (!empty($input['whole_day_night_tour'])) {
        return 'whole_day_night';
    } elseif (!empty($input['night_tour'])) {
        return 'night_tour';
    } else {
        // Default to day tour if somehow nothing was selected
        return 'day_tour';
    }
}

// IMPROVED time handling function to store consistent format
function processTimeFormat($tour_type, $raw_time = '') {
    // Define standard time formats for each tour type
    $standard_times = [
        'day_tour' => '9:00 AM to 6:00 PM',
        'night_tour' => '8:00 PM to 7:00 AM (next day)',
        'whole_day_morning' => '9:00 AM to 7:00 AM (next day)',
        'whole_day_night' => '8:00 PM to 6:00 PM (next day)'
    ];
    
    // Always use the standard time format based on tour type
    // This ensures consistency in the database
    return $standard_times[$tour_type] ?? '9:00 AM to 6:00 PM'; // Default fallback
}

// Main Processing Logic
try {
    // Ensure we have the mysqli connection from database.php
    global $mysqli;
    
    // Check if mysqli is valid
    if (!isset($mysqli) || $mysqli->connect_error) {
        throw new Exception("Database connection failed. Please try again later.");
    }
    
    // Sanitize and validate input
    $sanitized_input = array_map('sanitizeInput', $_POST);
    
    // Set default value for kid_count if not provided
    if (!isset($sanitized_input['kid_count'])) {
        $sanitized_input['kid_count'] = 0;
    }
    
    // Convert the tour type checkboxes to integers (0 or 1)
    $sanitized_input['day_tour'] = isset($sanitized_input['day_tour']) && $sanitized_input['day_tour'] == '1' ? 1 : 0;
    $sanitized_input['whole_day_morning_tour'] = isset($sanitized_input['whole_day_morning_tour']) && $sanitized_input['whole_day_morning_tour'] == '1' ? 1 : 0;
    $sanitized_input['whole_day_night_tour'] = isset($sanitized_input['whole_day_night_tour']) && $sanitized_input['whole_day_night_tour'] == '1' ? 1 : 0;
    $sanitized_input['night_tour'] = isset($sanitized_input['night_tour']) && $sanitized_input['night_tour'] == '1' ? 1 : 0;
    
    // Process the extra tent checkbox
    $sanitized_input['extra_tent'] = isset($sanitized_input['extra_tent']) && $sanitized_input['extra_tent'] == '1' ? 1 : 0;
    
    // FIX: Better handling of quantities - ensure they're explicitly 0 if not set or not positive
    $sanitized_input['corkage_quantity'] = isset($sanitized_input['corkage_quantity']) && is_numeric($sanitized_input['corkage_quantity']) && intval($sanitized_input['corkage_quantity']) > 0 
        ? intval($sanitized_input['corkage_quantity']) 
        : 0;
    
    $sanitized_input['pet_quantity'] = isset($sanitized_input['pet_quantity']) && is_numeric($sanitized_input['pet_quantity']) && intval($sanitized_input['pet_quantity']) > 0 
        ? intval($sanitized_input['pet_quantity']) 
        : 0;
    
    // Determine the actual tour type for pricing calculations
    $actual_tour_type = determineTourType($sanitized_input);
    
    // FIX: Always use standard time format based on tour type
    $time = processTimeFormat($actual_tour_type);
    
    // Validate input
    $validation_errors = validateInput(
        $sanitized_input, 
        $sanitized_input['check_in'], 
        $sanitized_input['check_out'], 
        $sanitized_input['email'], 
        $sanitized_input['contact_number']
    );
    
    // If there are validation errors, throw an exception
    if (!empty($validation_errors)) {
        throw new Exception(implode("\n", $validation_errors));
    }

    // Prepare input data
    $check_in = $sanitized_input['check_in'];
    $check_out = $sanitized_input['check_out'];
    $first_name = $sanitized_input['first_name'];
    $last_name = $sanitized_input['last_name'];
    $email = $sanitized_input['email'];
    $contact_number = $sanitized_input['contact_number'];
    $adult_count = intval($sanitized_input['adult_count']);
    $kid_count = intval($sanitized_input['kid_count']);
    $day_tour = $sanitized_input['day_tour'];
    $whole_day_morning_tour = $sanitized_input['whole_day_morning_tour'];
    $whole_day_night_tour = $sanitized_input['whole_day_night_tour'];
    $night_tour = $sanitized_input['night_tour'];
    
    $special_requests = $sanitized_input['special_requests'] ?? '';

    // Handle extra options with explicit checks
    $extra_tent = $sanitized_input['extra_tent'] ? 1 : 0;
    $corkage_quantity = $sanitized_input['corkage_quantity']; // Already sanitized to be 0 if not valid
    $pet_quantity = $sanitized_input['pet_quantity']; // Already sanitized to be 0 if not valid

    // Calculate total guests
    $total_guests = $adult_count + $kid_count;
    
    // Prepare extras for pricing calculation
    $extras = [
        'extra_tent' => $extra_tent,
        'corkage_quantity' => $corkage_quantity,
        'pet_quantity' => $pet_quantity
    ];
    
    $pricing_calculation = calculateTotalPrice($actual_tour_type, $total_guests, $extras, $PRICING_DATA, $EXTRAS_PRICES);
    
    // Calculate specific fees using fixed amounts - now using values from pricing calculation
    $extra_tent_amount = $pricing_calculation['extra_tent_cost'];
    $total_corkage_fee = $pricing_calculation['total_corkage_fee'];
    $total_pet_fee = $pricing_calculation['total_pet_fee'];
    
    // Convert prices to strings to match decimal in database
    $base_price_str = number_format($pricing_calculation['base_price'], 2, '.', '');
    $extras_price_str = number_format($pricing_calculation['extras_price'], 2, '.', '');
    $total_price_str = number_format($pricing_calculation['total_price'], 2, '.', '');
    $tent_amount_str = number_format($extra_tent_amount, 2, '.', '');
    $total_corkage_fee_str = number_format($total_corkage_fee, 2, '.', '');
    $total_pet_fee_str = number_format($total_pet_fee, 2, '.', '');

    // Generate reservation code using the new format
    $reservation_code = generateReservationCode();
    
    // Set status to "pending" regardless of payment
    $status = "pending";

    // UPDATED SQL query to include status field
    $stmt = $mysqli->prepare("INSERT INTO guest_reservation (
        reservation_code, 
        check_in, 
        check_out, 
        first_name, 
        last_name, 
        email, 
        contact_number, 
        adult_count, 
        kid_count, 
        special_requests, 
        extra_tent,
        tent_amount,
        base_price, 
        extras_price, 
        total_price,
        time,
        day_tour,
        whole_day_morning_tour,
        whole_day_night_tour,
        night_tour,
        corkage_quantity,
        total_corkage_fee,
        pet_quantity,
        total_pet_fee,
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $mysqli->error);
    }
    
    // UPDATED type binding string - ensure time is bound as string ('s')
    $stmt->bind_param(
        "sssssssiiidsdssiiiididids", 
        $reservation_code,
        $check_in,
        $check_out,
        $first_name,
        $last_name,
        $email,
        $contact_number,
        $adult_count,
        $kid_count,
        $special_requests,
        $extra_tent,
        $tent_amount_str,
        $base_price_str,
        $extras_price_str,
        $total_price_str,
        $time,
        $day_tour,
        $whole_day_morning_tour,
        $whole_day_night_tour,
        $night_tour,
        $corkage_quantity,
        $total_corkage_fee_str,
        $pet_quantity,
        $total_pet_fee_str,
        $status
    );

    // Execute the statement
    if (!$stmt->execute()) {
        throw new Exception("Execution failed: " . $stmt->error);
    }

    // Prepare session data with all the fee information
    $_SESSION['reservation_details'] = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'email' => $email,
        'contact_number' => $contact_number,
        'check_in' => $check_in,
        'check_out' => $check_out,
        'time' => $time, // Store the standardized time format
        'adult_count' => $adult_count,
        'kid_count' => $kid_count,
        'tour_type' => $actual_tour_type,
        'day_tour' => $day_tour,
        'night_tour' => $night_tour,
        'whole_day_morning_tour' => $whole_day_morning_tour,
        'whole_day_night_tour' => $whole_day_night_tour,
        'total_price' => $pricing_calculation['total_price'],
        'base_price' => $pricing_calculation['base_price'],
        'extras_price' => $pricing_calculation['extras_price'],
        'reservation_code' => $reservation_code,
        'total_amount' => $pricing_calculation['total_price'],
        'payment_amount' => 0,
        'extras' => [
            'extra_tent' => $extra_tent,
            'extra_tent_amount' => $extra_tent_amount,
            'corkage_quantity' => $corkage_quantity,
            'corkage_fee_amount' => $total_corkage_fee,
            'pet_quantity' => $pet_quantity,
            'pet_fee_amount' => $total_pet_fee
        ],
        'extra_tent' => $extra_tent,
        'tent_amount' => $extra_tent_amount,
        'status' => $status
    ];    
    
    // Close statement
    $stmt->close();
    
    // Redirect to billing page
    header("Location: billing.php");
    exit;

} catch (Exception $e) {
    // Log the error with details
    error_log("Reservation Error: " . $e->getMessage());
    if (isset($mysqli) && $mysqli->error) {
        error_log("MySQL Error: " . $mysqli->error);
    }

    // Store error in session
    $_SESSION['error_message'] = $e->getMessage();
    if (isset($mysqli) && $mysqli->error) {
        $_SESSION['error_details'] = $mysqli->error;
    }

    // Redirect to error page
    header("Location: error.php");
    exit;
} finally {
    // We don't close the connection here if database.php manages it
}
?>