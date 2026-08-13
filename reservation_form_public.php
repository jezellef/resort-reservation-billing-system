<?php
session_start();
$mysqli = require __DIR__ . "/database.php";
$check_in = $_GET['check_in'] ?? '';
$check_out = $_GET['check_out'] ?? '';
$room_id = $_GET['room_id'] ?? '';

if (!$check_in || !$check_out || !$room_id) {
    echo "Missing required data.";
    exit;
}

function generateReservationCode($mysqli) {
    $result = $mysqli->query("SELECT COUNT(*) as count FROM publicguest_reservations");
    $row = $result->fetch_assoc();
    $next = $row['count'] + 1;
    return "PRG" . str_pad($next, 5, "0", STR_PAD_LEFT);
}
$user_id = $_SESSION['user_id'] ?? null;
$reservation_code = generateReservationCode($mysqli);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $contact_number = $_POST['contact_number'];
    $adult_count = $_POST['adult_count'];
    $kid_count = $_POST['kid_count'];
    $tour_type = $_POST['tour_type'];
    $special_requests = $_POST['special_requests'];

    $room_stmt = $mysqli->prepare("SELECT * FROM rooms WHERE id = ?");
    $room_stmt->bind_param("i", $room_id);
    $room_stmt->execute();
    $room_result = $room_stmt->get_result();
    $room = $room_result->fetch_assoc();

    // Calculate the total price
    $base_price = $room['day_tour_price']; // or night_tour_price depending on the type
    $extras_price = 0; // You can add logic to calculate extras (like extra pillows, etc.)
    $total_price = $base_price + $extras_price;

    $stmt = $mysqli->prepare("
        INSERT INTO publicguest_reservations (
            reservation_code, room_id, check_in, check_out,
            first_name, last_name, email, contact_number,
            adult_count, kid_count, tour_type, special_requests,
            status, created_at, base_price, extras_price, total_price
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?, ?)
        ");
        $stmt->bind_param(
            "sissssssiiisddd",
            $reservation_code, $room_id, $check_in, $check_out,
            $first_name, $last_name, $email, $contact_number,
            $adult_count, $kid_count, $tour_type, $special_requests,
            $base_price, $extras_price, $total_price
        );

    if ($stmt->execute()) {
        // Redirect to billing
        header("Location: billing_public.php?reservation_code=" . urlencode($reservation_code));
        exit;
    } else {
        echo "Error: " . $stmt->error;
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Public Reservation Form</title>
</head>
<body>
    <h2>Public Reservation Form</h2>
    <form method="POST">
        <p><strong>Reservation Code:</strong> <?= $reservation_code ?></p>
        <p><strong>Check-in:</strong> <?= htmlspecialchars($check_in) ?></p>
        <p><strong>Check-out:</strong> <?= htmlspecialchars($check_out) ?></p>

        <input type="hidden" name="check_in" value="<?= $check_in ?>">
        <input type="hidden" name="check_out" value="<?= $check_out ?>">
        <input type="hidden" name="room_id" value="<?= $room_id ?>">

        <label>First Name: <input type="text" name="first_name" required></label><br>
        <label>Last Name: <input type="text" name="last_name" required></label><br>
        <label>Email: <input type="email" name="email" required></label><br>
        <label>Contact Number: <input type="text" name="contact_number" required></label><br>
        <label>Number of Adults: <input type="number" name="adult_count" min="1" required></label><br>
        <label>Number of Kids: <input type="number" name="kid_count" min="0" required></label><br>
        <label>Tour Type:
            <select name="tour_type" required>
                <option value="Day Tour">Day Tour</option>
                <option value="Night Tour">Night Tour</option>
            </select>
        </label><br>
        <label>Special Requests:<br>
            <textarea name="special_requests" rows="3" cols="30"></textarea>
        </label><br><br>

        <button type="submit">Submit Reservation</button>
    </form>
</body>
</html>
