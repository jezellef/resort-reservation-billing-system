<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

// CORRECTLY FIXED - Allows consecutive bookings like Dec 19-20 then Dec 20-21
function checkRoomAvailability($mysqli, $room_id, $check_in, $check_out, $quantity_needed = 1) {
    // Get room's real quantity
    $roomQuery = "SELECT real_quantity, name, status FROM rooms WHERE id = ?";
    $stmt = $mysqli->prepare($roomQuery);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $roomResult = $stmt->get_result();
    
    if ($roomResult->num_rows === 0) {
        return ['available' => false, 'available_quantity' => 0, 'message' => 'Room not found'];
    }
    $room = $roomResult->fetch_assoc();
    $stmt->close();
    
    // Check if room is available status
    if ($room['status'] !== 'Available') {
        return ['available' => false, 'available_quantity' => 0, 'message' => 'Room is currently unavailable'];
    }
    
    $current_time = time(); // Get current timestamp for expiration checking
    
    // For same-day bookings
    if ($check_in === $check_out) {
        // Check day tour bookings for the same date
        $bookedQuery = "
            SELECT COALESCE(SUM(rr.quantity_booked), 0) as total_booked
            FROM reservation_room rr
            JOIN reservations r ON rr.reservation_id = r.id
            WHERE rr.room_id = ? 
            AND (
                -- Confirmed/approved reservations
                r.status IN ('Approved', 'Checked In', 'Pending') OR
                -- Unexpired saved/pending reservations (within 3-hour window)
                (
                    (r.status = 'saved' OR r.status = '' OR r.status IS NULL) AND
                    r.payment_status IN ('Unpaid', 'unpaid') AND
                    r.expires_at > ?
                )
            )
            AND (
                (r.check_in = ? AND r.check_out = ?) OR
                (r.check_in <= ? AND r.check_out > ?)
            )
        ";
        $stmt = $mysqli->prepare($bookedQuery);
        $stmt->bind_param("iissss", $room_id, $current_time, $check_in, $check_out, $check_in, $check_in);
    } else {
        // CORRECT FIX: Two bookings overlap if:
        // - Existing check_in is BEFORE new check_out AND
        // - Existing check_out is AFTER new check_in
        // This allows consecutive bookings where check_out = next check_in
        $bookedQuery = "
            SELECT COALESCE(SUM(rr.quantity_booked), 0) as total_booked
            FROM reservation_room rr
            JOIN reservations r ON rr.reservation_id = r.id
            WHERE rr.room_id = ? 
            AND (
                -- Confirmed/approved reservations
                r.status IN ('Approved', 'Checked In', 'Pending') OR
                -- Unexpired saved/pending reservations (within 3-hour window)
                (
                    (r.status = 'saved' OR r.status = '' OR r.status IS NULL) AND
                    r.payment_status IN ('Unpaid', 'unpaid') AND
                    r.expires_at > ?
                )
            )
            AND r.check_in < ? AND r.check_out > ?
        ";
        $stmt = $mysqli->prepare($bookedQuery);
        $stmt->bind_param("iiss", $room_id, $current_time, $check_out, $check_in);
    }
    
    $stmt->execute();
    $bookedResult = $stmt->get_result();
    $bookedData = $bookedResult->fetch_assoc();
    $total_booked = $bookedData['total_booked'];
    $stmt->close();
    
    $available_quantity = $room['real_quantity'] - $total_booked;
    $is_available = $available_quantity >= $quantity_needed;
    
    return [
        'available' => $is_available,
        'available_quantity' => max(0, $available_quantity),
        'total_quantity' => $room['real_quantity'],
        'booked_quantity' => $total_booked,
        'message' => $is_available ? "Available" : "Not available"
    ];
}

try {
    require_once 'database.php';
    
    if (!isset($mysqli)) {
        throw new Exception("Database connection not found");
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Invalid JSON: " . json_last_error_msg());
    }
    
    $check_in = $input['check_in'] ?? '';
    $check_out = $input['check_out'] ?? '';
    
    if (!$check_in || !$check_out) {
        throw new Exception("Missing dates - check_in: $check_in, check_out: $check_out");
    }
    
    // Get all available rooms
    $roomsQuery = "SELECT id, name, real_quantity, day_tour_price, night_tour_price, capacity, status, image, description FROM rooms WHERE status = 'Available' AND real_quantity > 0 ORDER BY name";
    $result = $mysqli->query($roomsQuery);
    
    if (!$result) {
        throw new Exception("Room query failed: " . $mysqli->error);
    }
    
    $available_rooms = [];
    
    while ($room = $result->fetch_assoc()) {
        $availability = checkRoomAvailability($mysqli, $room['id'], $check_in, $check_out);
        
        // Add availability info to room
        $room['available_quantity'] = $availability['available_quantity'];
        $room['booked_quantity'] = $availability['booked_quantity'];
        
        // Only include rooms with availability > 0
        if ($availability['available_quantity'] > 0) {
            $available_rooms[] = $room;
        }
    }
    
    echo json_encode([
        'success' => true,
        'rooms' => $available_rooms
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>