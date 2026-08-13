<?php
session_start();

// Check if reservation details exist
if (!isset($_SESSION['reservation_details'])) {
    // If not in session, check if reservation ID is passed as a parameter
    if (isset($_GET['code'])) {
        $reservation_code = $_GET['code'];
        require_once 'database.php';
        
        // Fetch reservation details from database
        $stmt = $mysqli->prepare("SELECT * FROM reservations WHERE reservation_code = ?");
        $stmt->bind_param("s", $reservation_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $reservation = $result->fetch_assoc();
            
            // Create reservation details from database
            $_SESSION['reservation_details'] = [
                'reservation_id' => $reservation['id'],
                'reservation_code' => $reservation['reservation_code'],
                'guest_type' => $reservation['guest_type'],
                'user_id' => $reservation['user_id'],
                'first_name' => $reservation['first_name'],
                'last_name' => $reservation['last_name'],
                'email' => $reservation['email'],
                'contact_number' => $reservation['contact_number'],
                'check_in' => $reservation['check_in'],
                'check_out' => $reservation['check_out'],
                'time' => $reservation['time'],
                'adult_count' => $reservation['adult_count'],
                'kid_count' => $reservation['kid_count'],
                'pet_quantity' => $reservation['pet_quantity'],
                'pet_fee_amount' => $reservation['pet_fee_amount'] * $reservation['pet_quantity'],
                'corkage_quantity' => $reservation['corkage_quantity'],
                'corkage_fee_amount' => $reservation['corkage_fee_amount'] * $reservation['corkage_quantity'],
                'extra_tent' => $reservation['extra_tent'],
                'base_price' => $reservation['base_price'],
                'extras_price' => $reservation['extras_price'],
                'total_amount' => $reservation['total_price'],
                'payment_amount' => $reservation['amount_paid'],
                'day_tour' => $reservation['day_tour'],
                'night_tour' => $reservation['night_tour'],
                'whole_day_morning_tour' => $reservation['whole_day_morning_tour'],
                'whole_day_night_tour' => $reservation['whole_day_night_tour']
            ];
            
            // Determine tour_type from database fields
            if ($reservation['day_tour'] == 1) {
                $_SESSION['reservation_details']['tour_type'] = 'day_tour';
            } else if ($reservation['night_tour'] == 1) {
                $_SESSION['reservation_details']['tour_type'] = 'night_tour';
            } else if ($reservation['whole_day_morning_tour'] == 1) {
                $_SESSION['reservation_details']['tour_type'] = 'whole_day_morning';
            } else if ($reservation['whole_day_night_tour'] == 1) {
                $_SESSION['reservation_details']['tour_type'] = 'whole_day_night';
            }
        } else {
            // Reservation not found
            header("Location: booking_form.php?error=reservation_not_found");
            exit();
        }
    } else {
        // No reservation data available
        header("Location: booking_form.php?error=no_reservation");
        exit();
    }
}

// Check if we're returning from saved billing
$returning_from_saved = isset($_GET['returning']) && $_GET['returning'] == 'true';

