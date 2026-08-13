<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// Get database connection
$mysqli = require 'database.php';

// Process actions
$action_message = '';
$action_status = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && isset($_POST['reservation_code'])) {
    $action = $_POST['action'];
    $reservation_code = $_POST['reservation_code'];
    
    // Validate reservation code
    $stmt = $mysqli->prepare("SELECT * FROM guest_reservations WHERE reservation_code = ?");
    $stmt->bind_param("s", $reservation_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $reservation = $result->fetch_assoc();
        
        if ($action === 'approve') {
            // Update reservation status to confirmed
            $update_stmt = $mysqli->prepare("UPDATE guest_reservations SET status = 'confirmed', updated_at = NOW() WHERE reservation_code = ?");
            $update_stmt->bind_param("s", $reservation_code);
            
            if ($update_stmt->execute()) {
                $action_message = "Reservation $reservation_code has been approved successfully!";
                $action_status = 'success';
                
                // Send email notification (in a production environment)
                // send_approval_email($reservation['email'], $reservation);
            } else {
                $action_message = "Failed to approve reservation. Please try again.";
                $action_status = 'danger';
            }
            $update_stmt->close();
        } elseif ($action === 'cancel') {
            // Update reservation status to cancelled
            $update_stmt = $mysqli->prepare("UPDATE guest_reservations SET status = 'cancelled', updated_at = NOW() WHERE reservation_code = ?");
            $update_stmt->bind_param("s", $reservation_code);
            
            if ($update_stmt->execute()) {
                $action_message = "Reservation $reservation_code has been cancelled.";
                $action_status = 'warning';
                
                // Send email notification (in a production environment)
                // send_cancellation_email($reservation['email'], $reservation);
            } else {
                $action_message = "Failed to cancel reservation. Please try again.";
                $action_status = 'danger';
            }
            $update_stmt->close();
        }
    } else {
        $action_message = "Invalid reservation code.";
        $action_status = 'danger';
    }
    $stmt->close();
}

// Get pending reservations
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total pending reservations
$count_stmt = $mysqli->prepare("SELECT COUNT(*) FROM guest_reservations WHERE status = 'pending'");
$count_stmt->execute();
$count_stmt->bind_result($total_records);
$count_stmt->fetch();
$count_stmt->close();

$total_pages = ceil($total_records / $limit);

