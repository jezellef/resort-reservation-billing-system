<?php
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 0); // Change to 0 in production
error_reporting(E_ALL);

// Set JSON content type for all responses
header('Content-Type: application/json');

// Validate session data
if (!isset($_SESSION['reservation_details']) || 
    !isset($_POST['referenceNumber']) || 
    !isset($_FILES['paymentProof']) || 
    !isset($_POST['paymentAmount'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required information']);
    exit;
}

if (!isset($_SESSION['reservation_details']['tour_type']) || empty($_SESSION['reservation_details']['tour_type'])) {
    // Determine tour type from individual fields if tour_type is not set
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

// Connect to database
try {
    $mysqli = require 'database.php';
    
    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'System error. Please try again later.']);
    exit;
}

// Extract and sanitize data
$reservation = $_SESSION['reservation_details'];
$reference_number = trim($_POST['referenceNumber']);
$payment_amount = floatval($_POST['paymentAmount']);
$reservation_code = trim($reservation['reservation_code'] ?? '');

// Validate reservation code
if (empty($reservation_code)) {
    echo json_encode(['success' => false, 'message' => 'Reservation details are invalid or missing.']);
    exit;
}

// Get total amount, defaulting to 24000 if not set
$total_amount = isset($reservation['total_amount']) ? floatval($reservation['total_amount']) : 
                (isset($reservation['total_price']) ? floatval($reservation['total_price']) : 24000);

// Validate reference number format
if (empty($reference_number) || strlen($reference_number) < 4) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid reference number']);
    exit;
}

// Validate payment amount
if ($payment_amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Payment amount must be greater than zero']);
    exit;
} elseif ($payment_amount < ($total_amount * 0.5)) {
    echo json_encode(['success' => false, 'message' => 'Payment amount must be at least 50% of the total amount']);
    exit;
} elseif ($payment_amount > $total_amount) {
    echo json_encode(['success' => false, 'message' => 'Payment amount cannot exceed the total amount']);
    exit;
}

// Determine which table to use based on user login status
$is_user_logged_in = isset($_SESSION['user_id']);
$reservation_table = $is_user_logged_in ? 'user_reservation' : 'guest_reservation';
$reservation_id_column = 'reservation_code'; // Same for both tables
$payment_reservation_column = $is_user_logged_in ? 'user_reservation_code' : 'guest_reservation_code';
$user_id_value = $is_user_logged_in ? $_SESSION['user_id'] : 0;

// Check if reservation is expired
$current_time = time();
if (isset($reservation['expires_at']) && $current_time > $reservation['expires_at']) {
    echo json_encode(['success' => false, 'message' => 'Your reservation has expired. Please create a new reservation.']);
    exit;
}

// Set new expiration time (48 hours)
$new_expires_at = time() + (48 * 60 * 60);

