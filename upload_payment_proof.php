<?php
session_start();
$mysqli = require __DIR__ . "/database.php";

// Get reservation code from the URL
$reservation_code = $_GET['reservation_code'] ?? null;
if (!$reservation_code) {
    echo "Reservation code is missing.";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if the file was uploaded correctly
    if (isset($_FILES['payment_image']) && $_FILES['payment_image']['error'] == 0) {
        $file_tmp = $_FILES['payment_image']['tmp_name'];
        $file_name = $_FILES['payment_image']['name'];
        $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);

        // Ensure the file is an image
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array(strtolower($file_ext), $allowed_extensions)) {
            // Define the target directory to upload
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            // Generate a unique file name to prevent conflicts
            $unique_file_name = uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $unique_file_name;

            // Move the file to the target directory
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Get the reference number and payment method
                $reference_number = $_POST['reference_number'];
                $payment_method = $_POST['payment_method'];

                // Update the database with the payment information
                $stmt = $mysqli->prepare("
                    UPDATE publicguest_reservations 
                    SET transaction_number = ?, proof_of_payment = ?, payment_method = ? 
                    WHERE reservation_code = ?
                ");
                $stmt->bind_param("ssss", $reference_number, $upload_path, $payment_method, $reservation_code);
                
                if ($stmt->execute()) {
                    echo "Payment proof uploaded successfully!";
                    // Redirect to a success page or confirmation
                    // After successful payment update
                    header("Location: confirmation_public.php?reservation_code=" . urlencode($reservation_code));
                    exit;

                } else {
                    echo "Error updating reservation: " . $stmt->error;
                }
            } else {
                echo "Error uploading file. Please try again.";
            }
        } else {
            echo "Invalid file type. Only images are allowed.";
        }
    } else {
        echo "No file uploaded or an error occurred during the file upload.";
    }
}
?>
