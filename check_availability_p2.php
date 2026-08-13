<?php
// Include database connection
$mysqli = require 'database.php';

// Check connection
if ($mysqli->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get input parameters
$check_in_date = $_POST['check_in_date'] ?? '';
$check_out_date = $_POST['check_out_date'] ?? '';

// Validate dates
if (empty($check_in_date) || empty($check_out_date)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing check-in or check-out date']);
    exit;
}

try {
    // Format dates for SQL query
    $check_in = date('Y-m-d', strtotime($check_in_date));
    $check_out = date('Y-m-d', strtotime($check_out_date));
    
    // Current date for validation
    $today = date('Y-m-d');
    
    // Validate date range
    if ($check_in <= $today) {
        throw new Exception('Check-in date must be in the future');
    }
    
    if ($check_out <= $check_in) {
        throw new Exception('Check-out date must be after check-in date');
    }
    
    // Call the stored procedure for checking room availability
    $query = "CALL check_room_availability(?, ?)";
    
    // Prepare and execute statement
    $stmt = $mysqli->prepare($query);
    $stmt->bind_param("ss", $check_in, $check_out);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Fetch available rooms
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        // Add price information from rooms table
        $price_query = "SELECT price_per_night FROM rooms WHERE id = ?";
        $price_stmt = $mysqli->prepare($price_query);
        $price_stmt->bind_param("i", $row['id']);
        $price_stmt->execute();
        $price_result = $price_stmt->get_result();
        $price_data = $price_result->fetch_assoc();
        
        $row['price_per_night'] = $price_data['price_per_night'] ?? 0;
        $row['description'] = ""; // Add description field if needed from rooms table
        
        $rooms[] = $row;
        $price_stmt->close();
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($rooms);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $mysqli->close();
}
?>