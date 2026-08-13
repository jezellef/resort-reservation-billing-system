<?php
// load_saved_reservation.php - Function to load saved reservation data
function loadSavedReservation($mysqli, $reservation_code) {
    try {
        // Get reservation details
        $stmt = $mysqli->prepare("SELECT * FROM reservations WHERE reservation_code = ? AND status = 'saved'");
        if (!$stmt) {
            throw new Exception("Failed to prepare reservation query: " . $mysqli->error);
        }
        
        $stmt->bind_param("s", $reservation_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("No saved reservation found with code: " . $reservation_code);
        }
        
        $reservation = $result->fetch_assoc();
        $stmt->close();
        
        // Check if reservation is expired
        $current_time = time();
        if ($reservation['expires_at'] && $reservation['expires_at'] < $current_time) {
            throw new Exception("This reservation has expired. Please make a new booking.");
        }
        
        // Get room details
        $room_stmt = $mysqli->prepare("
            SELECT rr.*, r.name as room_name, r.day_tour_price, r.night_tour_price, r.capacity 
            FROM reservation_room rr
            JOIN rooms r ON rr.room_id = r.id
            WHERE rr.reservation_id = ?
        ");
        
        if (!$room_stmt) {
            throw new Exception("Failed to prepare room query: " . $mysqli->error);
        }
        
        $room_stmt->bind_param("i", $reservation['id']);
        $room_stmt->execute();
        $room_result = $room_stmt->get_result();
        
        $rooms = [];
        while ($room = $room_result->fetch_assoc()) {
            $rooms[] = [
                'room_id' => $room['room_id'],
                'room_name' => $room['room_name'],
                'quantity_booked' => $room['quantity_booked'],
                'tour_type' => $room['tour_type'],
                'day_tour_price' => $room['day_tour_price'],
                'night_tour_price' => $room['night_tour_price'],
                'capacity' => $room['capacity']
            ];
        }
        $room_stmt->close();
        
        // Return all reservation data
        return [
            'success' => true,
            'reservation' => $reservation,
            'rooms' => $rooms
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// If this file is called directly (for AJAX requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'load_reservation') {
    header('Content-Type: application/json');
    
    require_once 'database.php';
    
    $reservation_code = $_POST['reservation_code'] ?? '';
    
    if (empty($reservation_code)) {
        echo json_encode(['success' => false, 'error' => 'Reservation code is required']);
        exit;
    }
    
    $result = loadSavedReservation($mysqli, $reservation_code);
    echo json_encode($result);
    exit;
}
?>