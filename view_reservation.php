<?php
// Start session if it's not already started
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
require_once 'db_connect.php';

$reservation_code = $_GET['code'];
$type = $_GET['type'];

// Decide table based on reservation type (guest or user)
$table = ($type == 'guest') ? 'guest_reservation' : 'user_reservation';

// SQL query to get reservation details
$sql = "SELECT * FROM $table WHERE reservation_code = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $reservation_code);
$stmt->execute();
$result = $stmt->get_result();
$reservation = $result->fetch_assoc();

if (!$reservation) {
    die("Reservation not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reservation Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
    .go-back-btn {
            position: fixed;
            top: 30px;
            right: 20px;
            background-color: #0056b3;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        .go-back-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
<div class="container mt-4">
    <h2>Reservation Details</h2>
    <table class="table table-striped table-bordered">
    <a href="reservations_all.php">
        <button class="go-back-btn">Go Back</button>
    </a>    
        <tr>
            <th>Reservation Code</th>
            <td><?php echo $reservation['reservation_code']; ?></td>
        </tr>
        <tr>
            <th>Check-in Date</th>
            <td><?php echo $reservation['check_in']; ?></td>
        </tr>
        <tr>
            <th>Check-out Date</th>
            <td><?php echo $reservation['check_out']; ?></td>
        </tr>
        <tr>
            <th>Full Name</th>
            <td><?php echo $reservation['first_name'] . ' ' . $reservation['last_name']; ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo $reservation['email']; ?></td>
        </tr>
        <tr>
            <th>Contact Number</th>
            <td><?php echo $reservation['contact_number']; ?></td>
        </tr>
        <tr>
            <th>Adults</th>
            <td><?php echo $reservation['adult_count']; ?></td>
        </tr>
        <tr>
            <th>Kids</th>
            <td><?php echo $reservation['kid_count']; ?></td>
        </tr>
        <tr>
            <th>Tour Type</th>
            <td>
                <?php
                    $tourTypeMap = ['Whole Day', 'Day Tour', 'Night Tour'];
                    echo isset($reservation['tour_type']) ? $tourTypeMap[(int)$reservation['tour_type']] : 'N/A';
                ?>
            </td>
        </tr>

        <tr>
            <th>Total Price</th>
            <td><?php echo $reservation['total_price']; ?></td>
        </tr>

        <!-- Only show these if they exist in the reservation table -->
        <?php if (isset($reservation['extras_total'])): ?>
            <tr>
                <th>Extras Total</th>
                <td><?php echo $reservation['extras_total']; ?></td>
            </tr>
        <?php endif; ?>

        <tr>
            <th>Special Requests</th>
            <td><?php echo $reservation['special_requests']; ?></td>
        </tr>
        <tr>
            <th>Extra Mattress</th>
            <td><?php echo isset($reservation['extra_mattress']) ? $reservation['extra_mattress'] : '0'; ?></td>
        </tr>
        <tr>
            <th>Extra Pillow</th>
            <td><?php echo isset($reservation['extra_pillow']) ? $reservation['extra_pillow'] : '0'; ?></td>
        </tr>
        <tr>
            <th>Extra Blanket</th>
            <td><?php echo isset($reservation['extra_blanket']) ? $reservation['extra_blanket'] : '0'; ?></td>
        </tr>

        <!-- Only show Total Amount if it exists -->
        <?php if (isset($reservation['total_amount'])): ?>
            <tr>
                <th>Total Amount</th>
                <td><?php echo $reservation['total_amount']; ?></td>
            </tr>
        <?php endif; ?>

        <tr>
            <th>Created At</th>
            <td><?php echo $reservation['created_at']; ?></td>
        </tr>
        <tr>
            <th>Proof of Payment</th>
            <td><?php echo $reservation['proof_of_payment'] ? '<a href="'.$reservation['proof_of_payment'].'" target="_blank">View</a>' : 'Not Provided'; ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td><?php echo $reservation['status']; ?></td>
        </tr>
        <tr>
            <th>Transaction Number</th>
            <td><?php echo $reservation['transaction_number']; ?></td>
        </tr>
    </table>
</div>

</div>
</body>
</html>
