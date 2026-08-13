<?php
session_start();
header('Content-Type: application/json');
$mysqli = require "database.php";
// Basic query to get all available rooms
$sql = "SELECT id, name, description, day_tour_price, night_tour_price, 
               whole_day_morning_tour_price, whole_day_night_tour_price, 
               quantity, image, status, capacity 
        FROM rooms 
        WHERE status = 'Available' AND quantity > 0";
$result = $mysqli->query($sql);
$rooms = array();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // Add the room to our array
        $rooms[] = $row;
    }
}
// Return rooms as JSON
echo json_encode($rooms);
// Close the database connection
$mysqli->close();
?>