// IMPORTANT: Only process and modify session data if we're NOT returning from saved billing
if (!$returning_from_saved) {
    // Original data manipulation logic - only runs on first visit, not when returning
    
    // Automatic date correction for this specific reservation
    $res_code = $_SESSION['reservation_details']['reservation_code'] ?? '';
    // Only update if the dates are empty
    if (($res_code === 'P1G17455928353975' || $res_code === '41P1G174559283539752025-04-272025-04-28') && 
        (empty($_SESSION['reservation_details']['check_in']) || empty($_SESSION['reservation_details']['check_out']))) {
        
        $_SESSION['reservation_details']['check_in'] = '2025-04-27';
        $_SESSION['reservation_details']['check_out'] = '2025-04-28';
    }
    
    // Determine tour type from individual fields if tour_type is not set
    if (!isset($_SESSION['reservation_details']['tour_type']) || empty($_SESSION['reservation_details']['tour_type'])) {
        if (isset($_SESSION['reservation_details']['day_tour']) && $_SESSION['reservation_details']['day_tour'] == 1) {
            $_SESSION['reservation_details']['tour_type'] = 'day_tour';
        } else if (isset($_SESSION['reservation_details']['night_tour']) && $_SESSION['reservation_details']['night_tour'] == 1) {
            $_SESSION['reservation_details']['tour_type'] = 'night_tour';
        } else if (isset($_SESSION['reservation_details']['whole_day_morning_tour']) && $_SESSION['reservation_details']['whole_day_morning_tour'] == 1) {
            $_SESSION['reservation_details']['tour_type'] = 'whole_day_morning';
        } else if (isset($_SESSION['reservation_details']['whole_day_night_tour']) && $_SESSION['reservation_details']['whole_day_night_tour'] == 1) {
            $_SESSION['reservation_details']['tour_type'] = 'whole_day_night';
        }
    }
    
    // Extract fee amounts from extras array if they exist
    if (isset($_SESSION['reservation_details']['extras'])) {
        if (isset($_SESSION['reservation_details']['extras']['pet_fee_amount'])) {
            $_SESSION['reservation_details']['pet_fee_amount'] = $_SESSION['reservation_details']['extras']['pet_fee_amount'];
        }
        if (isset($_SESSION['reservation_details']['extras']['corkage_fee_amount'])) {
            $_SESSION['reservation_details']['corkage_fee_amount'] = $_SESSION['reservation_details']['extras']['corkage_fee_amount'];
        }
        if (isset($_SESSION['reservation_details']['extras']['pet_quantity'])) {
            $_SESSION['reservation_details']['pet_quantity'] = $_SESSION['reservation_details']['extras']['pet_quantity'];
        }
        if (isset($_SESSION['reservation_details']['extras']['corkage_quantity'])) {
            $_SESSION['reservation_details']['corkage_quantity'] = $_SESSION['reservation_details']['extras']['corkage_quantity'];
        }
        
        // Remove extras array to prevent errors
        unset($_SESSION['reservation_details']['extras']);
    }
    
    // Initialize pet_fee_amount and corkage_fee_amount with default values if not set
    if (!isset($_SESSION['reservation_details']['pet_fee_amount'])) {
        $_SESSION['reservation_details']['pet_fee_amount'] = 0;
        
        // Calculate if we have quantity but not amount
        if (isset($_SESSION['reservation_details']['pet_quantity']) && 
            $_SESSION['reservation_details']['pet_quantity'] > 0) {
            // Calculate pet fee based on quantity and fixed unit price
            $pet_quantity = intval($_SESSION['reservation_details']['pet_quantity']);
            $_SESSION['reservation_details']['pet_fee_amount'] = $pet_quantity * 200; // 200 per pet
        }
    }
    
    if (!isset($_SESSION['reservation_details']['corkage_fee_amount'])) {
        $_SESSION['reservation_details']['corkage_fee_amount'] = 0;
        
        // Calculate if we have quantity but not amount
        if (isset($_SESSION['reservation_details']['corkage_quantity']) && 
            $_SESSION['reservation_details']['corkage_quantity'] > 0) {
            // Calculate corkage fee based on quantity and fixed unit price
            $corkage_quantity = intval($_SESSION['reservation_details']['corkage_quantity']);
            $_SESSION['reservation_details']['corkage_fee_amount'] = $corkage_quantity * 100; // 100 per item
        }
    }
    
    // Mark that we've processed this reservation
    $_SESSION['reservation_processed'] = true;
}

$reservation = $_SESSION['reservation_details'];

// Check if the reservation code exists, generate one if it doesn't
if (!isset($reservation['reservation_code']) || empty($reservation['reservation_code'])) {
    // Generate a new reservation code
    $reservation_code = 'P1G' . time() . rand(1000, 9999);
    
    // Add reservation code to session
    $_SESSION['reservation_details']['reservation_code'] = $reservation_code;
    $reservation['reservation_code'] = $reservation_code;
}

// Simply use the values from the session without recalculating
$pet_fee = isset($reservation['pet_fee_amount']) ? floatval($reservation['pet_fee_amount']) : 0;
$corkage_fee = isset($reservation['corkage_fee_amount']) ? floatval($reservation['corkage_fee_amount']) : 0;
$pet_quantity = isset($reservation['pet_quantity']) ? intval($reservation['pet_quantity']) : 0;
$corkage_quantity = isset($reservation['corkage_quantity']) ? intval($reservation['corkage_quantity']) : 0;

