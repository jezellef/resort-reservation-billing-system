<?php
session_start();
include 'db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch the latest unpaid reservation for this user
$sql = "SELECT * FROM reservations WHERE user_id = ? AND (status = 'Pending' OR status = 'Pending Verification') ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reservation = $result->fetch_assoc();

// If no reservation found, show message
if (!$reservation) {
    echo "<p>You have no pending reservations that require payment.</p>";
    exit();
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Payment</title>
</head>
<body>

    <h2>Upload Payment Proof</h2>

    <p>Reservation Details:</p>
    <ul>
        <li><strong>Check-in:</strong> <?php echo $reservation['check_in']; ?></li>
        <li><strong>Check-out:</strong> <?php echo $reservation['check_out']; ?></li>
        <li><strong>Status:</strong> <?php echo $reservation['status']; ?></li>
    </ul>

    <form action="upload_payment.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
        <label for="payment_proof">Upload Payment Proof (JPG, PNG, PDF only):</label><br>
        <input type="file" name="payment_proof" accept=".jpg, .jpeg, .png, .pdf" required><br><br>
        <button type="submit">Upload</button>
    </form>

</body>
</html>
