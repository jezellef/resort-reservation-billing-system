<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
require_once 'db_connect.php';
require_once 'room_availability_functions.php';
$success_message = '';
$error_message = '';
if (isset($_GET['deleted']) && $_GET['deleted'] == 'true') {
    $success_message = "Room has been deleted successfully!";
}
if (isset($_GET['delete'])) {
    $roomId = $_GET['delete'];
    // Check for active bookings
    $checkBookingsQuery = "
        SELECT COUNT(*) as booking_count 
        FROM reservation_room rr 
        JOIN reservations r ON rr.reservation_id = r.id 
        WHERE rr.room_id = ? AND r.status IN ('Approved', 'Checked In')
    ";
    $checkStmt = $conn->prepare($checkBookingsQuery);
    $checkStmt->bind_param("i", $roomId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $bookingData = $checkResult->fetch_assoc();
    if ($bookingData['booking_count'] > 0) {
        $error_message = "Cannot delete room: It has active bookings.";
    } else {
        $deleteSql = "DELETE FROM rooms WHERE id = ?";
        $stmt = $conn->prepare($deleteSql);
        $stmt->bind_param("i", $roomId);
        if ($stmt->execute()) {
            header("Location: rooms_public.php?deleted=true");
            exit;
        } else {
            $error_message = "Error deleting room: " . $stmt->error;
        }
    }
}
// Handle quantity update
if (isset($_POST['update_quantity'])) {
    $roomId = $_POST['room_id'];
    $newQuantity = intval($_POST['new_quantity']);
    if ($newQuantity < 0 || $newQuantity > 50) {
        $error_message = "Invalid quantity. Must be between 0 and 50.";
    } else {
        $updateQuery = "UPDATE rooms SET real_quantity = ?, quantity = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("iii", $newQuantity, $newQuantity, $roomId);
        
        if ($stmt->execute()) {
            // Auto-update status based on quantity
            if ($newQuantity == 0) {
                $statusQuery = "UPDATE rooms SET status = 'Unavailable' WHERE id = ?";
            } else {
                $statusQuery = "UPDATE rooms SET status = 'Available' WHERE id = ?";
            }
            $statusStmt = $conn->prepare($statusQuery);
            $statusStmt->bind_param("i", $roomId);
            $statusStmt->execute();
            
            $success_message = "Room quantity updated successfully!";
        } else {
            $error_message = "Error updating room quantity.";
        }
    }
}
// Handle status update
if (isset($_GET['update_status'])) {
    $roomId = $_GET['update_status'];
    $newStatus = $_GET['new_status'] == 'Available' ? 'Unavailable' : 'Available';
    if ($newStatus == 'Unavailable') {
        // Check for active bookings before making unavailable
        $checkBookingsQuery = "
            SELECT COUNT(*) as booking_count 
            FROM reservation_room rr 
            JOIN reservations r ON rr.reservation_id = r.id 
            WHERE rr.room_id = ? AND r.status IN ('Approved', 'Checked In')
        ";
        $checkStmt = $conn->prepare($checkBookingsQuery);
        $checkStmt->bind_param("i", $roomId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $bookingData = $checkResult->fetch_assoc();
        
        if ($bookingData['booking_count'] > 0) {
            $error_message = "Cannot make room unavailable: It has active bookings.";
        } else {
            $updateStatusSql = "UPDATE rooms SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($updateStatusSql);
            $stmt->bind_param("si", $newStatus, $roomId);
            if ($stmt->execute()) {
                header("Location: rooms_public.php");
                exit;
            }
        }
    } else {
        $updateStatusSql = "UPDATE rooms SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($updateStatusSql);
        $stmt->bind_param("si", $newStatus, $roomId);
        if ($stmt->execute()) {
            header("Location: rooms_public.php");
            exit;
        }
    }
}
// Fetch rooms with occupancy data
$today = date('Y-m-d');
$roomsQuery = "
    SELECT 
        r.*,
        COALESCE(SUM(CASE 
            WHEN res.status IN ('Approved', 'Checked In') 
            AND rr.check_in_date <= '$today' 
            AND rr.check_out_date > '$today' 
            THEN rr.quantity_booked 
            ELSE 0 
        END), 0) as currently_occupied
    FROM rooms r
    LEFT JOIN reservation_room rr ON r.id = rr.room_id
    LEFT JOIN reservations res ON rr.reservation_id = res.id
    GROUP BY r.id
    ORDER BY 
        CASE WHEN r.name LIKE '%Private%' OR r.name = 'Private Area' THEN 1 ELSE 0 END,
        r.name
";
$roomsResult = $conn->query($roomsQuery);
// Separate rooms into categories
$publicRooms = [];
$privateRooms = [];

while ($room = $roomsResult->fetch_assoc()) {
    $room['currently_available'] = max(0, $room['real_quantity'] - $room['currently_occupied']);
    
    if (stripos($room['name'], 'private') !== false) {
        $privateRooms[] = $room;
    } else {
        $publicRooms[] = $room;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Room Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
        .room-type-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin: 2rem 0 1rem 0;
        }
        .private-header {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .quantity-input {
            width: 80px;
        }
        .room-card {
            border-left: 4px solid #007bff;
            margin-bottom: 0.5rem;
        }
        .private-card {
            border-left-color: #dc3545;
        }
        .occupancy-badge {
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Room Management</h2>
            <div>
                <a href="admin_rooms.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
                <a href="admin_add_room.php" class="btn btn-success">+ Add Room</a>
            </div>
        </div>
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <!-- Public Rooms Section -->
        <div class="room-type-header">
            <h3><i class="bi bi-building"></i> Public Accommodations (<?= count($publicRooms) ?> types)</h3>
        </div>

        <?php if (!empty($publicRooms)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Room Name</th>
                            <th>Day Price</th>
                            <th>Night Price</th>
                            <th>Physical Units</th>
                            <th>Available Now</th>
                            <th>Status</th>
                            <th>Max Capacity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($publicRooms as $room): ?>
                        <tr class="room-card">
                            <td>
                                <strong><?= htmlspecialchars($room['name']) ?></strong>
                                <?php if ($room['currently_occupied'] > 0): ?>
                                    <br><span class="badge bg-warning occupancy-badge">
                                        <?= $room['currently_occupied'] ?> occupied today
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>₱<?= number_format($room['day_tour_price'], 2) ?></td>
                            <td>₱<?= number_format($room['night_tour_price'], 2) ?></td>
                            <td>
                                <form method="POST" style="display: inline-block;">
                                    <input type="hidden" name="room_id" value="<?= $room['id'] ?>">
                                    <div class="input-group input-group-sm quantity-input">
                                        <input type="number" name="new_quantity" value="<?= $room['real_quantity'] ?>" 
                                               min="0" max="50" class="form-control">
                                        <button type="submit" name="update_quantity" class="btn btn-outline-primary">
                                            <i class="bi bi-check"></i>
                                        </button>
                                    </div>
                                </form>
                            </td>
                            <td>
                                <span class="badge bg-<?= $room['currently_available'] > 0 ? 'success' : 'danger' ?>">
                                    <?= $room['currently_available'] ?> / <?= $room['real_quantity'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $room['status'] == 'Available' ? 'success' : 'secondary' ?>">
                                    <?= $room['status'] ?>
                                </span>
                            </td>
                            <td><?= $room['capacity'] ?> pax</td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="edit_room.php?id=<?= $room['id'] ?>" class="btn btn-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    
                                    <a href="?update_status=<?= $room['id'] ?>&new_status=<?= $room['status'] ?>" 
                                       class="btn btn-<?= $room['status'] == 'Available' ? 'warning' : 'success' ?>">
                                        <i class="bi bi-<?= $room['status'] == 'Available' ? 'pause' : 'play' ?>"></i>
                                    </a>
                                    
                                    <?php if ($room['currently_occupied'] == 0): ?>
                                        <a href="?delete=<?= $room['id'] ?>" class="btn btn-danger" 
                                           onclick="return confirm('Delete <?= htmlspecialchars($room['name']) ?>?');">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-danger" disabled title="Has active bookings">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No public rooms found.</div>
        <?php endif; ?>
        <!-- Private Area Section -->
        <div class="room-type-header private-header">
            <h3><i class="bi bi-house-heart"></i> Private Area (<?= count($privateRooms) ?> area)</h3>
        </div>

        <?php if (!empty($privateRooms)): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Area Name</th>
                            <th>Day Price</th>
                            <th>Night Price</th>
                            <th>Availability</th>
                            <th>Status</th>
                            <th>Max Capacity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($privateRooms as $room): ?>
                        <tr class="room-card private-card">
                            <td>
                                <strong><?= htmlspecialchars($room['name']) ?></strong>
                                <br><small class="text-muted">Exclusive area booking</small>
                                <?php if ($room['currently_occupied'] > 0): ?>
                                    <br><span class="badge bg-danger occupancy-badge">
                                        Currently booked
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>₱<?= number_format($room['day_tour_price'], 2) ?></td>
                            <td>₱<?= number_format($room['night_tour_price'], 2) ?></td>
                            <td>
                                <?php if ($room['currently_occupied'] > 0): ?>
                                    <span class="badge bg-danger">Booked</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Available</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $room['status'] == 'Available' ? 'success' : 'secondary' ?>">
                                    <?= $room['status'] ?>
                                </span>
                            </td>
                            <td><?= $room['capacity'] ?> pax</td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="edit_room.php?id=<?= $room['id'] ?>" class="btn btn-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    
                                    <a href="?update_status=<?= $room['id'] ?>&new_status=<?= $room['status'] ?>" 
                                       class="btn btn-<?= $room['status'] == 'Available' ? 'warning' : 'success' ?>">
                                        <i class="bi bi-<?= $room['status'] == 'Available' ? 'pause' : 'play' ?>"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No private areas found.</div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>