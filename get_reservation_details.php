<?php
include 'db_connect.php';

// Security measures
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['error' => 'Invalid reservation ID']);
    exit;
}

$id = intval($_GET['id']);

// Get reservation details
$query = "SELECT * FROM reservations WHERE id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo json_encode(['error' => 'Reservation not found']);
    exit;
}

$reservation = $result->fetch_assoc();

// Get payment history
$paymentsQuery = "SELECT * FROM paymentscheck WHERE reservation_id = ? ORDER BY payment_date DESC";
$paymentsStmt = $conn->prepare($paymentsQuery);
$paymentsStmt->bind_param("i", $id);
$paymentsStmt->execute();
$paymentsResult = $paymentsStmt->get_result();

$payments = [];
while ($payment = $paymentsResult->fetch_assoc()) {
    $payments[] = $payment;
}

$reservation['payments'] = $payments;

// Return JSON response
echo json_encode($reservation);