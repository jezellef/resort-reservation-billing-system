<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connect.php';
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

$reservation = null;
$code = $_GET['code'] ?? null;
$type = $_GET['type'] ?? null;
$paymentStatus = 'Pending';

if ($code && $type) {
    $code = mysqli_real_escape_string($conn, $code);
    $type = mysqli_real_escape_string($conn, $type);

    if ($type === 'guest') {
        $query = "SELECT * FROM p2_guest_reservation WHERE booking_reference = '$code'";
    } elseif ($type === 'user') {
        $query = "SELECT * FROM p2_user_reservation WHERE booking_reference = '$code'";
    } else {
        $_SESSION['error_message'] = "Invalid reservation type.";
        header("Location: book.php");
        exit;
    }

    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        if ($row) {  // Check if fetch was successful
            $reservation = [
                'reservation_code' => $row['booking_reference'] ?? '',
                'first_name' => $row['first_name'] ?? '',
                'last_name' => $row['last_name'] ?? '',
                'email' => $row['email'] ?? '',
                'contact_number' => $row['contact_number'] ?? '',
                'tour_type' => $row['tour_type'] ?? '',
                'check_in' => $row['check_in'] ?? '',
                'check_out' => $row['check_out'] ?? '',
                'adult_count' => $row['adult_count'] ?? 0,
                'kid_count' => $row['kid_count'] ?? 0,
                'total_price' => $row['total_price'] ?? 0,
                'created_at' => $row['created_at'] ?? '',
                'expires_at' => isset($row['expires_at']) ? strtotime($row['expires_at']) : 0,
                'extras' => [
                    'extra_mattress' => $row['extra_mattress'] ?? 0,
                    'extra_pillow' => $row['extra_pillow'] ?? 0,
                    'extra_blanket' => $row['extra_blanket'] ?? 0,
                ]
            ];

            $_SESSION['reservation_code'] = $code;
            $_SESSION['reservation_type'] = $type;
            $_SESSION['reservation_details'] = $reservation;

            // Payment status
            if ($type === 'guest') {
                $paymentQuery = "SELECT * FROM p2_payments WHERE guest_reservation_code = '$code' ORDER BY created_at DESC LIMIT 1";
            } else {
                $paymentQuery = "SELECT * FROM p2_payments WHERE user_reservation_code = '$code' ORDER BY created_at DESC LIMIT 1";
            }

            $paymentResult = mysqli_query($conn, $paymentQuery);
            if ($paymentResult && mysqli_num_rows($paymentResult) > 0) {
                $paymentRow = mysqli_fetch_assoc($paymentResult);
                $paymentStatus = $paymentRow['status'] ?? 'Pending';
            }
        } else {
            $_SESSION['error_message'] = "Reservation data missing.";
            header("Location: book.php");
            exit;
        }
    } else {
        $_SESSION['error_message'] = "Reservation not found in database.";
        header("Location: book.php");
        exit;
    }

} elseif (isset($_SESSION['reservation_details'])) {

    $reservation = $_SESSION['reservation_details'];
    $code = $_SESSION['reservation_code'] ?? null;
    $type = $_SESSION['reservation_type'] ?? 'guest';

    if ($code) {
        if ($type === 'guest') {
            $paymentQuery = "SELECT * FROM p2_payments WHERE guest_reservation_code = '$code' ORDER BY created_at DESC LIMIT 1";
        } else {
            $paymentQuery = "SELECT * FROM p2_payments WHERE user_reservation_code = '$code' ORDER BY created_at DESC LIMIT 1";
        }

        $paymentResult = mysqli_query($conn, $paymentQuery);
        if ($paymentResult && mysqli_num_rows($paymentResult) > 0) {
            $paymentRow = mysqli_fetch_assoc($paymentResult);
            $paymentStatus = $paymentRow['status'] ?? 'Pending';
        }
    }

} else {
    $_SESSION['error_message'] = "No reservation details found. Please search using your reservation code.";
    header("Location: book.php");
    exit;
}

// Additional Safety Checks
if (!$reservation || !$code) {
    $_SESSION['error_message'] = "Incomplete reservation details.";
    header("Location: book.php");
    exit;
}

if (time() > ($reservation['expires_at'] ?? 0)) {
    $_SESSION['error_message'] = "Your reservation has expired.";
    unset($_SESSION['reservation_details']);
    header("Location: book.php");
    exit;
}
?>
<?php
// [your PHP logic above]
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Upload Proof of Payment</title>
</head>
<body>
    <h2>Reservation Code: <?php echo htmlspecialchars($reservation['reservation_code']); ?></h2>
    <p>Reservation under: <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></p>
    <p>Payment Status: <?php echo htmlspecialchars($paymentStatus); ?></p>

<?php if ($paymentStatus === 'Pending' || $paymentStatus === 'Declined'): ?>
    <form action="process_payment_upload.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="reference_code" value="<?php echo htmlspecialchars($reservation['reservation_code']); ?>">
        <input type="hidden" name="reservation_type" value="<?php echo htmlspecialchars($type); ?>">

        <label for="payment_method">Payment Method:</label>
        <input type="text" name="payment_method" id="payment_method" required><br><br>

        <label for="amount">Amount Paid:</label>
        <input type="number" name="amount" id="amount" step="0.01" required><br><br>

        <label for="proof">Upload Proof of Payment:</label>
        <input type="file" name="proof" id="proof" accept="image/*,application/pdf" required><br><br>

        <button type="submit">Submit Payment</button>
    </form>
<?php else: ?>
    <p>Your payment has already been submitted. Please wait for admin confirmation.</p>
<?php endif; ?>

</body>
</html>
