<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access. Please log in.");
}

$user_id = $_SESSION['user_id'];

// Fetch reservation ID of the latest pending reservation
$sql = "SELECT id FROM reservations WHERE user_id = ? AND status = 'Pending' ORDER BY id DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$reservation = $result->fetch_assoc();
$stmt->close();

if (!$reservation) {
    die("No pending reservation found.");
}

$reservation_id = $reservation['id'];

// Fetch billing details from the bills table
$sql = "SELECT total_amount FROM bills WHERE reservation_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$result = $stmt->get_result();
$bill = $result->fetch_assoc();
$stmt->close();

if (!$bill) {
    die("No bill found for this reservation.");
}

$total_amount = $bill['total_amount'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Payment</title>
    <link rel="stylesheet" href="css/upload_payment.css">
</head>
<body>

    <div class="payment-container">
        <div class="payment-header">
            <h2>Upload Proof of Payment</h2>
            <div class="divider"></div>
        </div>

        <p class="amount">Total Amount Due: <strong>$<?php echo number_format($total_amount, 2); ?></strong></p>

        <form action="process_payment_upload.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="reservation_id" value="<?php echo $reservation_id; ?>">

            <!-- File Upload Section -->
            <div class="file-upload">
                <label for="payment_receipt" class="file-label">
                    Choose File (JPG, PNG, PDF)
                </label>
                <input type="file" id="payment_receipt" name="payment_receipt" accept=".jpg,.jpeg,.png,.pdf" required onchange="previewFile()">
                
                <!-- File Name Display -->
                <p id="file-name">No file selected</p>

                <!-- Image Preview -->
                <img id="image-preview" style="display: none; width: 100%; max-height: 200px; margin-top: 10px; border-radius: 6px;">
            </div>

            <button type="submit" class="btn">Submit Payment</button>
        </form>
    </div>

    <script>
        function previewFile() {
            const fileInput = document.getElementById("payment_receipt");
            const fileNameDisplay = document.getElementById("file-name");
            const imagePreview = document.getElementById("image-preview");
            
            const file = fileInput.files[0];
            if (file) {
                fileNameDisplay.textContent = file.name; // Show file name
                
                if (file.type.startsWith("image/")) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.style.display = "block"; // Show image preview
                    };
                    reader.readAsDataURL(file);
                } else {
                    imagePreview.style.display = "none"; // Hide preview for non-image files
                }
            } else {
                fileNameDisplay.textContent = "No file selected";
                imagePreview.style.display = "none";
            }
        }
    </script>

</body>
</html>


