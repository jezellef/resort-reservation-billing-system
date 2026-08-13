<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'db_connect.php';
$messages = [];
$messageType = '';

// Function to get booked rooms for a reservation
function getBookedRooms($conn, $reservation_id) {
    $roomQuery = "SELECT rr.*, r.name as room_name, r.capacity 
                  FROM reservation_room rr 
                  JOIN rooms r ON rr.room_id = r.id 
                  WHERE rr.reservation_id = ?";
    $stmt = $conn->prepare($roomQuery);
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rooms = [];
    while ($room = $result->fetch_assoc()) {
        $rooms[] = $room;
    }
    $stmt->close();
    return $rooms;
}

// Enhanced room availability checking function using your existing logic
function checkRoomAvailability($conn, $room_id, $check_in, $check_out, $quantity_needed = 1, $exclude_reservation_id = null) {
    // Get room's real quantity
    $roomQuery = "SELECT real_quantity, name, status FROM rooms WHERE id = ?";
    $stmt = $conn->prepare($roomQuery);
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    $roomResult = $stmt->get_result();
    
    if ($roomResult->num_rows === 0) {
        return ['available' => false, 'available_quantity' => 0, 'message' => 'Room not found'];
    }
    $room = $roomResult->fetch_assoc();
    $stmt->close();
    
    // Check if room is available status
    if ($room['status'] !== 'Available') {
        return ['available' => false, 'available_quantity' => 0, 'message' => 'Room is currently unavailable'];
    }
    
    $current_time = time();
    
    // For same-day bookings
    if ($check_in === $check_out) {
        // Check day tour bookings for the same date
        $bookedQuery = "
            SELECT COALESCE(SUM(rr.quantity_booked), 0) as total_booked
            FROM reservation_room rr
            JOIN reservations r ON rr.reservation_id = r.id
            WHERE rr.room_id = ? 
            AND (
                -- Confirmed/approved reservations
                r.status IN ('Approved', 'Checked In', 'Pending') OR
                -- Unexpired saved/pending reservations (within 3-hour window)
                (
                    (r.status = 'saved' OR r.status = '' OR r.status IS NULL) AND
                    r.payment_status IN ('Unpaid', 'unpaid') AND
                    r.expires_at > ?
                )
            )
            AND (
                (r.check_in = ? AND r.check_out = ?) OR
                (r.check_in <= ? AND r.check_out > ?)
            )";
        
        // Add exclusion for current reservation being rescheduled
        if ($exclude_reservation_id) {
            $bookedQuery .= " AND r.id != ?";
            $stmt = $conn->prepare($bookedQuery);
            $stmt->bind_param("iissssi", $room_id, $current_time, $check_in, $check_out, $check_in, $check_in, $exclude_reservation_id);
        } else {
            $stmt = $conn->prepare($bookedQuery);
            $stmt->bind_param("iissss", $room_id, $current_time, $check_in, $check_out, $check_in, $check_in);
        }
    } else {
        // Check overnight bookings - date ranges that overlap
        $bookedQuery = "
            SELECT COALESCE(SUM(rr.quantity_booked), 0) as total_booked
            FROM reservation_room rr
            JOIN reservations r ON rr.reservation_id = r.id
            WHERE rr.room_id = ? 
            AND (
                -- Confirmed/approved reservations
                r.status IN ('Approved', 'Checked In', 'Pending') OR
                -- Unexpired saved/pending reservations (within 3-hour window)
                (
                    (r.status = 'saved' OR r.status = '' OR r.status IS NULL) AND
                    r.payment_status IN ('Unpaid', 'unpaid') AND
                    r.expires_at > ?
                )
            )
            AND NOT (r.check_out <= ? OR r.check_in >= ?)";
        
        // Add exclusion for current reservation being rescheduled
        if ($exclude_reservation_id) {
            $bookedQuery .= " AND r.id != ?";
            $stmt = $conn->prepare($bookedQuery);
            $stmt->bind_param("iissi", $room_id, $current_time, $check_in, $check_out, $exclude_reservation_id);
        } else {
            $stmt = $conn->prepare($bookedQuery);
            $stmt->bind_param("iiss", $room_id, $current_time, $check_in, $check_out);
        }
    }
    
    $stmt->execute();
    $bookedResult = $stmt->get_result();
    $bookedData = $bookedResult->fetch_assoc();
    $total_booked = $bookedData['total_booked'];
    $stmt->close();
    
    $available_quantity = $room['real_quantity'] - $total_booked;
    $is_available = $available_quantity >= $quantity_needed;
    
    return [
        'available' => $is_available,
        'available_quantity' => max(0, $available_quantity),
        'total_quantity' => $room['real_quantity'],
        'booked_quantity' => $total_booked,
        'message' => $is_available ? "Available" : "Not available"
    ];
}

