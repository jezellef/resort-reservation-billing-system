<?php
$servername = "localhost";
$username = "root";  
$password = ""; 
$database = "resort.db";  

// Create connection
$conn = new mysqli($servername, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch rooms from the database
$sql = "SELECT * FROM rooms";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accommodations</title>
    <link rel="stylesheet" href="mystyle.css">
</head>
<body>

<header>
    <h1>Available Rooms</h1>
</header>

<div class="container">
    <?php
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            echo "<div class='room'>";
            echo "<img src='" . $row["image"] . "' alt='" . $row["name"] . "' class='main-image'>";
            echo "<h2>" . $row["name"] . "</h2>";
            echo "<p>Good for " . $row["capacity"] . " Pax</p>";
            echo "<p class='price'>Daytour: " . number_format($row["daytour_price"], 2) . "</p>";
            echo "<p class='price'>Overnight: " . number_format($row["overnight_price"], 2) . "</p>";
            echo "<p class='note'>" . $row["description"] . "</p>";
            echo "<a href='phase1_booking.php?room_id=" . $row["id"] . "'><button>Book Now</button></a>";
            echo "</div>";
        }
    } else {
        echo "<p>No rooms available.</p>";
    }
    ?>
</div>

</body>
</html>

<?php
$conn->close();
?>
