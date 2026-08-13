<?php
session_start();
include 'database.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$reservation_id = $_GET['reservation_id'];
$user_id = $_SESSION['user_id'];

// Fetch bill details
$sql = "SELECT b.*, r.check_in, r.check_out 
        FROM bills b
        JOIN reservations r ON b.reservation_id = r.id
        WHERE b.reservation_id = ? AND b.user_id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("ii", $reservation_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$bill = $result->fetch_assoc();

if (!$bill) {
    echo "Bill not found!";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Bill</title>
    <link rel="stylesheet" href="css/bill.css">
</head>
<body>

    <div class="bill-container">
        <div class="bill-header">
            <h2>Billing Details</h2>
            <div class="divider"></div>
        </div>

        <div class="bill-content">
            <p><strong>Check-in:</strong> <?php echo $bill['check_in']; ?></p>
            <p><strong>Check-out:</strong> <?php echo $bill['check_out']; ?></p>
            <p><strong>Total Amount:</strong> $<?php echo number_format($bill['total_amount'], 2); ?></p>
            <p><strong>Payment Status:</strong> <span class="status <?php echo strtolower($bill['payment_status']); ?>">
                <?php echo $bill['payment_status']; ?>
            </span></p>
        </div>

        <div class="action">
            <a href="upload_payment.php?reservation_id=<?php echo $reservation_id; ?>" class="btn">Upload Payment</a>
        </div>
    </div>

</body>
</html>


<?php
$stmt->close();
$conn->close();
?>