// Function to check room availability for rescheduling
function checkRoomAvailabilityForReschedule($conn, $reservation_id, $new_check_in_date, $new_check_out_date) {
    // Get the rooms booked for this reservation
    $bookedRooms = getBookedRooms($conn, $reservation_id);
    
    $conflicts = [];
    
    foreach ($bookedRooms as $room) {
        // Check if this room is available for the new dates, excluding the current reservation
        $availability = checkRoomAvailability(
            $conn, 
            $room['room_id'], 
            $new_check_in_date, 
            $new_check_out_date, 
            $room['quantity_booked'], 
            $reservation_id
        );
        
        if (!$availability['available']) {
            $conflicts[] = [
                'room_name' => $room['room_name'],
                'needed' => $room['quantity_booked'],
                'available' => $availability['available_quantity']
            ];
        }
    }
    
    return $conflicts;
}

// Function to determine tour type and schedule from reservation data
function getTourTypeAndSchedule($reservation) {
    $isPrivate = ($reservation['reservation_type'] === 'private');
    
    // Determine tour type from boolean fields
    $tourType = 'day_tour'; // default
    
    if ($reservation['day_tour'] == 1) {
        $tourType = 'day_tour';
    } elseif ($reservation['night_tour_pm'] == 1) {
        $tourType = 'overnight_pm';
    } elseif ($reservation['night_tour_am'] == 1) {
        $tourType = 'overnight_am';
    } elseif ($reservation['whole_day_morning_tour'] == 1) {
        $tourType = 'whole_day';
    } elseif ($reservation['whole_day_night_tour'] == 1) {
        $tourType = 'overnight_special';
    }
    
    // Map tour types to labels
    $tourTypes = [
        'day_tour' => 'Day Tour',
        'overnight_am' => 'Overnight AM',
        'overnight_pm' => 'Overnight PM', 
        'whole_day' => 'Whole Day',
        'night_tour' => 'Night Tour',
        'overnight_special' => 'Overnight Special'
    ];
    
    $tourTypeLabel = $tourTypes[$tourType] ?? 'Day Tour';
    
    // Determine schedule based on reservation type and tour type
    $schedule = 'See resort for details';
    
    if ($isPrivate) {
        // Private area schedules
        switch ($tourType) {
            case 'day_tour':
                $schedule = '9:00 AM – 6:00 PM';
                break;
            case 'night_tour':
            case 'overnight_pm':
                $schedule = '8:00 PM – 7:00 AM (next day)';
                break;
            case 'overnight_am':
            case 'whole_day':
                $schedule = '9:00 AM – 7:00 AM (next day) / 8:00 PM – 6:00 PM (next day)';
                break;
            default:
                $schedule = '9:00 AM – 6:00 PM';
        }
    } else {
        // Public area schedules
        switch ($tourType) {
            case 'day_tour':
                $schedule = '8:00 AM – 5:00 PM';
                break;
            case 'overnight_am':
                $schedule = '9:00 AM – 7:00 AM (next day)';
                break;
            case 'overnight_pm':
                $schedule = '6:00 PM – 4:00 PM (next day)';
                break;
            case 'overnight_special':
                $schedule = '2:00 PM – 12:00 NN (next day) — Campers/Tent Users Only';
                break;
            case 'whole_day':
                $schedule = '2:00 PM – 12:00 NN (next day) — Campers/Tent Users Only';
                break;
            default:
                $schedule = '8:00 AM – 5:00 PM';
        }
    }
    
    return [
        'tour_type' => $tourType,
        'tour_label' => $tourTypeLabel,
        'schedule' => $schedule,
        'display' => $tourTypeLabel . ' (' . $schedule . ')'
    ];
}

