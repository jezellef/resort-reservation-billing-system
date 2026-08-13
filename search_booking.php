<?php
// Initialize session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to catch any unexpected output
ob_start();

// Enable error reporting for debugging but don't display errors in output
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Include database connection
require_once 'db_connect.php';

// Set header to return JSON
header('Content-Type: application/json');

// Check if database connection is working
if (!isset($conn) || mysqli_connect_errno()) {
    echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit;
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

// Get the reference number from the POST data
$reference = isset($_POST['reference']) ? trim($_POST['reference']) : '';

// Validate reference number
if (empty($reference)) {
    echo json_encode(['error' => 'Reference number is required']);
    exit;
}

// Log the reference number for debugging
error_log("Searching for exact reference: " . $reference);

// Array to store results
$results = [];

// Debug output
$debug = [];
$debug['reference'] = $reference;

// Use prepared statements for better security and exact matching
// First check guest reservations
$guestStmt = $conn->prepare("SELECT gr.*, p.status as payment_status, p.id as payment_id 
                             FROM guest_reservation gr
                             LEFT JOIN payments p ON gr.reservation_code = p.guest_reservation_code
                             WHERE gr.reservation_code = ?
                             ORDER BY gr.created_at ASC");  // Added ORDER BY to sort by creation date

if (!$guestStmt) {
    echo json_encode(['error' => 'Prepare statement failed (guest): ' . $conn->error]);
    exit;
}

$guestStmt->bind_param("s", $reference);
$guestStmt->execute();
$guestResult = $guestStmt->get_result();

if (!$guestResult) {
    echo json_encode(['error' => 'Database error (guest query): ' . $guestStmt->error]);
    exit;
} else {
    // Add explicit logging
    $guestCount = $guestResult->num_rows;
    error_log("Guest results count: " . $guestCount);
    $debug['guest_count'] = $guestCount;
    
    // Process guest reservations
    while ($reservation = $guestResult->fetch_assoc()) {
        error_log("Found guest reservation with code: " . $reservation['reservation_code']);
        $debug['guest_row'] = $reservation;
        
        // MODIFIED: Check if check_outdate exists and is valid before comparing
        $is_expired = false;
        if (!empty($reservation['check_out'])) {
            $checkout_timestamp = strtotime($reservation['check_out']);
            $current_timestamp = time();
            
            // Only mark as expired if there is a valid checkout date in the past
            if ($checkout_timestamp && $checkout_timestamp < $current_timestamp) {
                $is_expired = true;
            }
        }
        
        $results[] = [
            'id' => $reservation['id'],
            'payment_id' => $reservation['payment_id'],
            'reference_number' => $reservation['reservation_code'],
            'booking_date' => date('F j, Y', strtotime($reservation['created_at'])),
            'check_in' => isset($reservation['check_in']) && $reservation['check_in'] ? date('F j, Y', strtotime($reservation['check_in'])) : 'Not specified',
            'check_out' => isset($reservation['check_out']) && $reservation['check_out'] ? date('F j, Y', strtotime($reservation['check_out'])) : 'Not specified',
            'name' => $reservation['first_name'] . ' ' . $reservation['last_name'],
            'status' => isset($reservation['payment_status']) && $reservation['payment_status'] ? $reservation['payment_status'] : 'Pending',
            'payment_status' => isset($reservation['payment_status']) && $reservation['payment_status'] ? $reservation['payment_status'] : 'Pending',
            'total_amount' => $reservation['total_price'],
            'reservation_type' => 'guest',
            'reservation_code' => $reservation['reservation_code'],
            'is_expired' => $is_expired, // Add fixed expired status to the result
            'created_at' => $reservation['created_at'] // Added created_at to help with sorting
        ];
    }
}
$guestStmt->close();

// Then check user reservations
$userStmt = $conn->prepare("SELECT ur.*, p.status as payment_status, p.id as payment_id
                            FROM user_reservation ur
                            LEFT JOIN payments p ON ur.reservation_code = p.user_reservation_code
                            WHERE ur.reservation_code = ?
                            ORDER BY ur.created_at ASC");  // Added ORDER BY to sort by creation date

if (!$userStmt) {
    echo json_encode(['error' => 'Prepare statement failed (user): ' . $conn->error]);
    exit;
}

$userStmt->bind_param("s", $reference);
$userStmt->execute();
$userResult = $userStmt->get_result();

if (!$userResult) {
    echo json_encode(['error' => 'Database error (user query): ' . $userStmt->error]);
    exit;
} else {
    // Add explicit logging
    $userCount = $userResult->num_rows;
    error_log("User results count: " . $userCount);
    $debug['user_count'] = $userCount;
    
    // Process user reservations
    while ($reservation = $userResult->fetch_assoc()) {
        error_log("Found user reservation with code: " . $reservation['reservation_code']);
        $debug['user_row'] = $reservation;
        
        // MODIFIED: Check if check_outdate exists and is valid before comparing
        $is_expired = false;
        if (!empty($reservation['check_out'])) {
            $checkout_timestamp = strtotime($reservation['check_out']);
            $current_timestamp = time();
            
            // Only mark as expired if there is a valid checkout date in the past
            if ($checkout_timestamp && $checkout_timestamp < $current_timestamp) {
                $is_expired = true;
            }
        }

        $results[] = [
            'id' => $reservation['id'],
            'payment_id' => $reservation['payment_id'],
            'reference_number' => $reservation['reservation_code'],
            'booking_date' => date('F j, Y', strtotime($reservation['created_at'])),
            'check_in' => isset($reservation['check_in']) && $reservation['check_in'] ? date('F j, Y', strtotime($reservation['check_in'])) : 'Not specified',
            'check_out' => isset($reservation['check_out']) && $reservation['check_out'] ? date('F j, Y', strtotime($reservation['check_out'])) : 'Not specified',
            'name' => $reservation['first_name'] . ' ' . $reservation['last_name'],
            'status' => isset($reservation['payment_status']) && $reservation['payment_status'] ? $reservation['payment_status'] : 'Pending',
            'payment_status' => isset($reservation['payment_status']) && $reservation['payment_status'] ? $reservation['payment_status'] : 'Pending',
            'total_amount' => isset($reservation['total_amount']) ? $reservation['total_amount'] : $reservation['total_price'],
            'reservation_type' => 'user',
            'reservation_code' => $reservation['reservation_code'],
            'is_expired' => $is_expired, // Add fixed expired status to the result
            'created_at' => $reservation['created_at'] // Added created_at to help with sorting
        ];
    }
}
$userStmt->close();

// Sort all results by creation date (oldest first)
usort($results, function($a, $b) {
    return strtotime($a['created_at']) - strtotime($b['created_at']);
});

// Check for any unexpected output
$unexpected_output = ob_get_clean();
if (!empty($unexpected_output)) {
    error_log("Unexpected output in search_booking.php: " . $unexpected_output);
}

// Return results with debug information
echo json_encode(['debug' => $debug, 'results' => $results]);

// Close database connection
mysqli_close($conn);
?>