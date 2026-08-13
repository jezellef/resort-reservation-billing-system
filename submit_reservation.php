<?php
$mysqli = require __DIR__ . "/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $stmt = $mysqli->prepare("INSERT INTO publicguest_reservations (tour_type, check_in, check_out, room_id, full_name, email, phone, address)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param(
        "sssissss",
        $_POST["tour_type"],
        $_POST["check_in"],
        $_POST["check_out"],
        $_POST["room_id"],
        $_POST["full_name"],
        $_POST["email"],
        $_POST["phone"],
        $_POST["address"]
    );

    if ($stmt->execute()) {
        echo "<h2>Reservation Confirmed!</h2><p>Thank you for booking, " . htmlspecialchars($_POST["full_name"]) . ".</p>";
    } else {
        echo "<p>Error saving reservation. Please try again.</p>";
    }

    $stmt->close();
}
?>
