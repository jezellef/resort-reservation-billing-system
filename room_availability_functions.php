<?php
// room_availability_functions.php
// Add this file to your project and include it where needed

/**
 * Check if a room is available for booking
 * @param object $conn Database connection
 * @param int $room_id Room ID
 * @param string $check_in Check-in date (Y-m-d format)
 * @param string $check_out Check-out date (Y-m-d format)
 * @param int $quantity_needed How many units needed
 * @param int $exclude_reservation_id Exclude this reservation (for editing)
 * @return array ['available' => bool, 'available_quantity' => int, 'message' => string]
 */
function checkRoomAvailability($conn, $room_id, $check_in, $check_out, $quantity_needed = 1, $exclude_reservation_id = null) {
    // Get room's real quantity
    $roomQuery = "SELECT real_quantity, name, status FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($roomQuery);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $roomResult = $stmt->get_result();
    
    if ($roomResult->num_rows === 0) {
        return ['available' => false, 'available_quantity' => 0, 'message' => 'Room not found'];
    }
    
    $room = $roomResult->fetch_assoc();
    
    // Check if room is available status
    if ($room['status'] !== 'Available') {
        return ['available' => false, 'available_quantity' => 0, 'message' => 'Room is currently unavailable'];
    }
    
    // Get booked quantity for the date range
    $bookedQuery = "
        SELECT COALESCE(SUM(rr.quantity_booked), 0) as total_booked
        FROM reservation_rooms rr
        JOIN reservations r ON rr.reservation_id = r.id
        WHERE rr.room_id = ? 
        AND r.status IN ('Approved', 'Checked In')
        AND NOT (rr.check_out_date <= ? OR rr.check_in_date >= ?)
    ";
    
    // Add exclusion for editing existing reservations
    if ($exclude_reservation_id) {
        $bookedQuery .= " AND r.id != ?";
        $stmt = $conn->prepare($bookedQuery);
        $stmt->bind_param("issi", $room_id, $check_in, $check_out, $exclude_reservation_id);
    } else {
        $stmt = $conn->prepare($bookedQuery);
        $stmt->bind_param("iss", $room_id, $check_in, $check_out);
    }
    
    $stmt->execute();
    $bookedResult = $stmt->get_result();
    $bookedData = $bookedResult->fetch_assoc();
    $total_booked = $bookedData['total_booked'];
    
    $available_quantity = $room['real_quantity'] - $total_booked;
    $is_available = $available_quantity >= $quantity_needed;
    
    $message = $is_available ? 
        "Available ({$available_quantity} units free)" : 
        "Not available (only {$available_quantity} units free, need {$quantity_needed})";
    
    return [
        'available' => $is_available,
        'available_quantity' => max(0, $available_quantity),
        'message' => $message
    ];
}

/**
 * Get available rooms for a date range
 * @param object $conn Database connection
 * @param string $check_in Check-in date
 * @param string $check_out Check-out date
 * @param string $room_type Optional filter by room type
 * @return array Array of available rooms with quantities
 */
function getAvailableRooms($conn, $check_in, $check_out, $room_type = null) {
    $query = "SELECT id, name, real_quantity, day_tour_price, night_tour_price, 
                     whole_day_morning_tour_price, whole_day_night_tour_price, 
                     capacity, image, description
              FROM rooms 
              WHERE status = 'Available'";
    
    if ($room_type) {
        if ($room_type === 'private') {
            $query .= " AND name LIKE '%Private%'";
        } else {
            $query .= " AND name NOT LIKE '%Private%'";
        }
    }
    
    $query .= " ORDER BY name";
    
    $result = $conn->query($query);
    $available_rooms = [];
    
    while ($room = $result->fetch_assoc()) {
        $availability = checkRoomAvailability($conn, $room['id'], $check_in, $check_out);
        
        if ($availability['available_quantity'] > 0) {
            $room['available_quantity'] = $availability['available_quantity'];
            $available_rooms[] = $room;
        }
    }
    
    return $available_rooms;
}

/**
 * Book a room (add to reservation_rooms table)
 * @param object $conn Database connection
 * @param int $reservation_id Reservation ID
 * @param int $room_id Room ID
 * @param int $quantity Quantity to book
 * @param string $check_in Check-in date
 * @param string $check_out Check-out date
 * @param string $tour_type Tour type
 * @return bool Success status
 */
function bookRoom($conn, $reservation_id, $room_id, $quantity, $check_in, $check_out, $tour_type = null) {
    // First check availability
    $availability = checkRoomAvailability($conn, $room_id, $check_in, $check_out, $quantity);
    
    if (!$availability['available']) {
        return false;
    }
    
    // Insert booking
    $insertQuery = "INSERT INTO reservation_rooms (reservation_id, room_id, quantity_booked, check_in_date, check_out_date, tour_type) 
                    VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insertQuery);
    $stmt->bind_param("iiisss", $reservation_id, $room_id, $quantity, $check_in, $check_out, $tour_type);
    
    return $stmt->execute();
}

/**
 * Update room quantities based on real capacity constraints
 * @param object $conn Database connection
 * @param int $room_id Room ID
 * @param int $new_real_quantity New real quantity (physical rooms available)
 * @return bool Success status
 */
function updateRoomRealQuantity($conn, $room_id, $new_real_quantity) {
    // Don't allow negative quantities
    if ($new_real_quantity < 0) {
        return false;
    }
    
    // Update the real quantity
    $updateQuery = "UPDATE rooms SET real_quantity = ? WHERE id = ?";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ii", $new_real_quantity, $room_id);
    
    if ($stmt->execute()) {
        // If real_quantity is 0, set status to Unavailable
        if ($new_real_quantity == 0) {
            $statusQuery = "UPDATE rooms SET status = 'Unavailable' WHERE id = ?";
            $statusStmt = $conn->prepare($statusQuery);
            $statusStmt->bind_param("i", $room_id);
            $statusStmt->execute();
        }
        return true;
    }
    
    return false;
}

/**
 * Free up rooms when reservation is checked out or cancelled
 * @param object $conn Database connection
 * @param int $reservation_id Reservation ID
 * @return bool Success status
 */
function freeUpRooms($conn, $reservation_id) {
    // This happens automatically when reservation status changes
    // No need to delete from reservation_rooms, just change reservation status
    return true;
}

/**
 * Get current room occupancy
 * @param object $conn Database connection
 * @param string $date Optional date (defaults to today)
 * @return array Room occupancy data
 */
function getRoomOccupancy($conn, $date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    $query = "
        SELECT 
            r.id,
            r.name,
            r.real_quantity,
            COALESCE(SUM(rr.quantity_booked), 0) as occupied,
            (r.real_quantity - COALESCE(SUM(rr.quantity_booked), 0)) as available
        FROM rooms r
        LEFT JOIN reservation_rooms rr ON r.id = rr.room_id
        LEFT JOIN reservations res ON rr.reservation_id = res.id
        WHERE r.status = 'Available'
        AND (rr.id IS NULL OR (
            res.status IN ('Approved', 'Checked In') 
            AND rr.check_in_date <= ? 
            AND rr.check_out_date > ?
        ))
        GROUP BY r.id, r.name, r.real_quantity
        ORDER BY r.name
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $date, $date);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>