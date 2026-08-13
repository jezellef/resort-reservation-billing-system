<?php
include 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $room_id = $_POST['room_id'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $quantity = $_POST['quantity'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $contact = $_POST['contact'];
    $guests_adult = $_POST['guests_adult'];
    $guests_kids = $_POST['guests_kids'];
    $tour_type = $_POST['tour_type'];
    $extra_mattress = $_POST['extra_mattress'];
    $extra_pillow = $_POST['extra_pillow'];
    $extra_blanket = $_POST['extra_blanket'];
    $special_requests = $_POST['special_requests'];
    $transaction_number = $_POST['transaction_number'];

    // File upload handling
    $target_dir = "uploads/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    $file_name = basename($_FILES["payment_proof"]["name"]);
    $target_file = $target_dir . time() . "_" . $file_name;
    $upload_ok = move_uploaded_file($_FILES["payment_proof"]["tmp_name"], $target_file);

    if ($upload_ok) {
        $stmt = $conn->prepare("INSERT INTO public_reservations (
            room_id, check_in, check_out, status, quantity,
            reservation_code, transaction_number, file_path, name,
            email, first_name, last_name, guests_adult, guests_kids,
            tour_type, extra_mattress, extra_pillow, extra_blanket,
            special_requests
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param(
            "issssssssssiiissss", 
            $room_id, $check_in, $check_out, 
            'Pending Confirmation',  // Add status here
            $quantity, $reservation_code, $transaction_number, 
            $target_file, $first_name . ' ' . $last_name, 
            $email, $first_name, $last_name, 
            $guests_adult, $guests_kids, $tour_type, 
            $extra_mattress, $extra_pillow, $extra_blanket, 
            $special_requests
        );
        
        
        // Execute and check for errors
        if ($stmt->execute()) {
            echo "<h2>Payment Received</h2>";
            echo "<p>Thank you, $first_name. Your payment is being processed.</p>";
            echo "<p><strong>Reference No.:</strong> $transaction_number</p>";
            echo "<p>Status: <strong>Waiting for Confirmation</strong></p>";
        } else {
            echo "Database Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        echo "Error uploading payment proof.";
    }

    $conn->close();
} else {
    echo "Invalid access.";
}
?>
