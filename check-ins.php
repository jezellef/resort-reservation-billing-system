<?php
include 'db_connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);
$operationSuccess = false;
$operationMessage = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkin_id'])) {
    $id = intval($_POST['checkin_id']);
    $additionalPayment = isset($_POST['additional_payment']) ? floatval($_POST['additional_payment']) : 0;
    $paymentMethod = isset($_POST['payment_method']) ? $conn->real_escape_string($_POST['payment_method']) : 'Cash';
    $paymentNotes = isset($_POST['payment_notes']) ? $conn->real_escape_string($_POST['payment_notes']) : '';
    
    $checkDateQuery = "SELECT r.check_in, r.id, rr.room_id, rr.quantity_booked 
                  FROM reservations r 
                  LEFT JOIN reservation_room rr ON r.id = rr.reservation_id
                  WHERE r.id = $id";                
    $dateResult = $conn->query($checkDateQuery);
    
    if ($dateResult->num_rows == 0) {
        echo "error_not_found";
        exit;
    }
    
    $dateRow = $dateResult->fetch_assoc();
    $checkInDate = $dateRow['check_in'];
    $today = date('Y-m-d');
    
    // NEW VALIDATION: Check if check-in date is exactly today
    if ($checkInDate !== $today) {
        echo "error_not_today";
        exit;
    }
    
    $checkInDateTime = new DateTime($checkInDate);
    $todayDateTime = new DateTime($today);
    
    if ($checkInDateTime < $todayDateTime) {
        echo "error_past_date";
        exit;
    }
    
    $result = $conn->query("SELECT amount_paid, total_price, reservation_code FROM reservations WHERE id = $id");
    $row = $result->fetch_assoc();
    $amountPaid = floatval($row['amount_paid']);
    $totalPrice = floatval($row['total_price']);
    $reservationCode = $row['reservation_code'];
    
    $newAmountPaid = $amountPaid + $additionalPayment;
    $remainingBalance = $totalPrice - $amountPaid; // Current remaining balance before new payment
    
    // NEW VALIDATION: Check if payment amount is sufficient to cover remaining balance
    if ($additionalPayment < $remainingBalance) {
        echo "error_insufficient_payment";
        exit;
    }
    
    $finalRemainingBalance = $totalPrice - $newAmountPaid;
    $paymentStatus = ($newAmountPaid >= $totalPrice) ? 'Paid' : 'Partial';
    
    $conn->begin_transaction();
    try {
        $update = $conn->query("
            UPDATE reservations 
            SET 
                status = 'Checked In', 
                checkin_status = 'Checked In', 
                payment_status = '$paymentStatus',
                amount_paid = $newAmountPaid,
                balance_due = $finalRemainingBalance,
                checkin_time = NOW()
            WHERE id = $id
        ");
        
        if (!$update) {
            throw new Exception("Error updating reservation status: " . $conn->error);
        }
        
        // Record payment in payments table if additional payment was made
        if ($additionalPayment > 0) {
            $paymentInsert = $conn->query("
                INSERT INTO paymentscheck (reservation_id, reservation_codes, amount, payment_method, payment_date, payment_notes, payment_type) 
                VALUES ($id, '$reservationCode', $additionalPayment, '$paymentMethod', NOW(), '$paymentNotes', 'Check-in Payment')
            ");
            
            if (!$paymentInsert) {
                throw new Exception("Error recording payment: " . $conn->error);
            }
        }

        $roomsQuery = "SELECT room_id, quantity_booked FROM reservation_room WHERE reservation_id = $id";
        $roomsResult = $conn->query($roomsQuery);
        
        if (!$roomsResult) {
            throw new Exception("Error retrieving reservation rooms: " . $conn->error);
        }
        
        if ($roomsResult->num_rows == 0) {
            $conn->commit();
            echo "success_no_rooms";
            exit;
        }
        
        while ($roomRow = $roomsResult->fetch_assoc()) {
            $roomId = $roomRow['room_id'];
            $roomQuantity = $roomRow['quantity_booked'];
            
            $currentQuantityQuery = "SELECT quantity FROM rooms WHERE id = $roomId";
            $currentQuantityResult = $conn->query($currentQuantityQuery);
            
            if (!$currentQuantityResult || $currentQuantityResult->num_rows == 0) {
                throw new Exception("Room ID $roomId not found");
            }
            
            $currentQuantityRow = $currentQuantityResult->fetch_assoc();
            $currentQuantity = $currentQuantityRow['quantity'];
            
            if ($currentQuantity < $roomQuantity) {
                throw new Exception("Not enough rooms available for Room ID $roomId. Required: $roomQuantity, Available: $currentQuantity");
            }
            
            $newQuantity = $currentQuantity - $roomQuantity;
            $updateRoomQuery = "UPDATE rooms SET quantity = $newQuantity WHERE id = $roomId";
            $updateRoomResult = $conn->query($updateRoomQuery);
            
            if (!$updateRoomResult) {
                throw new Exception("Error updating room quantity: " . $conn->error);
            }
            
            if ($newQuantity == 0) {
                $updateStatusQuery = "UPDATE rooms SET status = 'Unavailable' WHERE id = $roomId";
                $conn->query($updateStatusQuery);
            }
        }
        
        $conn->commit();
        echo "success";
    } catch (Exception $e) {
        $conn->rollback();
        echo "error:" . $e->getMessage();
    }
    exit;
}

// HANDLE CHECK-OUT PROCESS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout_id'])) {
    $id = intval($_POST['checkout_id']);
    
    $hasDamage = isset($_POST['has_damage']) ? 1 : 0;
    $damageDescription = $hasDamage ? $conn->real_escape_string($_POST['damage_description'] ?? '') : '';
    $damageFee = $hasDamage ? (isset($_POST['damage_fee']) ? floatval($_POST['damage_fee']) : 0) : 0;
    
    $additionalItems = isset($_POST['additional_items']) ? $conn->real_escape_string($_POST['additional_items']) : '';
    $additionalFee = isset($_POST['additional_fee']) ? floatval($_POST['additional_fee']) : 0;
    $additionalPayment = isset($_POST['additional_payment']) ? floatval($_POST['additional_payment']) : 0;
    $paymentMethod = isset($_POST['payment_method']) ? $conn->real_escape_string($_POST['payment_method']) : 'Cash';
    
    $conn->begin_transaction();
    
    try {
        $reservationQuery = "SELECT total_price, amount_paid, reservation_code FROM reservations WHERE id = $id";
        $reservationResult = $conn->query($reservationQuery);
        if (!$reservationResult || $reservationResult->num_rows == 0) {
            throw new Exception("Reservation not found");
        }
        $reservationData = $reservationResult->fetch_assoc();
        $currentTotalPrice = floatval($reservationData['total_price']);
        $currentAmountPaid = floatval($reservationData['amount_paid']);
        $reservationCode = $reservationData['reservation_code'];
        $newTotalPrice = $currentTotalPrice + $damageFee + $additionalFee;
        $newAmountPaid = $currentAmountPaid + $additionalPayment;
        $finalBalance = $newTotalPrice - $newAmountPaid;
        $paymentStatus = ($finalBalance <= 0) ? 'Paid' : 'Partial';
        $update = $conn->query("
            UPDATE reservations 
            SET 
                status = 'Checked Out',
                checkout_status = 'Checked Out',
                checkout_time = NOW(),
                has_damage = $hasDamage,
                damage_description = '$damageDescription',
                damage_fee = $damageFee,
                additional_items = '$additionalItems',
                additional_fee = $additionalFee,
                total_price = $newTotalPrice,
                amount_paid = $newAmountPaid,
                balance_due = $finalBalance,
                payment_status = '$paymentStatus'
            WHERE id = $id
        ");
        if (!$update) {
            throw new Exception("Error updating reservation status: " . $conn->error);
        }
        if ($additionalPayment > 0) {
            $paymentNotes = "Checkout payment";
            if ($damageFee > 0) $paymentNotes .= " including damage fee";
            if ($additionalFee > 0) $paymentNotes .= " including additional charges";
            $paymentInsert = $conn->query("
                INSERT INTO paymentscheck (reservation_id, reservation_codes, amount, payment_method, payment_date, payment_notes, payment_type) 
                VALUES ($id, '$reservationCode', $additionalPayment, '$paymentMethod', NOW(), '$paymentNotes', 'Check-out Payment')
            ");
            if (!$paymentInsert) {
                throw new Exception("Error recording payment: " . $conn->error);
            }
        }
        $roomsQuery = "SELECT room_id, quantity_booked FROM reservation_room WHERE reservation_id = $id";
        $roomsResult = $conn->query($roomsQuery);
        if (!$roomsResult) {
            throw new Exception("Error retrieving reservation rooms: " . $conn->error);
        }
        while ($roomRow = $roomsResult->fetch_assoc()) {
            $roomId = $roomRow['room_id'];
            $roomQuantity = $roomRow['quantity_booked'];
            $currentQuantityQuery = "SELECT quantity, status FROM rooms WHERE id = $roomId";
            $currentQuantityResult = $conn->query($currentQuantityQuery);
            if (!$currentQuantityResult || $currentQuantityResult->num_rows == 0) {
                throw new Exception("Room ID $roomId not found");
            }
            $currentQuantityRow = $currentQuantityResult->fetch_assoc();
            $currentQuantity = $currentQuantityRow['quantity'];
            $newQuantity = $currentQuantity + $roomQuantity;
            $updateRoomQuery = "UPDATE rooms SET quantity = $newQuantity, status = 'Available' WHERE id = $roomId";
            $updateRoomResult = $conn->query($updateRoomQuery);
            if (!$updateRoomResult) {
                throw new Exception("Error updating room quantity: " . $conn->error);
            }
        }
        $conn->commit();
        echo "success";
    } catch (Exception $e) {
        $conn->rollback();
        echo "error:" . $e->getMessage();
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_id'])) {
    $id = intval($_POST['delete_id']);
    $conn->begin_transaction();
    try {
        $checkQuery = "SELECT id, status FROM reservations WHERE id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("i", $id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        if ($checkResult->num_rows == 0) {
            echo "error:Reservation not found";
            exit;
        }
        $reservationData = $checkResult->fetch_assoc();
        if ($reservationData['status'] == 'Checked In') {
            $roomsQuery = "SELECT room_id, quantity_booked FROM reservation_room WHERE reservation_id = ?";
            $roomsStmt = $conn->prepare($roomsQuery);
            $roomsStmt->bind_param("i", $id);
            $roomsStmt->execute();
            $roomsResult = $roomsStmt->get_result();
            while ($roomRow = $roomsResult->fetch_assoc()) {
                $roomId = $roomRow['room_id'];
                $roomQuantity = $roomRow['quantity_booked'];
                
                // Get current room quantity
                $currentQuantityQuery = "SELECT quantity, status FROM rooms WHERE id = ?";
                $currentQuantityStmt = $conn->prepare($currentQuantityQuery);
                $currentQuantityStmt->bind_param("i", $roomId);
                $currentQuantityStmt->execute();
                $currentQuantityResult = $currentQuantityStmt->get_result();
                
                if ($currentQuantityResult->num_rows > 0) {
                    $currentQuantityRow = $currentQuantityResult->fetch_assoc();
                    $currentQuantity = $currentQuantityRow['quantity'];
                    
                    // Update room quantity
                    $newQuantity = $currentQuantity + $roomQuantity;
                    $updateRoomQuery = "UPDATE rooms SET quantity = ?, status = 'Available' WHERE id = ?";
                    $updateRoomStmt = $conn->prepare($updateRoomQuery);
                    $updateRoomStmt->bind_param("ii", $newQuantity, $roomId);
                    $updateRoomStmt->execute();
                }
            }
        }
        $deleteRoomsQuery = "DELETE FROM reservation_room WHERE reservation_id = ?";
        $deleteRoomsStmt = $conn->prepare($deleteRoomsQuery);
        $deleteRoomsStmt->bind_param("i", $id);
        $deleteRoomsStmt->execute();
        $deletePaymentsQuery = "DELETE FROM paymentscheck WHERE reservation_id = ?";
        $deletePaymentsStmt = $conn->prepare($deletePaymentsQuery);
        $deletePaymentsStmt->bind_param("i", $id);
        $deletePaymentsStmt->execute();
        $deleteQuery = "DELETE FROM reservations WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteQuery);
        $deleteStmt->bind_param("i", $id);
        $deleteStmt->execute();
        $conn->commit();
        echo "success";
    } catch (Exception $e) {
        $conn->rollback();
        echo "error:" . $e->getMessage();
    }
    exit;
}
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$whereClause = "";
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'approved';
switch ($filter) {
    case 'checked_in':
        $whereClause .= (empty($whereClause) ? "WHERE " : " AND ") . "r.status = 'Checked In'";
        break;
    case 'checked_out':
        $whereClause .= (empty($whereClause) ? "WHERE " : " AND ") . "r.status = 'Checked Out'";
        break;
    case 'approved':
    default:
        $whereClause .= (empty($whereClause) ? "WHERE " : " AND ") . "r.status = 'Approved'";
        break;
}
if (!empty($search)) {
    $whereClause = "WHERE (r.reservation_code LIKE '%$search%'
                    OR r.first_name LIKE '%$search%'
                    OR r.last_name LIKE '%$search%'
                    OR r.check_in LIKE '%$search%'
                    OR r.check_out LIKE '%$search%')";
    if ($filter == 'checked_in') {
        $whereClause .= " AND r.status = 'Checked In'";
    } else if ($filter == 'checked_out') {
        $whereClause .= " AND r.status = 'Checked Out'";
    } else if ($filter == 'approved') {
        $whereClause .= " AND r.status = 'Approved'";
    }
}

function getRoomDetails($conn, $reservationId) {
    $roomQuery = "SELECT rr.room_id, rr.quantity_booked, r.room_name, r.room_type 
                  FROM reservation_room rr 
                  LEFT JOIN rooms r ON rr.room_id = r.id 
                  WHERE rr.reservation_id = ?";
    $stmt = $conn->prepare($roomQuery);
    $stmt->bind_param("i", $reservationId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rooms = [];
    while ($row = $result->fetch_assoc()) {
        $rooms[] = [
            'name' => $row['room_name'] ?? 'Room #' . $row['room_id'],
            'type' => $row['room_type'] ?? 'Unknown',
            'quantity' => $row['quantity_booked']
        ];
    }
    return $rooms;
}

// Updated SQL query using the correct column names from your rooms table
$sql = "SELECT r.id, r.reservation_code, r.first_name, r.last_name, r.email, r.contact_number, 
               r.check_in, r.check_out, r.status, r.total_price, r.amount_paid, r.payment_status,
               r.guest_type, r.reservation_type, r.user_id, r.time, r.adult_count, r.kid_count, 
               r.special_requests, r.extra_tent, r.base_price, r.extras_price, r.checkin_status, 
               r.has_damage, r.damage_description, r.damage_fee, r.balance_due, r.additional_items, 
               r.additional_fee,
               GROUP_CONCAT(CONCAT(rm.name, ' (', rr.quantity_booked, ')') SEPARATOR ', ') as room_details
        FROM reservations r
        LEFT JOIN reservation_room rr ON r.id = rr.reservation_id
        LEFT JOIN rooms rm ON rr.room_id = rm.id
        $whereClause
        GROUP BY r.id
        ORDER BY r.check_in DESC";
$result = $conn->query($sql);
$today = date('Y-m-d');

// Also update the current check-ins query with proper room names
$currentCheckinsQuery = "
    SELECT r.id, r.reservation_code, r.first_name, r.last_name, r.check_in, r.check_out, 
           r.contact_number, r.adult_count, r.kid_count, r.total_price, r.amount_paid, r.payment_status,
           GROUP_CONCAT(CONCAT(rm.name, ' (', rr.quantity_booked, ')') SEPARATOR ', ') as room_details
    FROM reservations r
    LEFT JOIN reservation_room rr ON r.id = rr.reservation_id
    LEFT JOIN rooms rm ON rr.room_id = rm.id
    WHERE r.status = 'Checked In'
    GROUP BY r.id
    ORDER BY r.check_in DESC
";
?>
<!DOCTYPE html>
<html>
<head>
    <title>CHECK-INS AND CHECK-OUT Management</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <link rel="stylesheet" href="styles/checkin.css">
    <script>
        let currentId = null;
        let currentAction = '';
        let currentTotal = 0;
        function formatDate(date) {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }
        function openCheckInModal(id, checkinDate, total) {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'get_reservation_details.php?id=' + id, true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                const todayDate = new Date('<?php echo $today; ?>');
                todayDate.setHours(0, 0, 0, 0);
                const checkInDate = new Date(checkinDate);
                checkInDate.setHours(0, 0, 0, 0);
                
                // Check if check-in date is exactly today
                const isToday = checkInDate.getTime() === todayDate.getTime();
                
                if (checkInDate < todayDate) {
                    alert("Check-in is not allowed for past dates.");
                    return;
                }
                
                const modal = document.getElementById("modal");
                const modalContent = document.querySelector("#modal .modal-content");
                const amountPaid = parseFloat(data.amount_paid) || 0;
                const totalPrice = parseFloat(data.total_price) || 0;
                const remainingBalance = totalPrice - amountPaid;
                const minPaymentRequired = totalPrice * 0.4; 
                
                let modalHTML = `
                    <span class="close" onclick="closeModal()">&times;</span>
                    <h2>Guest Check-In</h2>
                    <div class="info-box">
                        <p><strong>Guest:</strong> ${data.first_name} ${data.last_name}</p>
                        <p><strong>Reservation Code:</strong> ${data.reservation_code}</p>
                        <p><strong>Check-in Date:</strong> ${formatDate(checkInDate)}</p>
                        <p><strong>Check-out Date:</strong> ${data.check_out}</p>
                    </div>
                    <div class="payment-summary">
                        <h3>Payment Summary</h3>
                        <p><strong>Total Price:</strong> ₱${totalPrice.toFixed(2)}</p>
                        <p><strong>Amount Paid:</strong> ₱${amountPaid.toFixed(2)}</p>
                        <p><strong>Remaining Balance:</strong> ₱${remainingBalance.toFixed(2)}</p>
                    </div>`;
                
                // Show warning if not today
                if (!isToday) {
                    modalHTML += `
                        <div class="warning-box">
                            <p class="warning" style="color: #721c24; font-weight: bold;">Check-in Date Restriction</p>
                            <p>Check-in is only allowed on the reservation date (${formatDate(checkInDate)}).</p>
                            <p>Today is ${formatDate(todayDate)}. Please wait until the check-in date.</p>
                        </div>`;
                }
                
                if (amountPaid < minPaymentRequired) {
                    modalHTML += `
                        <div class="warning-box">
                            <p class="warning">The minimum required down payment (40%) has not been met.</p>
                            <p>Please collect at least ₱${(minPaymentRequired - amountPaid).toFixed(2)} before check-in.</p>
                        </div>`;
                }
                
                // Show payment requirement warning
                if (remainingBalance > 0) {
                    modalHTML += `
                        <div class="warning-box">
                            <p class="warning" style="color: #721c24; font-weight: bold;">Full Payment Required</p>
                            <p>The guest must pay the full remaining balance of ₱${remainingBalance.toFixed(2)} to complete check-in.</p>
                        </div>`;
                }
                
                modalHTML += `
                    <div class="payment-form">
                        <h3>Payment Collection</h3>
                        <form id="checkinForm" onsubmit="return submitCheckInForm(${id})">
                            <input type="hidden" name="checkin_id" value="${id}">
                            <div class="form-group">
                                <label for="additional_payment">Amount to Collect:</label>
                                <input type="number" id="additional_payment" name="additional_payment" 
                                       step="0.01" min="${remainingBalance.toFixed(2)}" 
                                       value="${remainingBalance.toFixed(2)}" 
                                       ${!isToday ? 'disabled' : ''} required>
                                <small style="color: #666;">Minimum required: ₱${remainingBalance.toFixed(2)}</small>
                            </div>
                            <div class="form-group">
                                <label for="payment_method">Payment Method:</label>
                                <select id="payment_method" name="payment_method" ${!isToday ? 'disabled' : ''}>
                                    <option value="Cash">Cash</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="payment_notes">Payment Notes:</label>
                                <textarea id="payment_notes" name="payment_notes" rows="2" ${!isToday ? 'disabled' : ''}></textarea>
                            </div>
                            <button type="submit" class="action-button" ${!isToday ? 'disabled' : ''}>
                                ${!isToday ? 'Check-in Not Available Today' : 'Complete Check-In'}
                            </button>
                            <button type="button" onclick="closeModal()" class="cancel-button">Cancel</button>
                        </form>
                    </div>
                `;
                
                modalContent.innerHTML = modalHTML;
                modal.style.display = "block";
            } catch (e) {
                console.error("Error parsing reservation data:", e);
                alert("Error retrieving reservation details. Please try again.");
            }
        } else {
            alert("Error retrieving reservation details. Please try again.");
        }
    };
    xhr.send();
}

