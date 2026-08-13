<?php
include 'db.php';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $room_id = $_POST['room_id'];
    $check_in = $_POST['check_in'];
    $check_out = $_POST['check_out'];
    $quantity = $_POST['quantity'];
    $total_price = $_POST['total_price'];
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
    $roomQuery = $conn->query("SELECT * FROM rooms WHERE id = '$room_id'");
    $room = $roomQuery->fetch_assoc();
    if (!$room) {
        echo "Room not found.";
        exit;
    }
} else {
    echo "Invalid request.";
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Billing & Payment</title>
    <style>
    body {
        font-family: Arial, sans-serif;
        background: #f2f2f2;
        margin: 0;
        padding: 20px;
    }

    .container {
        max-width: 900px;
        background: #fff;
        padding: 30px;
        margin: auto;
        border-radius: 10px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    h2, h3 {
        text-align: center;
        color: #333;
        margin-bottom: 20px;
    }

    .details-grid {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .column {
        flex: 1;
        min-width: 300px;
    }

    .info-group {
        background: #f7f7f7;
        border-left: 4px solid #007bff;
        padding: 10px;
        margin-bottom: 15px;
    }

    .info-group strong {
        color: #333;
    }

    form {
        margin-top: 30px;
    }

    label {
        font-weight: bold;
        display: block;
        margin-top: 15px;
    }

    input[type="text"],
    input[type="file"] {
        width: 100%;
        padding: 10px;
        margin-top: 5px;
        border: 1px solid #ccc;
        border-radius: 6px;
    }

    button {
        background-color: #007bff;
        color: white;
        padding: 12px 20px;
        margin-top: 20px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 16px;
    }

    button:hover {
        background-color: #0056b3;
    }

    ul {
        list-style: none;
        padding: 0;
        margin: 0 0 15px 0;
    }

    ul li {
        margin-bottom: 5px;
    }
    .bank-grid {
        display: flex;
        gap: 30px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    .bank-box {
        flex: 1;
        min-width: 250px;
        background: #f8f9fa;
        border-left: 4px solid #28a745;
        padding: 15px;
        border-radius: 6px;
    }
</style>
</head>
<body>
<div class="container">
    <h2>Payment Details</h2>
    <div class="details-grid">
        <div class="column">
            <div class="info-group"><strong>Name:</strong> <?php echo htmlspecialchars($last_name . ', ' . $first_name); ?></div>
            <div class="info-group"><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></div>
            <div class="info-group"><strong>Contact:</strong> <?php echo htmlspecialchars($contact); ?></div>
            <div class="info-group"><strong>Guests:</strong> <?php echo "$guests_adult Adult(s), $guests_kids Kid(s)"; ?></div>
            <div class="info-group"><strong>Tour Type:</strong> <?php echo htmlspecialchars($tour_type); ?></div>
        </div>
        <div class="column">
            <div class="info-group"><strong>Room:</strong> <?php echo htmlspecialchars($room['name']); ?></div>
            <div class="info-group"><strong>Check-in:</strong> <?php echo $check_in; ?></div>
            <div class="info-group"><strong>Check-out:</strong> <?php echo $check_out; ?></div>
            <div class="info-group"><strong>Quantity:</strong> <?php echo $quantity; ?></div>
            <div class="info-group"><strong>Total Price:</strong> ₱<?php echo $total_price; ?></div>
        </div>
    </div>
    <div class="info-group"><strong>Extras:</strong> Mattress: <?php echo $extra_mattress; ?> | Pillow: <?php echo $extra_pillow; ?> | Blanket: <?php echo $extra_blanket; ?></div>
    <div class="info-group"><strong>Special Requests:</strong> <?php echo nl2br(htmlspecialchars($special_requests)); ?></div>
    <hr>
    <h3>Bank Transfer Payment Instructions</h3>
    <p>Please complete your payment using the following bank details:</p>
    <div class="bank-grid">
        <div class="bank-box">
            <h4>RCBC</h4>
            <p><strong>Account Name:</strong> Rainbow Forest Paradise</p>
            <p><strong>Account Number:</strong> 123-456-7890</p>
        </div>
        <div class="bank-box">
            <h4>GCash</h4>
            <p><strong>Account Name:</strong> Rainbow Forest Paradise</p>
            <p><strong>GCash Number:</strong> 0917-123-4567</p>
        </div>
    </div>
    <p><strong>Upload Payment Proof</strong><br>Please upload your bank transfer receipt and enter the reference number.</p>
    <form action="payment_received.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="room_id" value="<?php echo $room_id; ?>">
    <input type="hidden" name="check_in" value="<?php echo $check_in; ?>">
    <input type="hidden" name="check_out" value="<?php echo $check_out; ?>">
    <input type="hidden" name="quantity" value="<?php echo $quantity; ?>">
    <input type="hidden" name="total_price" value="<?php echo $total_price; ?>">
    <input type="hidden" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>">
    <input type="hidden" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>">
    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
    <input type="hidden" name="contact" value="<?php echo htmlspecialchars($contact); ?>">
    <input type="hidden" name="guests_adult" value="<?php echo $guests_adult; ?>">
    <input type="hidden" name="guests_kids" value="<?php echo $guests_kids; ?>">
    <input type="hidden" name="tour_type" value="<?php echo $tour_type; ?>">
    <input type="hidden" name="extra_mattress" value="<?php echo $extra_mattress; ?>">
    <input type="hidden" name="extra_pillow" value="<?php echo $extra_pillow; ?>">
    <input type="hidden" name="extra_blanket" value="<?php echo $extra_blanket; ?>">
    <input type="hidden" name="special_requests" value="<?php echo htmlspecialchars($special_requests); ?>">
    
    <!-- Add the transaction_number input here -->
    <label for="transaction_number">Transaction Number:</label>
    <input type="text" name="transaction_number" required>

    <label for="payment_proof">Upload Payment Proof (Image):</label>
    <input type="file" name="payment_proof" accept="image/*" required>
    
    <button type="submit">Submit Payment</button>
</form>


</div>

</body>
</html>