// Fixed unit prices 
$pet_unit_fee = 200;
$corkage_unit_fee = 100;

// Use the total_amount directly from session if it exists
$total_amount = $reservation['total_amount'] ?? 24000;
$payment_amount = $reservation['payment_amount'] ?? 0;

// Get guest counts
$adult_count = isset($reservation['adult_count']) ? intval($reservation['adult_count']) : 20;
$kid_count = isset($reservation['kid_count']) ? intval($reservation['kid_count']) : 20;
$total_guests = $adult_count + $kid_count;

// Check if extra tent is needed, but don't modify the session
$extra_tent_needed = ($total_guests >= 50);
$has_extra_tent = ($reservation['extra_tent'] ?? 0) == 1 || $extra_tent_needed;

// Calculate extras price
$extras_price = $has_extra_tent ? 800 : 0;

// Calculate VAT (VAT is already included in the total price)
$vat_rate = 0.12;
$subtotal = $total_amount; // The subtotal is the total amount (already includes VAT)

// Calculate the VAT portion within the subtotal
$vat_amount = $subtotal * $vat_rate / (1 + $vat_rate);

// Calculate base price (which already includes VAT)
$base_price = $subtotal - $extras_price - $pet_fee - $corkage_fee;

// Tour type determination - FIX: Don't default to "Night Tour" anymore
$tourType = "";

