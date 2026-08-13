<?php
// process_booking_p2.php

// Database connection
$servername = "localhost"; // Change if necessary
$username = "root"; // Your database username
$password = " "; // Your database password
$dbname = "resort_db"; // Your database name

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Prepare and bind
$stmt = $conn->prepare("INSERT INTO publicguest_reservations (check_in_date, check_out_date, adults, children, first_name, last_name, email, contact_number, special_requests, total_amount, payment_method, transaction_number, proof_of_payment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssiiissssdsiss", $check_in_date, $check_out_date, $adults, $children, $first_name, $last_name, $email, $contact_number, $special_requests, $total_amount, $payment_method, $transaction_number, $proof_of_payment);

// Get the form data
$check_in_date = $_POST['check_in_date'];
$check_out_date = $_POST['check_out_date'];
$adults = $_POST['adults'];
$children = $_POST['children'];
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$email = $_POST['email'];
$contact_number = $_POST['contact_number'];
$special_requests = $_POST['special_requests'];
$total_amount = $_POST['total_amount'];
$payment_method = $_POST['payment_method'];
$transaction_number = $_POST['transaction_number'];

// Handle file upload for proof of payment
if (isset($_FILES['proof_of_payment']) && $_FILES['proof_of_payment']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/'; // Ensure this directory exists and is writable
    $proof_of_payment = $upload_dir . basename($_FILES['proof_of_payment']['name']);
    move_uploaded_file($_FILES['proof_of_payment']['tmp_name'], $proof_of_payment);
} else {
    // Handle error or set a default value
    $proof_of_payment = null; // Or handle the error as needed
}

// Execute the statement
if ($stmt->execute()) {
    // Success response
    $response = [
        'success' => true,
        'booking_reference' => $conn->insert_id // Get the last inserted ID
    ];
} else {
    // Error response
    $response = [
        'success' => false,
        'message' => 'Error: ' . $stmt->error
    ];
}

// Close connections
$stmt->close();
$conn->close();

// Return response as JSON
header('Content-Type: application/json');
echo json_encode($response);
?>