$sql = "SELECT r.id, r.reservation_code, r.first_name, r.last_name, r.email, r.check_in, r.check_out, 
        r.status, r.is_rescheduled, r.rescheduled_date, r.payment_status, r.total_price, r.balance_due,
        r.reservation_type, r.base_price, r.created_at, r.adult_count, r.kid_count, r.pwd_senior_count, 
        r.time, r.day_tour, r.whole_day_morning_tour, r.whole_day_night_tour, r.night_tour_am, r.night_tour_pm
        FROM reservations r 
        WHERE r.status IN ('Approved', 'Rejected')";    

// Add search functionality
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = '%' . $_GET['search'] . '%';
    $sql .= " AND (
        r.reservation_code LIKE ? OR
        CONCAT(r.first_name, ' ', r.last_name) LIKE ? OR
        r.email LIKE ? OR
        r.check_in LIKE ? OR
        r.check_out LIKE ? OR
        r.status LIKE ? OR
        r.id IN (
            SELECT DISTINCT rr.reservation_id 
            FROM reservation_room rr 
            JOIN rooms room ON rr.room_id = room.id 
            WHERE room.name LIKE ?
        )
    )";
}

$sql .= " ORDER BY r.check_in DESC";

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $stmt = $conn->prepare($sql);
    $search = '%' . $_GET['search'] . '%';
    $stmt->bind_param("sssssss", $search, $search, $search, $search, $search, $search, $search);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reschedule_reservation'])) {
    $reservation_id = filter_input(INPUT_POST, 'reservation_id', FILTER_VALIDATE_INT);
    $new_check_in_date_raw = filter_input(INPUT_POST, 'new_check_in_date', FILTER_UNSAFE_RAW);
    $new_check_in_date = is_string($new_check_in_date_raw) ? htmlspecialchars($new_check_in_date_raw) : '';

    if (!$reservation_id) {
        $messages[] = "Invalid reservation ID.";
        $messageType = 'error';
    } else {
        $stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
        $stmt->bind_param("i", $reservation_id);
        $stmt->execute();
        $reservation_result = $stmt->get_result();
        
        if ($reservation_result->num_rows === 0) {
            $messages[] = "Reservation not found.";
            $messageType = 'error';
        } else {
            $reservation = $reservation_result->fetch_assoc();
            $stmt->close();
            
            $original_check_in = $reservation['check_in'];
            $original_check_out = $reservation['check_out'];
            $today = date('Y-m-d');
            
            // Calculate the same length of stay as the original booking
            $original_stay_days = (strtotime($original_check_out) - strtotime($original_check_in)) / (60 * 60 * 24);
            $calculated_check_out = date('Y-m-d', strtotime($new_check_in_date . ' + ' . $original_stay_days . ' days'));
            
            $validationResults = validateRescheduling($reservation, $new_check_in_date);
            
            if (empty($validationResults['errors'])) {
                // Check room availability for the new dates
                $roomConflicts = checkRoomAvailabilityForReschedule($conn, $reservation_id, $new_check_in_date, $calculated_check_out);
                
                if (!empty($roomConflicts)) {
                    $conflictMessages = [];
                    foreach ($roomConflicts as $conflict) {
                        $conflictMessages[] = "{$conflict['room_name']} (needed: {$conflict['needed']}, available: {$conflict['available']})";
                    }
                    $messages[] = "Cannot reschedule: The following rooms are not available for the selected dates: " . implode(', ', $conflictMessages);
                    $messageType = 'error';
                } else {
                    // Update reservation dates
                    $updateStmt = $conn->prepare("UPDATE reservations SET 
                        check_in = ?, 
                        check_out = ?,
                        is_rescheduled = 1, 
                        rescheduled_date = ? 
                        WHERE id = ?");
                    $updateStmt->bind_param("sssi", $new_check_in_date, $calculated_check_out, $today, $reservation_id);
                    
                    if ($updateStmt->execute()) {
                        // Update room booking dates
                        $updateRoomStmt = $conn->prepare("UPDATE reservation_room SET 
                            check_in_date = ?, 
                            check_out_date = ?
                            WHERE reservation_id = ?");
                        $updateRoomStmt->bind_param("ssi", $new_check_in_date, $calculated_check_out, $reservation_id);
                        
                        if ($updateRoomStmt->execute()) {
                            $messages[] = "Reservation successfully rescheduled from {$original_check_in} to {$new_check_in_date}.";
                            $messageType = 'success';
                            
                            // Log the rescheduling if you have a logs table
                            try {
                                $logStmt = $conn->prepare("INSERT INTO reservation_logs 
                                    (reservation_id, action, details, created_at, admin_id) 
                                    VALUES (?, 'rescheduled', ?, NOW(), ?)");
                                $details = "Rescheduled from {$original_check_in} to {$new_check_in_date}";
                                $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 0;
                                $logStmt->bind_param("isi", $reservation_id, $details, $admin_id);
                                $logStmt->execute();
                                $logStmt->close();
                            } catch (Exception $e) {
                                // Log table might not exist, continue without logging
                            }
                        } else {
                            $messages[] = "Error updating room booking dates: " . $updateRoomStmt->error;
                            $messageType = 'error';
                        }
                        $updateRoomStmt->close();
                    } else {
                        $messages[] = "Error updating reservation: " . $updateStmt->error;
                        $messageType = 'error';
                    }
                    $updateStmt->close();
                }
            } else {
                // Display validation errors
                foreach ($validationResults['errors'] as $error) {
                    $messages[] = $error;
                }
                $messageType = 'error';
            }
        }
    }
    // Re-run the query with search parameters if they were used
    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $stmt = $conn->prepare($sql);
        $search = '%' . $_GET['search'] . '%';
        $stmt->bind_param("sssssss", $search, $search, $search, $search, $search, $search, $search);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
}

function validateRescheduling($reservation, $new_check_in_date) {
    $errors = [];
    $today = date('Y-m-d');
    $original_check_in = $reservation['check_in'];
    
    // Rule 1: Check if reservation is already rescheduled
    if ($reservation['is_rescheduled']) {
        $errors[] = "This reservation has already been rescheduled. Multiple reschedulings are not allowed.";
        return ['errors' => $errors];
    }
    
    // Rule 2: Check if rebooking is within 6 months of original booking
    $six_months_from_original = date('Y-m-d', strtotime("+6 months", strtotime($original_check_in)));
    if ($new_check_in_date > $six_months_from_original) {
        $errors[] = "Rebooking must be within 6 months of the original booking date ({$original_check_in}).";
    }
    
    // REMOVED: Rule 3 - No longer restricting to earlier dates only
    // Admins can now reschedule to both earlier AND later dates
    
    // Rule 3 (Previously Rule 4): Weekend restriction removed - admins can reschedule to any day
    
    // Rule 4 (Previously Rule 5): Check if the new date is in the past
    if ($new_check_in_date < $today) {
        $errors[] = "Rebooking date cannot be in the past.";
    }
    
    return ['errors' => $errors];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>All Reservations</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <link rel="stylesheet" href="styles/allreservation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
   <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="dashboard-title">Reservations Dashboard</h2>
        </div>
        
        <div class="search-container">
            <form method="GET">
                <input type="text" name="search" placeholder="Search by name, code, date, status, or room..." value="<?= isset($_GET['search']) ? htmlspecialchars($_GET['search']) : '' ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
                <a href="?" class="btn back-button"><i class="fas fa-times"></i> Clear</a>
            </form>
        </div>
        <?php if (!empty($messages)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php foreach ($messages as $message): ?>
                    <p><?php echo $message; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <!-- Reservations Table -->
        <table id="reservationsTable">
            <thead>
                <tr>
                    <th>Reservation Code</th>
                    <th>Guest Name</th>
                    <th>Status</th>
                    <th>Check-in</th>
                    <th>Check-out</th>
                    <th>Rooms</th>
                    <th>Rescheduled</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        $statusClass = $row['status'] == 'Approved' ? 'status-approved' : 'status-rejected';
                        $bookedRooms = getBookedRooms($conn, $row['id']);
                        
                        echo "<tr>";
                        echo "<td data-label='Code'>" . htmlspecialchars($row['reservation_code']) . "</td>";
                        echo "<td data-label='Guest'>" . htmlspecialchars($row['first_name'] . " " . $row['last_name']) . "</td>";
                        echo "<td data-label='Status' class='{$statusClass}'>" . htmlspecialchars(ucfirst($row['status'])) . "</td>";
                        echo "<td data-label='Check-in'>" . htmlspecialchars($row['check_in']) . "</td>";
                        echo "<td data-label='Check-out'>" . htmlspecialchars($row['check_out']) . "</td>";
                        
                        // Display room information
                        echo "<td data-label='Rooms' class='room-info'>";
                        if (!empty($bookedRooms)) {
                            foreach ($bookedRooms as $room) {
                                echo "<span class='room-tag'>";
                                echo htmlspecialchars($room['room_name']) . " (×" . $room['quantity_booked'] . ")";
                                echo "</span><br>";
                            }
                        } else {
                            echo "<em>No rooms assigned</em>";
                        }
                        echo "</td>";
                        
                        echo "<td data-label='Rescheduled'>";
                        if ($row['is_rescheduled']) {
                            echo "<span class='rescheduled'>Yes</span>";
                        } else {
                            echo "No";
                        }
                        echo "</td>";
                        
                        echo "<td data-label='Actions'>";
                        if (!$row['is_rescheduled']) {
                            echo "<button class='btn action-btn' onclick='showRescheduleModal(" . $row['id'] . ", \"" . $row['check_in'] . "\", \"" . $row['check_out'] . "\")'>
                                  <i class='fas fa-calendar-alt'></i> Reschedule</button> ";
                        } else {
                            echo "<span class='rescheduled'><i class='fas fa-check-circle'></i> Already Rescheduled</span>";
                        }
                        echo " <button class='btn btn-secondary action-btn' onclick='showDetailsModal(" . $row['id'] . ")'>
                              <i class='fas fa-info-circle'></i> Details</button>";
                        echo "</td>";
                        echo "</tr>";
                        
                        // Get tour type and schedule information
                        $tourInfo = getTourTypeAndSchedule($row);
                        
                        echo "<script>
                            if (typeof reservationData === 'undefined') {
                                var reservationData = {};
                            }
                            reservationData[" . $row['id'] . "] = {
                                id: " . $row['id'] . ",
                                reservation_code: '" . htmlspecialchars($row['reservation_code']) . "',
                                first_name: '" . htmlspecialchars($row['first_name']) . "',
                                last_name: '" . htmlspecialchars($row['last_name']) . "',
                                email: '" . htmlspecialchars($row['email']) . "',
                                check_in: '" . htmlspecialchars($row['check_in']) . "',
                                check_out: '" . htmlspecialchars($row['check_out']) . "',
                                status: '" . htmlspecialchars($row['status']) . "',
                                is_rescheduled: " . (int)$row['is_rescheduled'] . ",
                                payment_status: '" . htmlspecialchars($row['payment_status']) . "',
                                total_price: " . (float)$row['total_price'] . ",
                                balance_due: " . (float)$row['balance_due'] . ",
                                reservation_type: '" . htmlspecialchars($row['reservation_type']) . "',
                                base_price: " . (float)$row['base_price'] . ",
                                created_at: '" . htmlspecialchars($row['created_at']) . "',
                                adult_count: " . (int)($row['adult_count'] ?? 0) . ",
                                kid_count: " . (int)($row['kid_count'] ?? 0) . ",
                                pwd_senior_count: " . (int)($row['pwd_senior_count'] ?? 0) . ",
                                tour_label: '" . addslashes($tourInfo['tour_label']) . "',
                                schedule: '" . addslashes($tourInfo['schedule']) . "',
                                tour_display: '" . addslashes($tourInfo['display']) . "',
                                rooms: " . json_encode($bookedRooms) . "
                            };
                        </script>";
                    }
                } else {
                    echo "<tr><td colspan='8'>No reservations found</td></tr>";
                }
                ?>
            </tbody>
        </table>
        
        <!-- Reschedule Modal -->
        <div id="rescheduleModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeRescheduleModal()">&times;</span>
                <h2>Reschedule Reservation</h2>
                <div id="rescheduleModalContent">
                    <form id="rescheduleForm" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
                        <input type="hidden" name="reservation_id" id="modal_reservation_id">
                        <input type="hidden" name="reschedule_reservation" value="1">
                        
                        <div class="form-group">
                            <label for="original_check_in">Original Check-in Date:</label>
                            <input type="date" id="original_check_in" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="original_check_out">Original Check-out Date:</label>
                            <input type="date" id="original_check_out" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label>Booked Rooms:</label>
                            <div id="modal_rooms_list" class="room-details-modal">
                                <!-- Rooms will be populated by JavaScript -->
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_check_in_date">New Check-in Date:</label>
                            <input type="date" name="new_check_in_date" id="new_check_in_date" required>
                            <p id="date_validation_message" style="color: #dc3545; display: none;"></p>
                        </div>
                        
                        <div class="form-group">
                            <div class="availability-check">
                                <p><strong>Rescheduling Rules:</strong></p>
                                <ul>
                                    <li>Can reschedule to any day of the week (including weekends)</li>
                                    <li>Must be within 6 months of original booking date</li>
                                    <li>Cannot reschedule to a past date</li>
                                    <li>Can only reschedule once per reservation</li>
                                    <li>All booked rooms must be available for the new dates</li>
                                </ul>
                                <p><small>The length of stay will remain the same as the original booking.</small></p>
                                <p><small>Room availability will be checked automatically before confirming the reschedule.</small></p>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn">Confirm Rescheduling</button>
                        <button type="button" class="btn btn-secondary" onclick="closeRescheduleModal()">Cancel</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Details Modal -->
        <div id="detailsModal" class="modal">
            <div class="modal-content details-modal-content">
                <span class="close" onclick="closeDetailsModal()">&times;</span>
                <h2 class="modal-title">Reservation Details</h2>
                <div id="detailsModalContent" class="details-grid">
                    <!-- This will be populated dynamically with JS -->
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn btn-secondary" onclick="closeDetailsModal()">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Fix the max date setting
        function showRescheduleModal(reservationId, checkIn, checkOut) {
            document.getElementById('modal_reservation_id').value = reservationId;
            document.getElementById('original_check_in').value = checkIn;
            document.getElementById('original_check_out').value = checkOut;
            
            // Display booked rooms...
            const reservation = reservationData[reservationId];
            const roomsList = document.getElementById('modal_rooms_list');
            roomsList.innerHTML = '';
            
            if (reservation && reservation.rooms && reservation.rooms.length > 0) {
                reservation.rooms.forEach(room => {
                    const roomDiv = document.createElement('div');
                    roomDiv.className = 'room-tag';
                    roomDiv.innerHTML = `${room.room_name} (Quantity: ${room.quantity_booked}, Tour: ${room.tour_type || 'Standard'})`;
                    roomsList.appendChild(roomDiv);
                });
            } else {
                roomsList.innerHTML = '<em>No rooms assigned to this reservation</em>';
            }
            
            // Set min date to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('new_check_in_date').min = today;
            
            // Set max date to 6 months after original check-in
            const originalCheckIn = new Date(checkIn);
            const maxDate = new Date(originalCheckIn);
            maxDate.setMonth(maxDate.getMonth() + 6);
            
            document.getElementById('new_check_in_date').max = maxDate.toISOString().split('T')[0];
            document.getElementById('rescheduleModal').style.display = 'block';
        }
        
        // Close reschedule modal
        function closeRescheduleModal() {
            document.getElementById('rescheduleModal').style.display = 'none';
            document.getElementById('date_validation_message').style.display = 'none';
        }
        
       // Replace the existing showDetailsModal function with this updated version:
        function showDetailsModal(reservationId) {
            const reservation = reservationData[reservationId];
            if (!reservation) return;
            const modalContent = document.getElementById('detailsModalContent');
            
            // Format the date for better readability
            const formatDate = (dateString) => {
                if (!dateString) return 'N/A';
                const date = new Date(dateString);
                return date.toLocaleDateString('en-US', {
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric'
                });
            };
            
            const formatCurrency = (amount) => {
                return '₱' + parseFloat(amount).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            };
            
            // Calculate total guests
            const totalGuests = (reservation.adult_count || 0) + (reservation.kid_count || 0) + (reservation.pwd_senior_count || 0);
            
            // Build rooms HTML
            let roomsHtml = '';
            if (reservation.rooms && reservation.rooms.length > 0) {
                reservation.rooms.forEach(room => {
                    roomsHtml += `<span class="room-tag">${room.room_name} (×${room.quantity_booked})</span> `;
                });
            } else {
                roomsHtml = '<em>No rooms assigned</em>';
            }
            
            let html = `
                <div>
                    <div class="details-row">
                        <div class="details-label">Reservation Code:</div>
                        <div class="details-value">${reservation.reservation_code}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Guest Name:</div>
                        <div class="details-value">${reservation.first_name} ${reservation.last_name}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Email:</div>
                        <div class="details-value">${reservation.email}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Reservation Type:</div>
                        <div class="details-value">${reservation.reservation_type || 'N/A'}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Status:</div>
                        <div class="details-value ${reservation.status === 'Approved' ? 'status-approved' : 'status-rejected'}">${reservation.status.charAt(0).toUpperCase() + reservation.status.slice(1)}</div>
                    </div>
                </div>
                <div>
                    <div class="details-row">
                        <div class="details-label">Check-in Date:</div>
                        <div class="details-value">${formatDate(reservation.check_in)}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Check-out Date:</div>
                        <div class="details-value">${formatDate(reservation.check_out)}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Tour Type:</div>
                        <div class="details-value">${reservation.tour_label || 'N/A'}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Schedule:</div>
                        <div class="details-value">${reservation.schedule || 'N/A'}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Booked Rooms:</div>
                        <div class="details-value">${roomsHtml}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Rescheduled:</div>
                        <div class="details-value ${reservation.is_rescheduled ? 'rescheduled' : ''}">${reservation.is_rescheduled ? 'Yes' : 'No'}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Created At:</div>
                        <div class="details-value">${formatDate(reservation.created_at)}</div>
                    </div>
                </div>
                <div>
                    <div class="details-row">
                        <div class="details-label">Total Guests:</div>
                        <div class="details-value"><strong>${totalGuests}</strong></div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Adults:</div>
                        <div class="details-value">${reservation.adult_count || 0}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Kids:</div>
                        <div class="details-value">${reservation.kid_count || 0}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">PWD/Senior:</div>
                        <div class="details-value">${reservation.pwd_senior_count || 0}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Base Price:</div>
                        <div class="details-value">${formatCurrency(reservation.base_price)}</div>
                    </div>
                </div>
                <div>
                    <div class="details-row">
                        <div class="details-label">Total Price:</div>
                        <div class="details-value">${formatCurrency(reservation.total_price)}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Payment Status:</div>
                        <div class="details-value">${reservation.payment_status}</div>
                    </div>
                    <div class="details-row">
                        <div class="details-label">Balance Due:</div>
                        <div class="details-value">${formatCurrency(reservation.balance_due)}</div>
                    </div>
                </div>
            `;
            
            modalContent.innerHTML = html;
            document.getElementById('detailsModal').style.display = 'block';
        }
        
        function closeDetailsModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }
        
        // Fix the date validation to allow both earlier and later dates
        document.getElementById('new_check_in_date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const message = document.getElementById('date_validation_message');
            const originalDate = new Date(document.getElementById('original_check_in').value);
            
            // Check if within 6 months of original
            const sixMonthsLater = new Date(originalDate);
            sixMonthsLater.setMonth(sixMonthsLater.getMonth() + 6);
            
            // Check if date is in the past
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                message.textContent = 'Cannot reschedule to a past date.';
                message.style.display = 'block';
                this.setCustomValidity('Cannot reschedule to a past date.');
            } else if (selectedDate > sixMonthsLater) {
                message.textContent = 'Must reschedule within 6 months of original booking date.';
                message.style.display = 'block';
                this.setCustomValidity('Must reschedule within 6 months of original booking date.');
            } else {
                message.style.display = 'none';
                this.setCustomValidity('');
            }
        });
        
        // Close the modal when clicking outside of it
        window.onclick = function(event) {
            const rescheduleModal = document.getElementById('rescheduleModal');
            const detailsModal = document.getElementById('detailsModal');
            if (event.target === rescheduleModal) {
                closeRescheduleModal();
            }
            if (event.target === detailsModal) {
                closeDetailsModal();
            }
        }
    </script>
</body>
</html>
<?php
$conn->close();
?>