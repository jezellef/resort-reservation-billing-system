<?php
session_start();
include 'database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$sql = "SELECT p.id AS payment_id, p.file_path, p.status, 
        r.id AS reservation_id, r.user_id, 
        b.total_amount, u.Email, u.Firstname, u.Lastname, u.Contactnum 
        FROM payments p 
        JOIN reservations r ON p.reservation_id = r.id 
        JOIN bills b ON r.id = b.reservation_id  
        JOIN users u ON r.user_id = u.id 
        WHERE p.status = 'Pending'";

$result = $mysqli->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Payments</title>
    <link rel="stylesheet" href="css/admin_style.css">
</head>
<body>

<div class="container">
    <h2>Pending Payments</h2>

    <!-- ✅ Inserted success/error message here -->
    <?php if (isset($_SESSION['payment_message'])): ?>
        <div class="alert <?php echo ($_SESSION['payment_status'] == 'success') ? 'success' : 'error'; ?>">
            <?php echo $_SESSION['payment_message']; ?>
        </div>
        <?php unset($_SESSION['payment_message']); // Remove message after displaying ?>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Reservation ID</th>
                <th>Guest Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Total Amount</th>
                <th>Payment Proof</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo $row['reservation_id']; ?></td>
                <td><?php echo $row['Firstname'] . " " . $row['Lastname']; ?></td>
                <td><?php echo $row['Email']; ?></td>
                <td><?php echo $row['Contactnum']; ?></td>
                <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                <td>
                    <a href="<?php echo $row['file_path']; ?>" target="_blank" class="btn-view">View</a>
                </td>
                <td>
                <form action="process_payment_verification.php" method="POST" class="action-form">
                    <input type="hidden" name="payment_id" value="<?php echo $row['payment_id']; ?>">
                    <input type="hidden" name="reservation_id" value="<?php echo $row['reservation_id']; ?>">
                    <input type="hidden" name="email" value="<?php echo $row['Email']; ?>">
                    
                    <button type="submit" name="action" value="reject" class="btn reject">Reject</button>
                    <button type="submit" name="action" value="approve" class="btn approve">Approve</button>
                </form>
            </td>

            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>


</body>
</html>

<?php $mysqli->close(); ?>