<?php
session_start();

// Check if the reservation information exists in the session
if (!isset($_SESSION['guest_reservation'])) {
    // Redirect to the home page if no reservation info found
    header("Location: home_p1.php");
    exit;
}

// Get the reservation details from the session
$reservation = $_SESSION['guest_reservation'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Confirmation - Rainbow Forest Paradise</title>
    <link rel="stylesheet" href="reservation.css">
    <style>
        .confirmation-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .confirmation-header {
            margin-bottom: 30px;
        }
        
        .confirmation-header h2 {
            color: #4caf50;
            margin-bottom: 10px;
        }
        
        .reservation-details {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            text-align: left;
            margin-bottom: 30px;
        }
        
        .reservation-details p {
            margin: 10px 0;
            font-size: 16px;
        }
        
        .reservation-code {
            font-size: 24px;
            color: #03624c;
            font-weight: bold;
            padding: 15px;
            background-color: #eaffef;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .buttons {
            margin-top: 30px;
            align: center;
        }
        
        .btn {
            padding: 12px 24px;
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            margin: 0 10px;
        }
        
        .btn-secondary {
            background-color: #03624c;
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-header">
            <h2>Reservation Confirmed!</h2>
            <p>Thank you for choosing Rainbow Forest Paradise Resort and Campsite</p>
        </div>
        
        <div class="reservation-details">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($reservation['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($reservation['email']); ?></p>
            <p><strong>Check-in Date:</strong> <?php echo isset($reservation['check_in_date']) ? htmlspecialchars($reservation['check_in_date']) : 'N/A'; ?></p>
            <p><strong>Check-out Date:</strong> <?php echo isset($reservation['check_out_date']) ? htmlspecialchars($reservation['check_out_date']) : 'N/A'; ?></p>
        </div>
        
        <div class="reservation-code-container">
            <p>Your Reservation Code:</p>
            <div class="reservation-code"><?php echo htmlspecialchars($reservation['reservation_code']); ?></div>
            <p>Please save this code for your records.</p>
        </div>
        
        <p>A confirmation email has been sent to your email address. Please check your inbox.</p>
        
        <div class="buttons">
            <a href="home_p1.php" class="btn">Return to Home</a>
            <a href="#" onclick="window.print(); return false;" class="btn btn-secondary">Print Confirmation</a>
        </div>
    </div>
</body>
</html>
<?php
// Clear the reservation data from the session after displaying the confirmation
unset($_SESSION['guest_reservation']);
?>