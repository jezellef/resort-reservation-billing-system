<?php
session_start();
include 'db_connect.php';

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the logged-in user's ID from session
$user_id = $_SESSION['user_id'];

// Fetch reservations for the logged-in user
$sql = "SELECT * FROM reservations WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations</title>
    <link rel="stylesheet" href="status.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
<div class="container">
    <h2>My Reservations</h2>
    <table>
        <thead>
            <tr>
                <th>Check-in</th>
                <th>Check-out</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()) { ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['check_in']); ?></td>
                    <td><?php echo htmlspecialchars($row['check_out']); ?></td>
                    <td class="status <?php echo strtolower($row['status']); ?>">
                        <?php echo htmlspecialchars($row['status']); ?>
                    </td>
                    <td>
                        <?php if ($row['status'] == 'Pending') { ?>
                            <button class="btn cancel-btn" data-id="<?php echo $row['id']; ?>">Cancel</button>
                        <?php } elseif ($row['status'] == 'Confirmed') { ?>
                            <span class="confirmed">✔ Confirmed</span>
                        <?php } elseif ($row['status'] == 'Cancelled') { ?>
                            <span class="cancelled">❌ Cancelled</span>
                        <?php } else { ?>
                            <span class="na">N/A</span>
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>

<script>
    $(document).ready(function(){
        $(".cancel-btn").click(function(){
            var reservationId = $(this).data("id");

            if (confirm("Are you sure you want to cancel this reservation?")) {
                $.ajax({
                    url: "cancel_reservation.php",
                    type: "POST",
                    data: { reservation_id: reservationId },
                    success: function(response) {
                        alert(response);
                        location.reload();
                    }
                });
            }
        });
    });
</script>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