function submitCheckInForm(id) {
    const form = document.getElementById('checkinForm');
    const formData = new FormData(form);
    
    // Get the values for validation
    const additionalPayment = parseFloat(document.getElementById('additional_payment').value) || 0;
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = xhr.responseText.trim();
            if (response === "success") {
                closeModal();
                showSuccessModal('Check-In Complete', 'Guest has been successfully checked in! Full payment received and room inventory has been updated.');
            } else if (response === "success_no_rooms") {
                closeModal();
                showSuccessModal('Check-In Complete', 'Guest has been successfully checked in! Full payment received. Note: No room inventory was updated as no rooms were found for this reservation.');
            } else if (response === "error_not_today") {
                alert('Check-in is only allowed on the reservation date. Please wait until the check-in date to proceed.');
            } else if (response === "error_insufficient_payment") {
                alert('Insufficient payment amount. The guest must pay the full remaining balance to complete check-in.');
            } else if (response === "error_past_date") {
                alert('Cannot check in for past dates.');
            } else if (response.startsWith("error:")) {
                alert('Error: ' + response.substring(6));
            } else {
                alert('Error during check-in. Please try again.');
            }
        } else {
            alert('Error during check-in. Please try again.');
        }
    };
    xhr.send(formData);
    return false; // Prevent form submission
}
        function openCheckOutModal(id, firstName, lastName) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_reservation_details.php?id=' + id, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        const totalPrice = parseFloat(data.total_price) || 0;
                        const amountPaid = parseFloat(data.amount_paid) || 0;
                        const remainingBalance = totalPrice - amountPaid;
                        const modal = document.getElementById("modal");
                        const modalContent = document.querySelector("#modal .modal-content");
                        modalContent.innerHTML = `
                            <span class="close" onclick="closeModal()">&times;</span>
                            <h2>Check Out: ${firstName} ${lastName}</h2>
                            <div class="info-box">
                                <p><strong>Reservation Code:</strong> ${data.reservation_code}</p>
                                <p><strong>Check-in Date:</strong> ${data.check_in}</p>
                                <p><strong>Check-out Date:</strong> ${data.check_out}</p>
                            </div>
                            <div class="payment-summary">
                                <h3>Billing Summary</h3>
                                <p><strong>Original Total:</strong> ₱${totalPrice.toFixed(2)}</p>
                                <p><strong>Amount Paid:</strong> ₱${amountPaid.toFixed(2)}</p>
                                <p><strong>Remaining Balance:</strong> ₱${remainingBalance.toFixed(2)}</p>
                            </div>
                            <form id="checkoutForm" onsubmit="return submitCheckoutForm(${id})">
                                <input type="hidden" name="checkout_id" value="${id}">          
                                <div class="damage-section">
                                    <h3>Damage Assessment</h3>
                                    <div class="form-group">
                                        <label>
                                            <input type="checkbox" id="hasDamage" name="has_damage" onchange="toggleDamageDescription()"> 
                                            Report damage or missing items
                                        </label>
                                    </div>
                                    <div id="damageDescriptionContainer" style="display: none;">
                                        <div class="form-group">
                                            <label for="damageDescription">Damage Description:</label>
                                            <textarea id="damageDescription" name="damage_description" placeholder="Describe the damage or missing items..."></textarea>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="damageFee">Damage Fee (₱):</label>
                                            <input type="number" id="damageFee" name="damage_fee" value="0.00" min="0" step="0.01" onchange="updateTotalFees()">
                                        </div>
                                    </div>
                                </div>
                                <div class="additional-section">
                                    <h3>Additional Charges</h3>
                                    <div class="form-group">
                                        <label for="additionalItems">Additional Items/Services:</label>
                                        <textarea id="additionalItems" name="additional_items" placeholder="Describe any additional items or services..."></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label for="additionalFee">Additional Fee (₱):</label>
                                        <input type="number" id="additionalFee" name="additional_fee" value="0.00" min="0" step="0.01" onchange="updateTotalFees()">
                                    </div>
                                </div>
                                <div class="final-billing">
                                    <h3>Final Billing</h3>
                                    <p><strong>Original Balance:</strong> ₱<span id="originalBalance">${remainingBalance.toFixed(2)}</span></p>
                                    <p><strong>Additional Fees:</strong> ₱<span id="additionalFees">0.00</span></p>
                                    <p><strong>Total Due Now:</strong> ₱<span id="totalDue">${remainingBalance.toFixed(2)}</span></p>
                                    <div class="form-group">
                                        <label for="additionalPayment">Payment Amount (₱):</label>
                                        <input type="number" id="additionalPayment" name="additional_payment" value="${remainingBalance > 0 ? remainingBalance.toFixed(2) : '0.00'}" min="0" step="0.01">
                                    </div>
                                    <div class="form-group">
                                        <label for="paymentMethod">Payment Method:</label>
                                        <select id="paymentMethod" name="payment_method">
                                            <option value="Cash">Cash</option>
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" id="confirmBtn" class="action-button">Complete Check Out</button>
                                <button type="button" onclick="closeModal()" class="cancel-button">Cancel</button>
                            </form>
                        `;
                        modal.style.display = "block";
                        window.updateTotalFees = function() {
                            const originalBalance = parseFloat(document.getElementById('originalBalance').innerText);
                            
                            // FIX: Only include damage fee if checkbox is CHECKED
                            const hasDamageChecked = document.getElementById('hasDamage').checked;
                            const damageFee = hasDamageChecked ? (parseFloat(document.getElementById('damageFee').value) || 0) : 0;
                            
                            const additionalFee = parseFloat(document.getElementById('additionalFee').value) || 0;
                            const totalAdditionalFees = damageFee + additionalFee;
                            const totalDue = originalBalance + totalAdditionalFees;
                            
                            document.getElementById('additionalFees').innerText = totalAdditionalFees.toFixed(2);
                            document.getElementById('totalDue').innerText = totalDue.toFixed(2);
                            document.getElementById('additionalPayment').value = totalDue.toFixed(2);
                        };
                        // Initialize the toggleDamageDescription function
                        window.toggleDamageDescription = function() {
                            const hasDamage = document.getElementById('hasDamage').checked;
                            document.getElementById('damageDescriptionContainer').style.display = hasDamage ? 'block' : 'none';
                            
                            // FIX: Reset damage fee to 0 when unchecked
                            if (!hasDamage) {
                                document.getElementById('damageFee').value = '0.00';
                            } else {
                                document.getElementById('damageFee').value = '2000.00';
                            }
                            
                            updateTotalFees();
                        };
                    } catch (e) {
                        console.error("Error parsing reservation data:", e);
                        alert("Error retrieving reservation details. Please try again.");
                    }
                } else {
                    alert("Error retrieving reservation details. Please try again.");
                }
            };
            xhr.send();
        }
        function submitCheckoutForm(id) {
            const form = document.getElementById('checkoutForm');
            const formData = new FormData(form);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '', true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    const response = xhr.responseText.trim();
                    if (response === "success") {
                        closeModal();
                        const hasDamage = document.getElementById('hasDamage').checked;
                        const additionalFee = parseFloat(document.getElementById('additionalFee').value) || 0;
                        let successMessage = "Guest has been checked out successfully. ";
                        if (hasDamage) {
                            successMessage += "Damage fees have been applied. ";
                        }
                        if (additionalFee > 0) {
                            successMessage += "Additional fees have been applied. ";
                        }
                        successMessage += "Payment has been recorded and room inventory has been restored.";
                        showSuccessModal('Check-Out Complete', successMessage);
                    } else if (response.startsWith("error:")) {
                        alert('Error: ' + response.substring(6));
                    } else {
                        alert("Error during check-out. Please try again.");
                    }
                } else {
                    alert("Error during check-out. Please try again.");
                }
            };
            xhr.send(formData);
            return false; // Prevent form submission
        }
        function viewPaymentHistory(id) {
            const xhr = new XMLHttpRequest();
            xhr.open('GET', 'get_reservation_details.php?id=' + id, true);
            xhr.onload = function() {
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        const modal = document.getElementById("modal");
                        const modalContent = document.querySelector("#modal .modal-content");
                        
                        // Determine tour type and schedule
                        let tourTypeLabel = 'Day Tour';
                        let schedule = '8:00 AM – 5:00 PM';
                        
                        const isPrivate = (data.reservation_type === 'private');
                        
                        // Check tour type from boolean fields
                        if (data.night_tour_pm == 1) {
                            tourTypeLabel = 'Overnight PM';
                            schedule = isPrivate ? '8:00 PM – 7:00 AM (next day)' : '6:00 PM – 4:00 PM (next day)';
                        } else if (data.night_tour_am == 1) {
                            tourTypeLabel = 'Overnight AM';
                            schedule = '9:00 AM – 7:00 AM (next day)';
                        } else if (data.whole_day_morning_tour == 1) {
                            tourTypeLabel = 'Whole Day';
                            schedule = isPrivate ? '9:00 AM – 7:00 AM (next day) / 8:00 PM – 6:00 PM (next day)' : '2:00 PM – 12:00 NN (next day) — Campers/Tent Users Only';
                        } else if (data.whole_day_night_tour == 1) {
                            tourTypeLabel = 'Overnight Special';
                            schedule = '2:00 PM – 12:00 NN (next day) — Campers/Tent Users Only';
                        } else if (data.day_tour == 1) {
                            tourTypeLabel = 'Day Tour';
                            schedule = isPrivate ? '9:00 AM – 6:00 PM' : '8:00 AM – 5:00 PM';
                        }
                        
                        // Calculate total guests
                        const totalGuests = (parseInt(data.adult_count) || 0) + 
                                          (parseInt(data.kid_count) || 0) + 
                                          (parseInt(data.pwd_senior_count) || 0);
                        
                        let paymentsHTML = '';
                        if (data.payments && data.payments.length > 0) {
                            data.payments.forEach(payment => {
                                const paymentDate = new Date(payment.payment_date);
                                paymentsHTML += `
                                    <div class="payment-history-item">
                                        <p><strong>Date:</strong> ${paymentDate.toLocaleString()}</p>
                                        <p><strong>Amount:</strong> ₱${parseFloat(payment.amount).toFixed(2)}</p>
                                        <p><strong>Method:</strong> <span class="payment-method-${payment.payment_method.toLowerCase().replace(' ', '-')}">${payment.payment_method}</span></p>
                                        <p><strong>Type:</strong> ${payment.payment_type}</p>
                                        ${payment.payment_notes ? `<p><strong>Notes:</strong> ${payment.payment_notes}</p>` : ''}
                                    </div>
                                `;
                            });
                        } else {
                            paymentsHTML = '<p>No payment records found.</p>';
                        }
                        
                        modalContent.innerHTML = `
                            <span class="close" onclick="closeModal()">&times;</span>
                            <h2>Details & Payment History</h2>
                            
                            <div class="info-box">
                                <h3 style="margin-top: 0; color: #2f5e2f;">Guest Information</h3>
                                <p><strong>Guest:</strong> ${data.first_name} ${data.last_name}</p>
                                <p><strong>Email:</strong> ${data.email || 'N/A'}</p>
                                <p><strong>Contact Number:</strong> ${data.contact_number || 'N/A'}</p>
                            </div>
                            
                            <div class="info-box" style="background-color: #e8f5e9; border-left: 4px solid #4caf50;">
                                <h3 style="margin-top: 0; color: #2f5e2f;">Tour Information</h3>
                                <p><strong>Tour Type:</strong> ${tourTypeLabel}</p>
                                <p><strong>Schedule:</strong> ${schedule}</p>
             
                            </div>
                            
                            <div class="info-box" style="background-color: #fff3e0; border-left: 4px solid #ff9800;">
                                <h3 style="margin-top: 0; color: #2f5e2f;">Guest Count</h3>
                                <p><strong>Total Guests:</strong> ${totalGuests}</p>
                                <p><strong>Adults:</strong> ${data.adult_count || 0} | <strong>Kids:</strong> ${data.kid_count || 0} | <strong>PWD/Senior:</strong> ${data.pwd_senior_count || 0}</p>
                            </div>
                            
                            <div class="info-box">
                                <h3 style="margin-top: 0; color: #2f5e2f;">Payment Summary</h3>
                                <p><strong>Total Price:</strong> ₱${parseFloat(data.total_price).toFixed(2)}</p>
                                <p><strong>Amount Paid:</strong> ₱${parseFloat(data.amount_paid).toFixed(2)}</p>
                                <p><strong>Remaining Balance:</strong> ₱${(parseFloat(data.total_price) - parseFloat(data.amount_paid)).toFixed(2)}</p>
                            </div>
                            
                            <div class="payment-history">
                                <h3>Payment Records</h3>
                                ${paymentsHTML}
                            </div>
                            
                            <button onclick="closeModal()" class="cancel-button">Close</button>
                        `;
                        modal.style.display = "block";
                    } catch (e) {
                        console.error("Error parsing payment data:", e);
                        alert("Error retrieving payment details. Please try again.");
                    }
                } else {
                    alert("Error retrieving payment details. Please try again.");
                }
            };
            xhr.send();
        }
        function openDeleteModal(id, reservationCode, guestName) {
            currentId = id;
            currentAction = 'Delete';
            const modal = document.getElementById("modal");
            const modalContent = document.querySelector("#modal .modal-content");
            modalContent.innerHTML = `
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Delete Reservation</h2>
                <div class="warning-box">
                    <p class="warning" style="color: #721c24; font-weight: bold;">Warning: This action cannot be undone!</p>
                    <p>You are about to delete the following reservation:</p>
                    <p><strong>Reservation Code:</strong> ${reservationCode}</p>
                    <p><strong>Guest Name:</strong> ${guestName}</p>
                </div>
                <p>If this reservation is currently checked in, deleting it will automatically restore room availability.</p>
                <div style="display: flex; justify-content: center; gap: 15px; margin-top: 20px;">
                    <button type="button" class="action-button" style="background-color: #dc3545;" onclick="confirmDelete(${id})">Delete Reservation</button>
                    <button type="button" class="cancel-button" onclick="closeModal()">Cancel</button>
                </div>
            `;
            modal.style.display = "block";
        }
        function confirmDelete(id) {
            const formData = new FormData();
            formData.append('delete_id', id);
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "", true);
            xhr.onload = function() {
                const response = xhr.responseText.trim();
                if (response === "success") {
                    closeModal(); // Close the existing modal
                    showSuccessModal('Reservation Deleted', 'The reservation has been successfully deleted from the system.');
                } else if (response.startsWith("error:")) {
                    alert('Error: ' + response.substring(6));
                } else {
                    alert("Error during deletion. Please try again.");
                }
            };
            xhr.send(formData);
        }
        function closeModal() {
            document.getElementById("modal").style.display = "none";
        }
        function showSuccessModal(title, message) {
            document.getElementById('successTitle').textContent = title;
            document.getElementById('successMessage').textContent = message;
            document.getElementById('successModal').style.display = 'block';
            setTimeout(function() {
                closeSuccessModal();
            }, 3000);
        }
        function closeSuccessModal() {
            document.getElementById('successModal').style.display = 'none';
            window.location.reload();
        }
        function setFilter(filter) {
            window.location.href = `?filter=${filter}${window.location.search.includes('search=') ? '&' + window.location.search.substring(1).split('&').find(param => param.startsWith('search=')) : ''}`;
        }
        window.onclick = function(event) {
            const modal = document.getElementById("modal");
            const successModal = document.getElementById("successModal");
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == successModal) {
                closeSuccessModal();
            }
        }
    </script>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
     <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="dashboard-title">Check-in | Check-out Dashboard</h2>
    </div>
    <?php if (isset($_GET['error']) && $_GET['error'] == 'past_date'): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> Cannot check in for past dates.
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['success']) && $_GET['success'] == 'checked_in'): ?>
        <div class="alert alert-success">
            <strong>Success:</strong> Guest has been checked in successfully.
        </div>
    <?php endif; ?>
    <form method="GET">
        <input type="text" name="search" placeholder="Search by code, name, or date..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
        <input type="hidden" name="filter" value="<?= isset($_GET['filter']) ? htmlspecialchars($_GET['filter']) : 'approved' ?>">
        <button type="submit">Search</button>
    </form>
    <a href="?" class="back-button">Clear All</a>
    <div class="filter-buttons">
        <button class="filter-button <?= (!isset($_GET['filter']) || $_GET['filter'] == 'approved') ? 'active' : '' ?>" onclick="setFilter('approved')">Pending Check-ins</button>
        <button class="filter-button <?= (isset($_GET['filter']) && $_GET['filter'] == 'checked_in') ? 'active' : '' ?>" onclick="setFilter('checked_in')">Currently Checked In</button>
        <button class="filter-button <?= (isset($_GET['filter']) && $_GET['filter'] == 'checked_out') ? 'active' : '' ?>" onclick="setFilter('checked_out')">History (Checked Out)</button>
    </div>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Reservation Code</th>
                <th>Rooms</th> <!-- New column -->
                <th>Check In</th>
                <th>Check Out</th>
                <th>Total</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php 
                $checkInDate = new DateTime($row['check_in']);
                $todayDate = new DateTime($today);
                $isPastDate = ($checkInDate < $todayDate);
                $isToday = ($checkInDate->format('Y-m-d') === $todayDate->format('Y-m-d'));
                $balance = floatval($row['total_price']) - floatval($row['amount_paid']);
                ?>
                <tr data-id="<?= $row['id']; ?>" class="<?= $isPastDate ? 'past-date' : ''; ?>">
                    <td><?= $row['id']; ?></td>
                    <td><?= htmlspecialchars($row['reservation_code']); ?></td>
                    <td><?= htmlspecialchars($row['room_details'] ?? 'No rooms assigned'); ?></td> <!-- New column -->
                    <td><?= htmlspecialchars($row['check_in']); ?></td>
                    <td><?= htmlspecialchars($row['check_out']); ?></td>
                    <td>₱<?= number_format(floatval($row['total_price']), 2); ?></td>
                   
                    <td>₱<?= number_format($balance, 2); ?></td>
                    <td class="<?= $row['payment_status'] === 'Paid' ? 'status-paid' : 'status-partial'; ?>"><?= htmlspecialchars($row['payment_status']); ?></td>
                    <td class="action-container">
                        <!-- Rest of the action buttons remain the same -->
                        <?php if ($row['status'] === 'Checked Out'): ?>
                            <button class="action-button" disabled>Completed</button>
                        <?php elseif ($row['status'] === 'Checked In'): ?>
                            <button class="action-button" onclick="openCheckOutModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['first_name']); ?>', '<?= htmlspecialchars($row['last_name']); ?>')">Check Out</button>
                        <?php elseif ($row['status'] === 'Approved'): ?>
                            <?php if($isPastDate): ?>
                                <button class="action-button" disabled>Past Date</button>
                            <?php else: ?>
                                <button class="action-button <?= !$isToday ? 'not-today' : ''; ?>" 
                                        onclick="openCheckInModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['check_in']); ?>', <?= floatval($row['total_price']); ?>)">
                                    <?= $isToday ? 'Check In' : 'Check In (Not Today)'; ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <button class="action-button view-btn" onclick="viewPaymentHistory(<?= $row['id']; ?>)">View Details</button>
                        <button class="action-button delete-btn" onclick="openDeleteModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['reservation_code']); ?>', '<?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>')">Delete</button>
                    </td>
                        
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="10">No reservations found.</td></tr> <!-- Update colspan to 10 -->
        <?php endif; ?>
            </tbody>
    </table>
    
    <?php if (!isset($_GET['filter']) || $_GET['filter'] != 'checked_out'): ?>
        <div class="current-checkins">
            <h2>Current Check-ins</h2>
            <?php
            $currentCheckins = $conn->query($currentCheckinsQuery);
            ?>
            <?php if ($currentCheckins->num_rows > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Reservation Code</th>
                            <th>Guest Name</th>
                            <th>Rooms</th> <!-- New column -->
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($checkin = $currentCheckins->fetch_assoc()): ?>
                            <?php 
                            $checkinBalance = floatval($checkin['total_price']) - floatval($checkin['amount_paid']);
                            ?>
                            <tr>
                                <td><?= $checkin['id']; ?></td>
                                <td><?= htmlspecialchars($checkin['reservation_code']); ?></td>
                                <td><?= htmlspecialchars($checkin['first_name'] . ' ' . $checkin['last_name']); ?></td>
                                <td><?= htmlspecialchars($checkin['room_details'] ?? 'No rooms assigned'); ?></td> <!-- New column -->
                                <td><?= htmlspecialchars($checkin['check_in']); ?></td>
                                <td><?= htmlspecialchars($checkin['check_out']); ?></td>
                                <td>₱<?= number_format(floatval($checkin['total_price']), 2); ?></td>
                                <td>₱<?= number_format(floatval($checkin['amount_paid']), 2); ?></td>
                                <td>₱<?= number_format($checkinBalance, 2); ?></td>
                                <td class="<?= $checkin['payment_status'] === 'Paid' ? 'status-paid' : 'status-partial'; ?>"><?= htmlspecialchars($checkin['payment_status']); ?></td>
                                <td class="action-container">
                                    <button class="action-button" onclick="openCheckOutModal(<?= $checkin['id']; ?>, '<?= htmlspecialchars($checkin['first_name']); ?>', '<?= htmlspecialchars($checkin['last_name']); ?>')">Check Out</button>
                                    <button class="action-button view-btn" onclick="viewPaymentHistory(<?= $checkin['id']; ?>)">View Payments</button>
                                    
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No current check-ins.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    <?php if (isset($_GET['filter']) && $_GET['filter'] == 'checked_out'): ?>
    <div class="summary-section">
        <h2>Damage Reports</h2>
        <?php
        $damageReports = $conn->query("
            SELECT id, reservation_code, first_name, last_name, check_in, check_out, 
                   damage_description, damage_fee, additional_items, additional_fee
            FROM reservations 
            WHERE status = 'Checked Out' AND (has_damage = 1 OR additional_fee > 0)
            ORDER BY check_out DESC
            LIMIT 10
        ");
        ?>
        <?php if ($damageReports->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Guest Name</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Damage Description</th>
                        <th>Damage Fee</th>
                        <th>Additional Items</th>
                        <th>Additional Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($report = $damageReports->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($report['reservation_code']); ?></td>
                            <td><?= htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></td>
                            <td><?= htmlspecialchars($report['check_in']); ?></td>
                            <td><?= htmlspecialchars($report['check_out']); ?></td>
                            <td><?= htmlspecialchars($report['damage_description'] ?? 'None'); ?></td>
                            <td>₱<?= number_format(floatval($report['damage_fee'] ?? 0), 2); ?></td>
                            <td><?= htmlspecialchars($report['additional_items'] ?? 'None'); ?></td>
                            <td>₱<?= number_format(floatval($report['additional_fee'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No damage or additional fee reports found.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div id="modal" class="modal">
        <div class="modal-content">
        </div>
    </div>
    <div id="successModal" class="success-modal">
        <div class="success-modal-content">
            <div class="success-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
                </svg>
            </div>
            <h2 class="success-title" id="successTitle">Success!</h2>
            <p class="success-message" id="successMessage">Operation completed successfully.</p>
            <button class="success-button" onclick="closeSuccessModal()">OK</button>
        </div>
    </div>
</div>
</body>