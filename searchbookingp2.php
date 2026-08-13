<?php
// Clean any output buffer and prevent any output before JSON
if (ob_get_level()) {
    ob_end_clean();
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 0); // Keep this off for JSON responses

// Set JSON header immediately
header('Content-Type: application/json');

// Function to send JSON response and exit
function sendJsonResponse($data) {
    echo json_encode($data);
    exit;
}

// Check database connection
require_once 'db_connect.php';

if (!isset($conn) || mysqli_connect_errno()) {
    sendJsonResponse(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Invalid request method']);
}

$reference = isset($_POST['reference']) ? trim($_POST['reference']) : '';

if (empty($reference)) {
    sendJsonResponse(['error' => 'Reference number is required']);
}

try {
    $results = [];

    // Get reservation details
    $reservationStmt = $conn->prepare("SELECT r.*, p.status as payment_status, p.id as payment_id, p.amount_paid as payment_amount, p.payment_date as payment_created_at
                                       FROM reservations r
                                       LEFT JOIN payments p ON r.reservation_code = p.reservation_codes
                                       WHERE r.reservation_code = ?
                                       ORDER BY r.created_at ASC");

    if (!$reservationStmt) {
        sendJsonResponse(['error' => 'Prepare statement failed: ' . $conn->error]);
    }

    $reservationStmt->bind_param("s", $reference);
    
    if (!$reservationStmt->execute()) {
        sendJsonResponse(['error' => 'Query execution failed: ' . $reservationStmt->error]);
    }
    
    $reservationResult = $reservationStmt->get_result();

    if ($reservationResult->num_rows === 0) {
        sendJsonResponse(['error' => 'No reservation found with that reference number']);
    }

    while ($reservation = $reservationResult->fetch_assoc()) {
        // Check if reservation is expired
        $is_expired = false;
        if (!empty($reservation['expires_at'])) {
            $current_timestamp = time();
            if ($reservation['expires_at'] < $current_timestamp) {
                $is_expired = true;
            }
        } elseif (!empty($reservation['check_out'])) {
            // Fallback to check_out date if expires_at is not available
            $checkout_timestamp = strtotime($reservation['check_out']);
            $current_timestamp = time();
            
            if ($checkout_timestamp && $checkout_timestamp < $current_timestamp) {
                $is_expired = true;
            }
        }

        // Fetch associated room data with proper pricing
        $roomStmt = $conn->prepare("SELECT rr.*, r.name as room_name, r.capacity, r.day_tour_price, r.night_tour_price
                                   FROM reservation_room rr
                                   LEFT JOIN rooms r ON rr.room_id = r.id
                                   WHERE rr.reservation_id = ?");
        
        if (!$roomStmt) {
            sendJsonResponse(['error' => 'Room query prepare failed: ' . $conn->error]);
        }
        
        $roomStmt->bind_param("i", $reservation['id']);
        
        if (!$roomStmt->execute()) {
            sendJsonResponse(['error' => 'Room query execution failed: ' . $roomStmt->error]);
        }
        
        $roomResult = $roomStmt->get_result();

        $rooms = [];
        while ($room = $roomResult->fetch_assoc()) {
            // Determine if it's a day tour
            $is_day_tour = ($reservation['check_in'] === $reservation['check_out']);
            $room_price = $is_day_tour ? $room['day_tour_price'] : $room['night_tour_price'];
            
            $rooms[] = [
                'room_id' => $room['room_id'],
                'room_name' => $room['room_name'],
                'room_capacity' => $room['capacity'],
                'quantity_booked' => $room['quantity_booked'],
                'tour_type' => $room['tour_type'],
                'room_price' => number_format($room_price, 2),
                'room_price_raw' => $room_price
            ];
        }
        $roomStmt->close();

        // Get totals from reservation record
        $total_amount = floatval($reservation['total_amount'] ?? $reservation['total_price'] ?? 0);
        $amount_paid = floatval($reservation['amount_paid'] ?? 0);
        $balance_due = $total_amount - $amount_paid;

        $results[] = [
            'id' => $reservation['id'],
            'reference_number' => $reservation['reservation_code'],
            'booking_date' => date('F j, Y', strtotime($reservation['created_at'])),
            'check_in' => isset($reservation['check_in']) && $reservation['check_in'] ? date('F j, Y', strtotime($reservation['check_in'])) : 'Not specified',
            'check_out' => isset($reservation['check_out']) && $reservation['check_out'] ? date('F j, Y', strtotime($reservation['check_out'])) : 'Not specified',
            'check_in_raw' => $reservation['check_in'],
            'check_out_raw' => $reservation['check_out'],
            'name' => $reservation['first_name'] . ' ' . $reservation['last_name'],
            'first_name' => $reservation['first_name'],
            'last_name' => $reservation['last_name'],
            'email' => $reservation['email'],
            'contact_number' => $reservation['contact_number'],
            'adults' => $reservation['adults'] ?? $reservation['adult_count'] ?? 0,
            'children' => $reservation['children'] ?? $reservation['kid_count'] ?? 0,
            'special_requests' => $reservation['special_requests'] ?? '',
            'status' => $reservation['status'] ?? 'Pending',
            'payment_status' => $reservation['payment_status'] ?? 'Pending',
            'total_amount' => number_format($total_amount, 2),
            'total_amount_raw' => $total_amount,
            'amount_paid' => number_format($amount_paid, 2),
            'amount_paid_raw' => $amount_paid,
            'balance_due' => number_format($balance_due, 2),
            'balance_due_raw' => $balance_due,
            'base_price' => number_format($reservation['base_price'] ?? 0, 2),
            'base_price_raw' => floatval($reservation['base_price'] ?? 0),
            'extras_price' => number_format($reservation['extras_price'] ?? 0, 2),
            'extras_price_raw' => floatval($reservation['extras_price'] ?? 0),
            'reservation_type' => $reservation['reservation_type'] ?? 'public',
            'guest_type' => $reservation['guest_type'] ?? 'guest',
            'reservation_code' => $reservation['reservation_code'],
            'add_second_house' => $reservation['add_second_house'] ?? 0,
            'default_tour_type' => $reservation['default_tour_type'] ?? 'day_tour',
            'is_expired' => $is_expired,
            'expires_at' => $reservation['expires_at'] ?? null,
            'created_at' => $reservation['created_at'],
            'rooms' => $rooms
        ];
    }
    
    $reservationStmt->close();

    // Sort by creation date
    usort($results, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });

    sendJsonResponse(['error' => null, 'results' => $results]);

} catch (Exception $e) {
    sendJsonResponse(['error' => 'Database error: ' . $e->getMessage()]);
} finally {
    if (isset($conn)) {
        mysqli_close($conn);
    }
}
?>