// Get pending reservations with pagination
$stmt = $mysqli->prepare("SELECT reservation_code, first_name, last_name, email, contact_number, check_in_date, check_out_date, 
                         guest_adult, guest_kid, guest_adult + guest_kid as total_guests, created_at 
                         FROM guest_reservations 
                         WHERE status = 'pending' 
                         ORDER BY created_at DESC 
                         LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$pending_reservations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Pending Reservations</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@400..700&display=swap" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="adminstyle.css">
</head>
<body>
<div class="sidebar">
        <div>
            <div class="resort-name">Rainbow Forest Paradise Resort and Campsite</div>
            <div class="nav-item">
                <img src="icons/home.png" alt="Dashboard Icon" class="nav-icon">
                <span>Dashboard</span>
            </div>

            <a href="reservations.php" class="nav-item">
                <img src="icons/reservations.png" alt="Settings Icon" class="nav-icon">
                <span>Reservations</span>
            </a>
            <a href="private_reservations.php" class="nav-item sub-nav-item">
                <span>Private</span>
            </a>
            <a href="public_reservations.php" class="nav-item sub-nav-item">
                <span>Public</span>
            </a>
 
            <div class="nav-item">
                <img src="icons/payments.png" alt="Payments Icon" class="nav-icon">
                <span>Payments</span>
            </div>
            <div class="nav-item">
                <img src="icons/calendar.png" alt="Calendar Icon" class="nav-icon">
                <span>Calendar</span>
            </div>
            <div class="nav-item">
                <img src="icons/reports.png" alt="Reports Icon" class="nav-icon">
                <span>Reports</span>
            </div>
            <div class="nav-item">
                <img src="icons/rooms.png" alt="Rooms Icon" class="nav-icon">
                <span>Rooms</span>
            </div>
    
            <a href="content_management.php" class="nav-item">
                <img src="icons/edit.png" alt="Content Management Icon" class="nav-icon">
                <span>Content Management</span>
            </a>
            
        </div>
        <div>
            <a href="admin_settings.php" class="nav-item">
                <img src="icons/settings.png" alt="Settings Icon" class="nav-icon">
                <span>Settings</span>
            </a>  

            <a href="admin_logout.php" class="nav-item">
                <img src="icons/logout.png" alt="Logout Icon" class="nav-icon">
                <span>Logout</span>
            </a>  
        </div>
    </div>
    
     <!-- Main Content -->
     <div class="main-content">
     <div class="col-lg-12 col-md-12 admin-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Pending Reservations</h2>
                    <div>
                        <a href="admin.php" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <?php if (!empty($action_message)): ?>
                    <div class="alert alert-<?php echo $action_status; ?> alert-dismissible fade show" role="alert">
                        <?php echo $action_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-hourglass-split me-2"></i>Pending Reservations</h5>
                            <span class="badge bg-dark"><?php echo $total_records; ?> reservations</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Guest Name</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Guests</th>
                                        <th>Date Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($pending_reservations)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4">No pending reservations found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($pending_reservations as $reservation): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($reservation['reservation_code']); ?></td>
                                                <td><?php echo htmlspecialchars($reservation['first_name']); ?></td>
                                                <td><?php echo htmlspecialchars($reservation['check_in_date']); ?></td>
                                                <td><?php echo htmlspecialchars($reservation['check_out_date']); ?></td>
                                                <td><?php echo htmlspecialchars($reservation['total_guests']); ?> 
                                                    <small class="text-muted">(<?php echo htmlspecialchars($reservation['guest_adult']); ?> adults, <?php echo htmlspecialchars($reservation['guest_kid']); ?> kids)</small>
                                                </td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($reservation['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-primary view-details" 
                                                            data-code="<?php echo htmlspecialchars($reservation['reservation_code']); ?>"
                                                            data-name="<?php echo htmlspecialchars($reservation['first_name']); ?>"
                                                            data-email="<?php echo htmlspecialchars($reservation['email']); ?>"
                                                            data-phone="<?php echo htmlspecialchars($reservation['contact_number']); ?>"
                                                            data-checkin="<?php echo htmlspecialchars($reservation['check_in_date']); ?>"
                                                            data-checkout="<?php echo htmlspecialchars($reservation['check_out_date']); ?>"
                                                            data-adults="<?php echo htmlspecialchars($reservation['guest_adult']); ?>"
                                                            data-kids="<?php echo htmlspecialchars($reservation['guest_kid']); ?>">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success approve-btn" 
                                                            data-code="<?php echo htmlspecialchars($reservation['reservation_code']); ?>">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger cancel-btn"
                                                            data-code="<?php echo htmlspecialchars($reservation['reservation_code']); ?>">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-center mt-4">
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Reservation Details Modal -->
    <div class="modal fade" id="reservationDetailsModal" tabindex="-1" aria-labelledby="reservationDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="reservationDetailsModalLabel">Reservation Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="card mb-3">
                        <div class="card-header bg-light">Guest Information</div>
                        <div class="card-body">
                            <p><strong>Name:</strong> <span id="modal-guest-name"></span></p>
                            <p><strong>Email:</strong> <span id="modal-guest-email"></span></p>
                            <p><strong>Phone:</strong> <span id="modal-guest-phone"></span></p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header bg-light">Reservation Information</div>
                        <div class="card-body">
                            <p><strong>Reservation Code:</strong> <span id="modal-reservation-code"></span></p>
                            <p><strong>Check-in Date:</strong> <span id="modal-checkin-date"></span></p>
                            <p><strong>Check-out Date:</strong> <span id="modal-checkout-date"></span></p>
                            <p><strong>Number of Adults:</strong> <span id="modal-adults"></span></p>
                            <p><strong>Number of Kids:</strong> <span id="modal-kids"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Approve Confirmation Modal -->
    <div class="modal fade confirmation-modal" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel">Confirm Approval</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to approve reservation <strong id="approve-code"></strong>?</p>
                    <p class="text-muted">This will send a confirmation email to the guest and move the reservation to the confirmed list.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="reservation_code" id="approve-form-code">
                        <button type="submit" class="btn btn-success">Approve Reservation</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cancel Confirmation Modal -->
    <div class="modal fade confirmation-modal" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Confirm Cancellation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to cancel reservation <strong id="cancel-code"></strong>?</p>
                    <p class="text-muted">This will send a cancellation email to the guest and move the reservation to the cancelled list.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">No, Keep it</button>
                    <form method="post" action="">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="reservation_code" id="cancel-form-code">
                        <button type="submit" class="btn btn-danger">Yes, Cancel Reservation</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap & jQuery Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // View Reservation Details
            $('.view-details').click(function() {
                $('#modal-reservation-code').text($(this).data('code'));
                $('#modal-guest-name').text($(this).data('name'));
                $('#modal-guest-email').text($(this).data('email'));
                $('#modal-guest-phone').text($(this).data('phone'));
                $('#modal-checkin-date').text($(this).data('checkin'));
                $('#modal-checkout-date').text($(this).data('checkout'));
                $('#modal-adults').text($(this).data('adults'));
                $('#modal-kids').text($(this).data('kids'));
                
                $('#reservationDetailsModal').modal('show');
            });
            
            // Approve Reservation
            $('.approve-btn').click(function() {
                const code = $(this).data('code');
                $('#approve-code').text(code);
                $('#approve-form-code').val(code);
                $('#approveModal').modal('show');
            });
            
            // Cancel Reservation
            $('.cancel-btn').click(function() {
                const code = $(this).data('code');
                $('#cancel-code').text(code);
                $('#cancel-form-code').val(code);
                $('#cancelModal').modal('show');
            });
        });
    </script>
</body>
</html>