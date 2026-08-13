<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Function to get user status
function getUserStatus() {
    if (isset($_SESSION["user_id"])) {
        try {
            $mysqli = require __DIR__ . "/database.php";
            $stmt = $mysqli->prepare("SELECT first_name, last_name FROM user WHERE id = ?");
            $stmt->bind_param("i", $_SESSION["user_id"]);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            return $user ?: null;
        } catch (Exception $e) {
            error_log("getUserStatus error: " . $e->getMessage());
            return null;
        }
    }
    return null;
}

$current_user = getUserStatus();
if (!$current_user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Database connection
try {
    $mysqli = require __DIR__ . "/database.php";
    if (!$mysqli) {
        die("Database connection failed: " . mysqli_connect_error());
    }

    // Fetch basic reservations
    $stmt = $mysqli->prepare("SELECT * FROM reservations WHERE user_id = ? ORDER BY check_in DESC");
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Try to get room information for each reservation
    foreach ($reservations as $key => $reservation) {
        try {
            // Check if reservation_room data exists
            $stmt = $mysqli->prepare("SELECT COUNT(*) as count FROM reservation_room WHERE reservation_id = ?");
            $stmt->bind_param("i", $reservation['id']);
            $stmt->execute();
            $roomCount = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($roomCount['count'] > 0) {
                // Get room details
                $stmt = $mysqli->prepare("
                    SELECT 
                        rm.name as room_name,
                        rm.room_type,
                        rm.description as room_description,
                        rr.quantity_booked,
                        rr.tour_type
                    FROM reservation_room rr 
                    LEFT JOIN rooms rm ON rr.room_id = rm.id 
                    WHERE rr.reservation_id = ?
                ");
                $stmt->bind_param("i", $reservation['id']);
                $stmt->execute();
                $roomData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                $reservations[$key]['room_name'] = !empty($roomData) ? $roomData[0]['room_name'] : 'Room info unavailable';
                $reservations[$key]['quantity_booked'] = !empty($roomData) ? $roomData[0]['quantity_booked'] : 1;
                $reservations[$key]['tour_type'] = !empty($roomData) ? $roomData[0]['tour_type'] : '';
                $reservations[$key]['room_description'] = !empty($roomData) ? $roomData[0]['room_description'] : '';
            } else {
                // No room data found
                $reservations[$key]['room_name'] = 'No room assigned';
                $reservations[$key]['quantity_booked'] = 1;
                $reservations[$key]['tour_type'] = '';
                $reservations[$key]['room_description'] = '';
            }
            
        } catch (Exception $e) {
            error_log("Room data fetch error: " . $e->getMessage());
            $reservations[$key]['room_name'] = 'Room info error';
            $reservations[$key]['quantity_booked'] = 1;
            $reservations[$key]['tour_type'] = '';
            $reservations[$key]['room_description'] = '';
        }
    }

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

// Function to get status badge color
function getStatusColor($status) {
    switch($status) {
        case 'Approved': return '#28a745';
        case 'Pending': return '#ffc107';
        case 'Checked In': return '#17a2b8';
        case 'Checked Out': return '#6c757d';
        case 'Rejected': return '#dc3545';
        default: return '#6c757d';
    }
}

// Function to format currency
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Bookings</title>
    <link rel="stylesheet" href="styles/mystyle.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
     body {
           background: linear-gradient(135deg, #3f704d 0%, #043927 100%);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }

        .container {
            max-width: 1000px;
            margin: 40px auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 2.5rem;
        }

        .booking-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 5px solid #667eea;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .booking-code {
            font-size: 1.2rem;
            font-weight: bold;
            color: #333;
        }

        .status-badge {
            padding: 5px 15px;
            border-radius: 20px;
            color: white;
            font-size: 0.9rem;
            font-weight: bold;
        }

        .room-name {
            font-size: 1.1rem;
            color: #667eea;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .booking-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-icon {
            width: 35px;
            height: 35px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .detail-text {
            flex: 1;
        }

        .detail-label {
            font-size: 0.8rem;
            color: #666;
            display: block;
        }

        .detail-value {
            font-weight: 600;
            color: #333;
        }

        .no-bookings {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .back-btn {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.3s ease;
        }

        .back-btn:hover {
            background: #5a67d8;
        }

        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .booking-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
<?php include 'headers/header.php'; ?>
    
    <div class="container">
        <h1><i class="fas fa-calendar-check"></i> Your Bookings</h1>

        <?php if (count($reservations) > 0): ?>
            <?php foreach ($reservations as $reservation): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <div class="booking-code">
                            Booking #<?= htmlspecialchars($reservation['reservation_code']) ?>
                        </div>
                        <div class="status-badge" style="background-color: <?= getStatusColor($reservation['status']) ?>;">
                            <?= htmlspecialchars($reservation['status']) ?>
                        </div>
                    </div>

                    <div class="room-name">
                        <i class="fas fa-bed"></i> <?= htmlspecialchars($reservation['room_name']) ?>
                        <?php if ($reservation['tour_type']): ?>
                            <span style="font-size: 0.9rem; color: #666;"> - <?= htmlspecialchars($reservation['tour_type']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="booking-details">
                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <div class="detail-text">
                                <span class="detail-label">Check-in</span>
                                <span class="detail-value"><?= date('M j, Y', strtotime($reservation['check_in'])) ?></span>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-calendar-minus"></i>
                            </div>
                            <div class="detail-text">
                                <span class="detail-label">Check-out</span>
                                <span class="detail-value"><?= date('M j, Y', strtotime($reservation['check_out'])) ?></span>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="detail-text">
                                <span class="detail-label">Guests</span>
                                <span class="detail-value"><?= ($reservation['adult_count'] + $reservation['kid_count']) ?> People</span>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <i class="fas fa-peso-sign"></i>
                            </div>
                            <div class="detail-text">
                                <span class="detail-label">Total Price</span>
                                <span class="detail-value"><?= formatCurrency($reservation['total_price']) ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($reservation['room_description']): ?>
                        <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 5px;">
                            <strong>Room Details:</strong> <?= htmlspecialchars(substr($reservation['room_description'], 0, 150)) ?>...
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-bookings">
                <i class="fas fa-calendar-times" style="font-size: 3rem; color: #ddd; margin-bottom: 20px;"></i>
                <h3>No bookings found</h3>
                <p>You haven't made any reservations yet.</p>
            </div>
        <?php endif; ?>

        <div style="text-align: center;">
            <a href="account.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Account
            </a>
        </div>
    </div>
</body>
</html>