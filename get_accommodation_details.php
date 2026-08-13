<?php
// Include database connection
$mysqli = require "database.php";

// Get accommodation_id from URL parameter
$accommodation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Initialize response array
$response = [
    'success' => false,
    'data' => null,
    'message' => ''
];

if ($accommodation_id > 0) {
    // Get accommodation details
    $stmt = $mysqli->prepare("
        SELECT a.check_in, a.check_out, a.adults, a.children, ar.room_id 
        FROM accommodation_details a
        LEFT JOIN accommodation_rooms ar ON a.id = ar.accommodation_id
        WHERE a.id = ?
    ");
    
    $stmt->bind_param("i", $accommodation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $accommodation = null;
        $room_ids = [];
        
        while ($row = $result->fetch_assoc()) {
            if ($accommodation === null) {
                $accommodation = [
                    'check_in' => $row['check_in'],
                    'check_out' => $row['check_out'],
                    'adults' => $row['adults'],
                    'children' => $row['children'],
                ];
            }
            
            if ($row['room_id']) {
                $room_ids[] = $row['room_id'];
            }
        }
        
        $accommodation['room_ids'] = $room_ids;
        
        $response = [
            'success' => true,
            'data' => $accommodation,
            'message' => 'Accommodation details retrieved successfully'
        ];
    } else {
        $response['message'] = 'Accommodation not found';
    }
} else {
    $response['message'] = 'Invalid accommodation ID';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Close database connection
$mysqli->close();
?>