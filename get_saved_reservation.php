<?php

// Clean output buffer to prevent JSON issues
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(0);

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['reservation_code'])) {
        throw new Exception('No reservation code provided');
    }

    $reservation_code = trim($data['reservation_code']);
    
    if (empty($reservation_code)) {
        throw new Exception('Reservation code is empty');
    }

    require_once 'database.php';

    if (!isset($mysqli) || $mysqli->connect_errno) {
        throw new Exception('Database connection failed');
    }

    // SIMPLE QUERY - Just find the reservation, don't worry about status
    $stmt = $mysqli->prepare("SELECT * FROM reservations WHERE reservation_code = ?");
    if (!$stmt) {
        throw new Exception('Database query failed: ' . $mysqli->error);
    }
    
    $stmt->bind_param("s", $reservation_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('No reservation found with that code');
    }
    
    $reservation = $result->fetch_assoc();
    $stmt->close();
    
    // Check if reservation is expired
    $current_time = time();
    if ($reservation['expires_at'] && $reservation['expires_at'] < $current_time) {
        throw new Exception('This reservation has expired. Please make a new booking.');
    }
    
    // Get room details
    $room_stmt = $mysqli->prepare("
        SELECT rr.*, r.name as room_name, r.day_tour_price, r.night_tour_price, r.capacity 
        FROM reservation_room rr
        JOIN rooms r ON rr.room_id = r.id
        WHERE rr.reservation_id = ?
    ");
    
    if (!$room_stmt) {
        throw new Exception('Room query failed: ' . $mysqli->error);
    }
    
    $room_stmt->bind_param("i", $reservation['id']);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    
    $rooms = [];
    while ($room = $room_result->fetch_assoc()) {
        $rooms[] = [
            'room_id' => $room['room_id'],
            'room_name' => $room['room_name'],
            'quantity' => $room['quantity_booked'],
            'tour_type' => $room['tour_type'] ?? 'day_tour',
            'capacity' => $room['capacity'],
            'day_price' => $room['day_tour_price'],
            'night_price' => $room['night_tour_price']
        ];
    }
    $room_stmt->close();
    
    // Add rooms array to reservation data
    $reservation['rooms'] = $rooms;
    
    echo json_encode([
        'success' => true,
        'reservation' => $reservation
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    if (isset($mysqli)) {
        $mysqli->close();
    }
}
?>