try {
    // Start transaction
    $mysqli->begin_transaction();

    // 1. Update reservation expiration time
    $stmt = $mysqli->prepare("UPDATE $reservation_table SET expires_at = ? WHERE $reservation_id_column = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement");
    }

    $stmt->bind_param("is", $new_expires_at, $reservation_code);
    if (!$stmt->execute()) {
        throw new Exception("Failed to update reservation");
    }
    $stmt->close();

    // Update session expiration
    $_SESSION['reservation_details']['expires_at'] = $new_expires_at;

    // 2. Handle file upload
    $upload_dir = 'uploads/payment_proofs/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }
    
    // Check if directory is writable
    if (!is_writable($upload_dir)) {
        throw new Exception('Upload directory is not writable');
    }
    
    // Validate the uploaded file
    if ($_FILES['paymentProof']['error'] != UPLOAD_ERR_OK) {
        throw new Exception('Error uploading file: ' . $_FILES['paymentProof']['error']);
    }
    
    // Check allowed file types
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    if (!in_array($_FILES['paymentProof']['type'], $allowed_types)) {
        throw new Exception('Only JPG, JPEG, PNG & GIF files are allowed.');
    }
    
    // Check file size (max 5MB)
    if ($_FILES['paymentProof']['size'] > 5000000) {
        throw new Exception('Sorry, your file is too large. Maximum size is 5MB.');
    }
    
    // Generate filename and move file
    $file_name = $reservation_code . '_' . time() . '_' . basename($_FILES['paymentProof']['name']);
    $target_file = $upload_dir . $file_name;
    
    if (!move_uploaded_file($_FILES['paymentProof']['tmp_name'], $target_file)) {
        throw new Exception('Failed to move uploaded file.');
    }
    
    // 3. Process payment record
    // Check for existing payment
    $check_stmt = $mysqli->prepare("
        SELECT id, amount_paid 
        FROM payments 
        WHERE $payment_reservation_column = ?
    ");
    
    if (!$check_stmt) {
        throw new Exception("Database prepare error");
    }
    
    $check_stmt->bind_param("s", $reservation_code);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    $total_paid = 0;
    
    if ($check_result->num_rows > 0) {
        // Update existing payment
        $payment_record = $check_result->fetch_assoc();
        $existing_amount = isset($payment_record['amount_paid']) ? floatval($payment_record['amount_paid']) : 0;
        $total_paid = $existing_amount + $payment_amount;
        
        $update_stmt = $mysqli->prepare("
            UPDATE payments 
            SET status = 'Approved',
                file_path = ?,
                amount_paid = ?,
                reference_number = ?
            WHERE $payment_reservation_column = ?
        ");
        
        if (!$update_stmt) {
            throw new Exception("Database prepare error");
        }
        
        $update_stmt->bind_param("sdss", $target_file, $total_paid, $reference_number, $reservation_code);
        
        if (!$update_stmt->execute()) {
            throw new Exception("Payment update error");
        }
        
        $update_stmt->close();
    } else {
        // Insert new payment
        $total_paid = $payment_amount;
        
        if ($is_user_logged_in) {
            $insert_stmt = $mysqli->prepare("
                INSERT INTO payments 
                (user_id, user_reservation_code, file_path, status, amount_paid, reference_number)
                VALUES (?, ?, ?, 'Approved', ?, ?)
            ");
        } else {
            $insert_stmt = $mysqli->prepare("
                INSERT INTO payments 
                (user_id, guest_reservation_code, file_path, status, amount_paid, reference_number)
                VALUES (?, ?, ?, 'Approved', ?, ?)
            ");
        }
        
        if (!$insert_stmt) {
            throw new Exception("Database prepare error");
        }
        
        $insert_stmt->bind_param("issds", $user_id_value, $reservation_code, $target_file, $payment_amount, $reference_number);
        
        if (!$insert_stmt->execute()) {
            throw new Exception("Payment insert error");
        }
        
        $insert_stmt->close();
    }
    
    $check_stmt->close();
    
    // 4. Determine payment status
    $payment_status = 'Pending';
    
    if ($total_paid >= $total_amount) {
        $payment_status = 'Fully Paid';
    } elseif ($total_paid >= ($total_amount * 0.5)) {
        $payment_status = 'Downpayment Received';
    }
    
    // 5. Update reservation payment_status only, keep status as 'Pending'
    $status_stmt = $mysqli->prepare("
        UPDATE $reservation_table 
        SET payment_status = ?
        WHERE $reservation_id_column = ?
    ");
    
    if (!$status_stmt) {
        throw new Exception("Database prepare error");
    }
    
    $status_stmt->bind_param("ss", $payment_status, $reservation_code);
    
    if (!$status_stmt->execute()) {
        throw new Exception("Status update error");
    }
    
    $status_stmt->close();
    
    // 6. Update session variables
    $_SESSION['reservation_details']['payment_status'] = $payment_status;
    $_SESSION['reservation_details']['payment_amount'] = $total_paid;
    $_SESSION['reservation_details']['reference_number'] = $reference_number;
    
    // Don't change the status in session either, keep it as is
    
    // Commit transaction
    $mysqli->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Payment proof uploaded successfully. Redirecting to confirmation page...',
        'payment_amount' => $total_paid,
        'payment_status' => $payment_status,
        'tour_type' => $_SESSION['reservation_details']['tour_type'] ?? 'night_tour' // Include tour type in response
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $mysqli->rollback();
    
    // Log detailed error for server-side debugging
    error_log("Payment processing error: " . $e->getMessage());

    // Return user-friendly error message
    echo json_encode(['success' => false, 'message' => 'Error processing your payment: ' . $e->getMessage()]);
}
?>