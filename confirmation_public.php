<?php
session_start();
$mysqli = require __DIR__ . "/database.php";

// Retrieve reservation code from URL
$reservation_code = $_GET['reservation_code'] ?? null;
if (!$reservation_code) {
    echo "Reservation code is missing.";
    exit;
}

// Verify if the reservation code exists in the public reservation table
$stmt = $mysqli->prepare("SELECT publicguest_reservations.*, rooms.name AS room_name 
                          FROM publicguest_reservations 
                          JOIN rooms ON publicguest_reservations.room_id = rooms.id 
                          WHERE publicguest_reservations.reservation_code = ?");
$stmt->bind_param("s", $reservation_code);
$stmt->execute();
$result = $stmt->get_result();
$reservation = $result->fetch_assoc();

if (!$reservation) {
    echo "Public reservation not found.";
    exit;
}
?>

<!-- Reservation details table -->
<table>
    <tr>
        <td>Reservation Code:</td>
        <td><?php echo $reservation['reservation_code']; ?></td>
    </tr>
    <tr>
        <td>Name:</td>
        <td><?php echo $reservation['first_name'] . ' ' . $reservation['last_name']; ?></td>
    </tr>
    <tr>
        <td>Email:</td>
        <td><?php echo $reservation['email']; ?></td>
    </tr>
    <tr>
        <td>Contact Number:</td>
        <td><?php echo $reservation['contact_number']; ?></td>
    </tr>
    <tr>
        <td>Tour Type:</td>
        <td><?php echo $reservation['tour_type']; ?></td>
    </tr>
    <tr>
        <td>Check-in Date:</td>
        <td><?php echo $reservation['check_in']; ?></td>
    </tr>
    <tr>
        <td>Check-out Date:</td>
        <td><?php echo $reservation['check_out']; ?></td>
    </tr>
    <tr>
        <td>Adults:</td>
        <td><?php echo $reservation['adult_count']; ?></td>
    </tr>
    <tr>
        <td>Kids:</td>
        <td><?php echo $reservation['kid_count']; ?></td>
    </tr>
    <tr>
        <td>Base Tour Price:</td>
        <td>₱<?php echo number_format($reservation['base_price'], 2); ?></td>
    </tr>
    <tr>
        <td>Total Amount Due:</td>
        <td>₱<?php echo number_format($reservation['total_price'], 2); ?></td>
    </tr>
    <tr>
        <td>Reserved Room:</td>
        <td><?php echo $reservation['room_name']; ?></td>
    </tr>
</table>

<?php
// Optionally, you can also display payment details if available
if (!empty($reservation['transaction_number'])) {
    echo "<h4>Payment Details</h4>";
    echo "Transaction Number: " . $reservation['transaction_number'] . "<br>";
    echo "Payment Method: " . $reservation['payment_method'] . "<br>";
    echo "Payment Proof: <a href='" . $reservation['proof_of_payment'] . "'>View Payment Proof</a><br>";
}
?>
