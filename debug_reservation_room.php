<?php
// Debug script to check reservation_room table structure and data
require_once 'database.php';

// Get the reservation code from URL parameter
$reservation_code = $_GET['code'] ?? 'RES-20250906-FA4D56'; // Use your actual reservation code

echo "<h2>Debugging Reservation Room Data</h2>";
echo "<h3>Reservation Code: " . htmlspecialchars($reservation_code) . "</h3>";

// First, get the reservation details
$stmt = $mysqli->prepare("SELECT * FROM reservations WHERE reservation_code = ?");
$stmt->bind_param("s", $reservation_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $reservation = $result->fetch_assoc();
    echo "<h3>Reservation Details:</h3>";
    echo "<pre>";
    print_r($reservation);
    echo "</pre>";
    
    $reservation_id = $reservation['id'];
    
    // Check the structure of reservation_room table
    echo "<h3>Reservation_Room Table Structure:</h3>";
    $structure = $mysqli->query("DESCRIBE reservation_room");
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $structure->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    
    // Get room data for this reservation
    echo "<h3>Room Data for this Reservation:</h3>";
    $room_stmt = $mysqli->prepare("SELECT * FROM reservation_room WHERE reservation_id = ?");
    $room_stmt->bind_param("i", $reservation_id);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    
    if ($room_result->num_rows > 0) {
        echo "<table border='1'>";
        $first_row = true;
        while ($room = $room_result->fetch_assoc()) {
            if ($first_row) {
                echo "<tr>";
                foreach (array_keys($room) as $key) {
                    echo "<th>" . htmlspecialchars($key) . "</th>";
                }
                echo "</tr>";
                $first_row = false;
            }
            echo "<tr>";
            foreach ($room as $value) {
                echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No room data found for this reservation!</p>";
    }
    
    // Check if there are any entries with the reservation_code instead of reservation_id
    echo "<h3>Checking for reservation_code-based entries:</h3>";
    $code_stmt = $mysqli->prepare("SELECT * FROM reservation_room WHERE reservation_code = ?");
    if ($code_stmt) {
        $code_stmt->bind_param("s", $reservation_code);
        $code_stmt->execute();
        $code_result = $code_stmt->get_result();
        
        if ($code_result->num_rows > 0) {
            echo "<table border='1'>";
            $first_row = true;
            while ($room = $code_result->fetch_assoc()) {
                if ($first_row) {
                    echo "<tr>";
                    foreach (array_keys($room) as $key) {
                        echo "<th>" . htmlspecialchars($key) . "</th>";
                    }
                    echo "</tr>";
                    $first_row = false;
                }
                echo "<tr>";
                foreach ($room as $value) {
                    echo "<td>" . htmlspecialchars($value ?? 'NULL') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No reservation_code-based room data found.</p>";
        }
        $code_stmt->close();
    } else {
        echo "<p>reservation_code column does not exist in reservation_room table.</p>";
    }
    
} else {
    echo "<p>Reservation not found!</p>";
}

$stmt->close();
$mysqli->close();
?>

<!-- Add this to the URL: ?code=YOUR_RESERVATION_CODE -->
<p><strong>Usage:</strong> Add ?code=YOUR_RESERVATION_CODE to the URL to check a specific reservation</p>