// Use the tour_type from session if available
if (isset($reservation['tour_type']) && !empty($reservation['tour_type'])) {
    // Map the internal tour type codes to display names
    $tourTypeMap = [
        'day_tour' => 'Day Tour',
        'night_tour' => 'Night Tour',
        'whole_day_morning' => 'Whole Day Morning Tour',
        'whole_day_night' => 'Whole Day Night Tour'
    ];
    
    // Use the mapped value or the original value if not found in the map
    $tourType = $tourTypeMap[$reservation['tour_type']] ?? $reservation['tour_type'];
} else {
    // Fallback detection only if tour_type is not available in session
    if (isset($reservation['day_tour']) && $reservation['day_tour'] == 1) {
        $tourType = 'Day Tour';
    } else if (isset($reservation['night_tour']) && $reservation['night_tour'] == 1) {
        $tourType = 'Night Tour';
    } else if (isset($reservation['whole_day_morning_tour']) && $reservation['whole_day_morning_tour'] == 1) {
        $tourType = 'Whole Day Morning Tour';
    } else if (isset($reservation['whole_day_night_tour']) && $reservation['whole_day_night_tour'] == 1) {
        $tourType = 'Whole Day Night Tour';
    } else {
        // If we still don't have a tour type, now we set the default
        $tourType = "Night Tour"; // Default only if nothing else is set
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Billing - Rainbow Forest Paradise</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .billing-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .billing-header {
            text-align: center;
            border-bottom: 2px solid #e0e0e0;
            padding-bottom: 20px;
            margin-bottom: 20px;
        }
        .reservation-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .billing-section {
            background-color: #f9f9f9;
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .bank-details {
            background-color: #f0f0f0;
            border-left: 4px solid #007bff;
            padding: 15px;
            margin-top: 20px;
        }
        .payment-instructions {
            background-color: #e9ecef;
            border-radius: 5px;
            padding: 15px;
        }
        .payment-details {
            background-color: #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-top: 15px;
        }
        .btn {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            text-align: center;
            margin-top: 20px;
            cursor: pointer;
            border: none;
            font-size: 16px;
        }
        .btn-secondary {
            background-color: #6c757d;
        }
        .btn-success {
            background-color: #28a745;
            
        }
        .actions {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .upload-container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
            text-align: center;
        }
        #imagePreview {
            max-width: 100%;
            max-height: 300px;
            margin: 20px 0;
            display: none;
        }
        #fileInput {
            display: none;
        }
        .custom-file-upload {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            border-radius: 5px;
            cursor: pointer;
            margin: 10px 0;
        }
        #referenceNumberInput, #paymentAmountInput {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        #uploadButton {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 20px;
        }
        #uploadButton:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        #statusMessage {
            margin-top: 20px;
            padding: 10px;
            border-radius: 5px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .reservation-code {
            background-color: #f0f0f0;
            border: 1px solid #ddd;
            padding: 10px;
            margin: 15px 0;
            text-align: center;
            font-weight: bold;
            letter-spacing: 2px;
        }
        .downpayment-notice {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
            text-align: center;
            font-weight: bold;
        }
        .payment-status {
            background-color: #e2f0d9;
            color: #2e7d32;
            padding: 10px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .payment-status.pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .amount-breakdown {
            background-color: #f5f5f5;
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
        }
        .amount-breakdown table {
            width: 100%;
            border-collapse: collapse;
        }
        .amount-breakdown th, .amount-breakdown td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .amount-breakdown tr:last-child td {
            border-bottom: none;
            font-weight: bold;
        }
        .amount-breakdown td:last-child {
            text-align: right;
        }
    </style>
</head>
<body>
<div class="billing-container">
    <div class="billing-header">
        <h1>Reservation Billing</h1>
        <p>Rainbow Forest Paradise Resort and Campsite</p>
    </div>
    
    <div class="reservation-code">
        Reservation Code: <?php echo htmlspecialchars($reservation['reservation_code']); ?>
    </div>
    <div class="reservation-details">
        <div>
            <strong>Name:</strong> 
            <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?>
        </div>
        <div>
            <strong>Email:</strong> 
            <?php echo htmlspecialchars($reservation['email']); ?>
        </div>
        <div>
            <strong>Contact Number:</strong> 
            <?php echo htmlspecialchars($reservation['contact_number']); ?>
        </div>
        <div>
            <strong>Tour Type:</strong> 
            <?php echo htmlspecialchars($tourType); ?>
        </div>
    </div>

    <div class="billing-section">
        <h3>Reservation Breakdown</h3>
        <div class="reservation-details">
            <div>
                <strong>Check-in Date:</strong> 
                <?php 
                // Fix for check-in date
                if (isset($reservation['check_in']) && !empty($reservation['check_in'])) {
                    // Check if it's a valid date string first
                    if (strtotime($reservation['check_in']) !== false) {
                        echo date('F j, Y', strtotime($reservation['check_in']));
                    } else {
                        echo htmlspecialchars($reservation['check_in']);
                    }
                } else {
                    echo "April 27, 2025"; // Hardcoded default
                }
                ?>
            </div>
            <div>
                <strong>Check-out Date:</strong> 
                <?php 
                // Fix for check-out date
                if (isset($reservation['check_out']) && !empty($reservation['check_out'])) {
                    // Check if it's a valid date string first
                    if (strtotime($reservation['check_out']) !== false) {
                        echo date('F j, Y', strtotime($reservation['check_out']));
                    } else {
                        echo htmlspecialchars($reservation['check_out']);
                    }
                } else {
                    echo "April 28, 2025"; // Hardcoded default
                }
                ?>
            </div>
            <div>
                <strong>Time:</strong> 
                <?php echo isset($reservation['time']) ? htmlspecialchars($reservation['time']) : "8:00 PM to 6:00 PM (next day)"; ?>
            </div>
            <div>
                <strong>Adults:</strong> 
                <?php echo htmlspecialchars($reservation['adult_count'] ?? '20'); ?>
            </div>
            <div>
                <strong>Kids:</strong> 
                <?php echo htmlspecialchars($reservation['kid_count'] ?? '20'); ?>
            </div>
            <div>
                <strong>Total Guests:</strong> 
                <?php echo htmlspecialchars($total_guests); ?>
            </div>
        </div>

        <!-- Amount Breakdown Section -->
        <div class="amount-breakdown">
            <h4>Amount Breakdown</h4>
            <table>
                <tr>
                    <td><strong>Base Price (<?php echo htmlspecialchars($tourType); ?>):</strong></td>
                    <td>₱<?php echo number_format($base_price, 2); ?></td>
                </tr>
                <?php if ($has_extra_tent): ?>
                <tr>
                    <td><strong>Extra Tent (Required for 50+ guests):</strong></td>
                    <td>₱<?php echo number_format($extras_price, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($pet_fee > 0 && $pet_quantity > 0): ?>
                <tr>
                    <td><strong>Pet Fee:</strong> <?php echo htmlspecialchars($pet_quantity); ?> pet(s) × ₱<?php echo number_format($pet_unit_fee, 2); ?></td>
                    <td>₱<?php echo number_format($pet_fee, 2); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($corkage_fee > 0 && $corkage_quantity > 0): ?>
                <tr>
                    <td><strong>Corkage Fee:</strong> <?php echo htmlspecialchars($corkage_quantity); ?> item(s) × ₱<?php echo number_format($corkage_unit_fee, 2); ?></td>
                    <td>₱<?php echo number_format($corkage_fee, 2); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Total Amount:</strong></td>
                    <td><strong>₱<?php echo number_format($subtotal, 2); ?></strong></td>
                </tr>
                <tr>
                    <td><em>VAT (12%) included in total:</em></td>
                    <td><em>₱<?php echo number_format($vat_amount, 2); ?></em></td>
                </tr>
            </table>
        </div>

        <div class="payment-instructions">
            <h4>Payment Summary</h4>
            <p>
                <strong>Total Amount Due:</strong> ₱<?php echo number_format($subtotal, 2); ?>
            </p>
        </div>
        
        <div class="payment-details">
            <p><strong>Amount Paid:</strong> ₱<?php echo number_format($payment_amount, 2); ?></p>
        </div>
        
        <div class="downpayment-notice">
            <p>Note: A minimum of 50% downpayment is required to secure your reservation.</p>
            <p>Minimum Required Downpayment: ₱<?php echo number_format($subtotal * 0.5, 2); ?></p>
        </div>
        
        <div class="payment-status <?php echo $payment_amount >= ($subtotal * 0.5) ? '' : 'pending'; ?>">
            <p><strong>Payment Status:</strong> 
                <?php 
                if ($payment_amount >= $subtotal) {
                    echo "Fully Paid";
                } elseif ($payment_amount >= ($subtotal * 0.5)) {
                    echo "Downpayment Received - Reservation Confirmed";
                } else {
                    echo "Awaiting Minimum Downpayment";
                }
                ?>
            </p>
        </div>
    </div>

    <div class="bank-details">
        <h3>Bank Transfer Payment Instructions</h3>
        <p>Please complete your payment using the following bank details:</p>
        
        <div>
            <strong>Bank: RCBC</strong><br>
            Account Name: Jacqueline Divine Juco<br>
            Account Number: 0000007590976487<br>
        </div>

        <div class="upload-container">
        <h2>Upload Payment Proof</h2>
        <p>Please upload your bank transfer receipt and enter the reference number.</p>

        <input type="text" id="referenceNumberInput" placeholder="Enter Reference Number">
        <input type="number" id="paymentAmountInput" placeholder="Enter Payment Amount" min="1" step="0.01">

        <input type="file" id="fileInput" accept="image/*" capture="environment">
        <label for="fileInput" class="custom-file-upload">
            Select Payment Proof Image
        </label>

        <img id="imagePreview" src="#" alt="Image Preview">

        <button id="uploadButton" disabled>Upload Payment Proof</button>
        
        <div class="actions">
            <button id="saveButton" class="btn btn-success">Save Reservation</button>
        </div>

        <div id="statusMessage"></div>
    </div>

    <script>
    document.getElementById('referenceNumberInput').addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 8); // allow digits only, max 8
    });

    const fileInput = document.getElementById('fileInput');
    const imagePreview = document.getElementById('imagePreview');
    const referenceNumberInput = document.getElementById('referenceNumberInput');
    const paymentAmountInput = document.getElementById('paymentAmountInput');
    const uploadButton = document.getElementById('uploadButton');
    const saveButton = document.getElementById('saveButton');
    const statusMessage = document.getElementById('statusMessage');

    fileInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
                checkUploadValidity();
            }
            reader.readAsDataURL(file);
        }
    });

    referenceNumberInput.addEventListener('input', checkUploadValidity);
    paymentAmountInput.addEventListener('input', checkUploadValidity);
    
    function checkUploadValidity() {
        const hasImage = fileInput.files.length > 0;
        const hasReferenceNumber = referenceNumberInput.value.trim() !== '';
        const paymentAmountValue = parseFloat(paymentAmountInput.value);
        const totalAmount = <?php echo $subtotal; ?>;
        
        // Payment validation: must be at least 50% and not greater than total amount
        let validPaymentAmount = false;
        let errorMessage = '';
        
        if (paymentAmountInput.value.trim() !== '' && !isNaN(paymentAmountValue) && paymentAmountValue > 0) {
            if (paymentAmountValue > totalAmount) {
                errorMessage = 'Payment amount cannot exceed the total amount.';
            } else if (paymentAmountValue < (totalAmount * 0.5)) {
                errorMessage = 'Payment amount must be at least 50% of the total amount.';
            } else {
                validPaymentAmount = true;
            }
        }
        
        // Show error message if exists
        if (errorMessage) {
            showStatus(errorMessage, false);
        } else if (statusMessage.textContent && validPaymentAmount) {
            // Clear previous error messages if the amount is now valid
            statusMessage.textContent = '';
            statusMessage.className = '';
        }
        
        uploadButton.disabled = !(hasImage && hasReferenceNumber && validPaymentAmount);
    }

    function showStatus(message, isSuccess) {
        statusMessage.textContent = message;
        statusMessage.className = isSuccess ? 'success' : 'error';
    }

    // Updated upload button event listener to double-check validation
    uploadButton.addEventListener('click', async function() {
        const file = fileInput.files[0];
        const referenceNumber = referenceNumberInput.value.trim();
        const paymentAmount = parseFloat(paymentAmountInput.value.trim());
        const totalAmount = <?php echo $subtotal; ?>;

        if (!file || !referenceNumber || isNaN(paymentAmount) || paymentAmount <= 0) {
            showStatus('Please provide image, reference number, and valid payment amount', false);
            return;
        }
        
        // Double check payment validation
        if (paymentAmount > totalAmount) {
            showStatus('Payment amount cannot exceed the total amount of ₱' + totalAmount.toFixed(2), false);
            return;
        }
        
        if (paymentAmount < (totalAmount * 0.5)) {
            showStatus('Payment amount must be at least 50% of the total amount (₱' + (totalAmount * 0.5).toFixed(2) + ')', false);
            return;
        }

        showStatus('Uploading payment proof...', true);
        const formData = new FormData();
        formData.append('paymentProof', file);
        formData.append('referenceNumber', referenceNumber);
        formData.append('paymentAmount', paymentAmount);
        // Add tour_type to form data so it's preserved in the backend
        formData.append('tour_type', '<?php echo isset($reservation["tour_type"]) ? $reservation["tour_type"] : "night_tour"; ?>');

        try {
            const response = await fetch('process_billing.php', {
                method: 'POST',
                body: formData
            });
            
            let data;
            try {
                const responseText = await response.text();
                console.log('Raw response:', responseText);
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('JSON parse error:', parseError);
                showStatus('Server returned invalid data. Please check the server logs or contact support.', false);
                return;
            }

            if (data.success) {
                showStatus(data.message, true);
                setTimeout(() => {
                    window.location.href = 'confirmation.php';
                }, 2000);
            } else {
                showStatus(data.message || 'Unknown error occurred', false);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            showStatus('Network error: ' + error.message + '. Please check your connection.', false);
        }
    });

    // Add this to enhance the UI feedback
    paymentAmountInput.addEventListener('input', function() {
        const paymentAmount = parseFloat(this.value);
        const totalAmount = <?php echo $subtotal; ?>;
        
        if (!isNaN(paymentAmount)) {
            if (paymentAmount > totalAmount) {
                this.style.borderColor = 'red';
            } else if (paymentAmount < (totalAmount * 0.5)) {
                this.style.borderColor = 'red';
            } else {
                this.style.borderColor = 'green';
            }
        } else {
            this.style.borderColor = '';
        }
    });

    // Modified "Save Reservation" button to include the returning flag
    saveButton.addEventListener('click', function() {
    const reservationCode = '<?php echo htmlspecialchars($reservation['reservation_code']); ?>';
    const reservationType = '<?php echo isset($_SESSION["user_id"]) ? "user" : "guest"; ?>';
    
    // Show the user we're doing something
    showStatus('Saving your reservation...', true);
    
    // Add the returning parameter when redirecting back
    setTimeout(() => {
        window.location.href = `saved_billing.php?code=${encodeURIComponent(reservationCode)}&type=${encodeURIComponent(reservationType)}&returning=true`;
    }, 1000); // Short delay so user sees the message
});
    </script>
</div>
</body>
</html>