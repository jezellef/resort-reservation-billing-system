<?php
session_start();
include 'db_connect.php';

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$message = "";
$status_class = "error"; // Default class

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $reservation_id = $_POST['reservation_id'];

    // Check if a payment already exists for this reservation
    $check_sql = "SELECT id FROM payments WHERE reservation_id = ? AND status = 'Pending'";
    $check_stmt = $conn->prepare($check_sql);
    if (!$check_stmt) {
        die("Database error: " . $conn->error);
    }
    $check_stmt->bind_param("i", $reservation_id);
    $check_stmt->execute();
    $check_stmt->store_result();

    if ($check_stmt->num_rows > 0) {
        $message = "A payment proof has already been uploaded for this reservation and is pending verification.";
        $check_stmt->close(); // Only close once
    } else {
        $check_stmt->close(); // Closing here is correct before proceeding

        // File upload handling
        $target_dir = "uploads/payments/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_name = basename($_FILES["payment_receipt"]["name"]);
        $file_tmp = $_FILES["payment_receipt"]["tmp_name"];
        $file_type = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        // Allowed file types
        $allowed_types = array("jpg", "jpeg", "png", "pdf");
        if (!in_array($file_type, $allowed_types)) {
            $message = "Error: Invalid file type. Only JPG, PNG, and PDF are allowed.";
        } else {
            // Rename file to prevent conflicts
            $new_file_name = "payment_" . time() . "_" . $user_id . "." . $file_type;
            $target_file = $target_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $target_file)) {
                // Insert payment record into database
                $sql = "INSERT INTO payments (reservation_id, user_id, file_path, status) VALUES (?, ?, ?, 'Pending')";
                $stmt = $conn->prepare($sql);
                
                if ($stmt) { // Ensure $stmt is initialized before using it
                    $stmt->bind_param("iis", $reservation_id, $user_id, $target_file);

                    if ($stmt->execute()) {
                        $message = "Payment uploaded successfully! Waiting for admin verification.";
                        $status_class = "success"; // Green success class
                    } else {
                        $message = "Database error: Could not save payment.";
                    }

                    $stmt->close(); // Close only if stmt was initialized
                } else {
                    $message = "Database error: " . $conn->error;
                }
            } else {
                $message = "Error uploading file.";
            }
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Status</title>
    <link rel="stylesheet" href="css/payment_process.css">
</head>
<body>

<div class="status-container">
    <div class="status-box <?php echo $status_class; ?>">
        <h2>Payment Status</h2>
        <p><?php echo $message; ?></p>
        <a href="reservation_status.php" class="btn">Check your Reservation Status</a>
    </div>
</div>

</body>
</html>
