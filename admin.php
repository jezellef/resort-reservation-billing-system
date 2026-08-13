<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}
require_once 'db_connect.php';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'], $_POST['reservation_id'])) {
        $reservationId = intval($_POST['reservation_id']);
        $action = $_POST['action'] === 'approve' ? 'Approved' : 'Rejected';
        if ($action === 'Approved') {
        $stmt = $conn->prepare("UPDATE reservations SET status = ?, payment_status = 'Partial' WHERE id = ?");
        $stmt->bind_param("si", $action, $reservationId);
        } else {
            $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $action, $reservationId);
        }
        $stmt->execute();
        $stmt->close();
    }
}

$sql = "SELECT r.*, p.amount_paid, p.reference_number, p.file_path 
        FROM reservations r 
        LEFT JOIN payments p ON r.reservation_code = p.reservation_codes 
        WHERE r.status = 'Pending'";
$result = $conn->query($sql);

$debug_sql = "SELECT * FROM payments LIMIT 5";
$debug_result = $conn->query($debug_sql);
if ($debug_result) {
    $debug_data = $debug_result->fetch_all(MYSQLI_ASSOC);
}

date_default_timezone_set('Asia/Manila');
$philippinesDateTime = date('M d, Y h:i A');
?>
<!DOCTYPE html>
<html>
<head>
    <title>ADMIN</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles/adminstyle.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1050 !important;
            width: 100%;
            height: 100%;
            overflow: auto;
            outline: 0;
        }
        .modal-content {
            position: relative;
            pointer-events: auto !important;
            z-index: 1052 !important;
        }
        .modal .btn, .modal .btn-close {
            cursor: pointer !important;
            pointer-events: auto !important;
            z-index: 2002 !important;
        }
        .modal-backdrop {
            z-index: 1040 !important;
        }
        .btn-close {
            padding: 0.5rem 0.5rem;
            background: transparent url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23000'%3e%3cpath d='M.293.293a1 1 0 011.414 0L8 6.586 14.293.293a1 1 0 111.414 1.414L9.414 8l6.293 6.293a1 1 0 01-1.414 1.414L8 9.414l-6.293 6.293a1 1 0 01-1.414-1.414L6.586 8 .293 1.707a1 1 0 010-1.414z'/%3e%3c/svg%3e") center/1em auto no-repeat;
            border: 0;
            border-radius: 0.25rem;
            opacity: 0.7;
        }
        .modal-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }
        .modal-dialog {
            z-index: 1051 !important;
        }
        
        .modal-error {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }
        
        .modal-warning {
            color: #856404;
            background-color: #fff3cd;
            border-color: #ffeeba;
        }
        .payment-proof-img {
            max-width: 100px;
            max-height: 80px;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: transform 0.2s;
        }
        .payment-proof-img:hover {
            transform: scale(1.05);
            box-shadow: 0 0 8px rgba(0,0,0,0.2);
        }
        .payment-proof-modal-img {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 4px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            border: none;
        }
        .card-header {
            background-color: #f8f9fa;
            font-weight: bold;
            border-radius: 8px 8px 0 0 !important;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .badge {
            font-size: 0.9em;
            padding: 0.4em 0.6em;
        }
        .table-dark th {
            background-color: #343a40 !important;
            color: white !important;
        }
        .main-content {
            padding: 0;
        }
        .guest-info-row {
            border-bottom: 1px solid #eee;
            padding: 8px 0;
            margin-bottom: 0;
            display: flex;
            align-items: center;
        }
        .guest-info-label {
            font-weight: bold;
            color: #555;
        }
        .guest-detail-section {
            margin-bottom: 15px;
        }
        .guest-detail-heading {
            background-color: #f8f9fa;
            padding: 8px;
            border-radius: 4px;
            margin-bottom: 10px;
            font-weight: bold;
            color: #495057;
        }
        .modal-content {
            width: 100%;
        }
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
        }
        .room-item {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 8px;
        }
        .room-name {
            font-weight: bold;
            color: #495057;
        }
        .room-details {
            font-size: 0.9em;
            color: #6c757d;
        }
        @media (max-width: 768px) {
            .modal-dialog.modal-lg {
                max-width: 95%;
                margin: 0.5rem auto;
            }
            .col-4.guest-info-label,
            .col-8 {
                padding-left: 10px;
                padding-right: 10px;
            }
        }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body">
                    <h4><i class="fas fa-info-circle"></i> Admin Dashboard</h4>
                    <p class="text-muted mb-0">Review and manage pending reservations. Verify payment details before approval.</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-light">
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h4 class="mb-0"><?= $result->num_rows ?></h4>
                                <p class="text-muted mb-0">Pending Reservations</p>
                            </div>
                        </div>
                        <div class="col-6">
                               <div class="d-flex justify-content-end align-items-center mb-3">
                                <div class="logo me-2">
                                    <img src="icons/admin1.png" alt="User Logo" style="height: 60px;">
                                </div>
                                <div class="greeting">
                                    <h5 class="mb-0">Hello, <?= htmlspecialchars($_SESSION['admin_name'] ?? 'Admin') ?>!</h5>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">Pending Reservations</h2>
                    <button class="btn btn-warning btn-sm" onclick="window.location.reload();">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                </div>
                <div class="card-body">
                    <?php if ($result->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Reservation Code</th>
                                        <th>Email</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                              
                                        <th>Amount Paid</th>
                                        <th>Reference Number</th>
                                        <th>Proof of Payment</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): 
                                        // Get booked rooms for this reservation
                                        $bookedRooms = getBookedRooms($conn, $row['id']);
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['reservation_code'] ?? '') ?></td>
                                            <td><?= htmlspecialchars($row['email'] ?? '') ?></td>
                                            <td><?= $row['check_in'] ?></td>
                                            <td><?= $row['check_out'] ?></td>
                                        
                                            <td><?= isset($row['amount_paid']) ? '₱' . number_format($row['amount_paid'], 2) : 'N/A' ?></td>
                                            <td><?= htmlspecialchars($row['reference_number'] ?? 'N/A') ?></td>
                                            <td>
                                                <?php if (isset($row['file_path']) && !empty($row['file_path'])): 
                                                    $fileExtension = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
                                                    $isPdf = ($fileExtension === 'pdf');
                                                ?>
                                                    <?php if ($isPdf): ?>
                                                        <!-- For PDF files -->
                                                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#proofModal<?= $row['id'] ?>">
                                                            <i class="fas fa-file-pdf"></i> View PDF
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- For image files -->
                                                        <img src="<?= htmlspecialchars($row['file_path']) ?>" class="payment-proof-img" 
                                                             data-bs-toggle="modal" data-bs-target="#proofModal<?= $row['id'] ?>" 
                                                             alt="Proof of Payment">
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">No Proof Uploaded</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <!-- View Guest Details Button -->
                                                    <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#guestDetailsModal<?= $row['id'] ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>
                                                    <form action="approve_reject.php" method="POST">
                                                        <input type="hidden" name="reservation_id" value="<?= $row['id'] ?>">
                                                        <button type="submit" name="action" value="Approved" class="btn btn-success btn-sm">Approve</button>
                                                        <button type="submit" name="action" value="Rejected" class="btn btn-danger btn-sm">Reject</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        
                                        <!-- Payment Proof Modal for each reservation -->
                                        <?php if (isset($row['file_path']) && !empty($row['file_path'])): ?>
                                        <div class="modal fade" id="proofModal<?= $row['id'] ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-lg">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Proof of Payment - <?= $row['reservation_code'] ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body text-center">
                                                        <?php 
                                                        $fileExtension = strtolower(pathinfo($row['file_path'], PATHINFO_EXTENSION));
                                                        if ($fileExtension === 'pdf'): 
                                                        ?>
                                                            <iframe src="<?= htmlspecialchars($row['file_path']) ?>" 
                                                                    width="100%" height="600px" style="border: none;">
                                                            </iframe>
                                                        <?php else: ?>
                                                            <img src="<?= htmlspecialchars($row['file_path']) ?>" 
                                                                 class="payment-proof-modal-img" alt="Proof of Payment">
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Guest Details Modal -->
                                        <div class="modal fade" id="guestDetailsModal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="guestDetailsModalLabel<?= $row['id'] ?>" aria-hidden="true">
                                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header bg-info text-white">
                                                        <h5 class="modal-title" id="guestDetailsModalLabel<?= $row['id'] ?>">
                                                            <i class="fas fa-user-circle"></i> Guest Details - <?= htmlspecialchars($row['reservation_code'] ?? '') ?>
                                                        </h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <div class="container-fluid">
                                                            <!-- Personal Information Section -->
                                                            <div class="guest-detail-section">
                                                                <h4 class="guest-detail-heading">
                                                                    <i class="fas fa-id-card"></i> Personal Information
                                                                </h4>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Name:</div>
                                                                    <div class="col-8">
                                                                        <?= htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: 'N/A' ?>
                                                                    </div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Email:</div>
                                                                    <div class="col-8"><?= htmlspecialchars($row['email'] ?? 'N/A') ?></div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Phone Number:</div>
                                                                    <div class="col-8"><?= htmlspecialchars($row['contact_number'] ?? 'N/A') ?></div>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Reservation Details Section -->
                                                            <div class="guest-detail-section">
                                                                <h4 class="guest-detail-heading">
                                                                    <i class="fas fa-calendar-check"></i> Reservation Details
                                                                </h4>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Reservation Type:</div>
                                                                    <div class="col-8">
                                                                        <span class="badge bg-info"><?= htmlspecialchars($row['reservation_type'] ?? 'N/A') ?></span>
                                                                    </div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Check-in Date:</div>
                                                                    <div class="col-8"><?= $row['check_in'] ?? 'N/A' ?></div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Check-out Date:</div>
                                                                    <div class="col-8"><?= $row['check_out'] ?? 'N/A' ?></div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Adults:</div>
                                                                    <div class="col-8"><?= htmlspecialchars($row['adult_count'] ?? 'N/A') ?></div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Children:</div>
                                                                    <div class="col-8"><?= htmlspecialchars($row['kid_count'] ?? 'N/A') ?></div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Special Requests:</div>
                                                                    <div class="col-8">
                                                                        <?= !empty($row['special_requests']) ? htmlspecialchars($row['special_requests']) : '<em>None provided</em>' ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- NEW: Booked Rooms Section -->
                                                            <div class="guest-detail-section">
                                                                <h4 class="guest-detail-heading">
                                                                    <i class="fas fa-bed"></i> Booked Rooms
                                                                </h4>
                                                                <?php if (!empty($bookedRooms)): ?>
                                                                    <?php foreach ($bookedRooms as $room): ?>
                                                                        <div class="room-item">
                                                                            <div class="room-name"><?= htmlspecialchars($room['room_name']) ?></div>
                                                                            <div class="room-details">
                                                                                <span><strong>Quantity:</strong> <?= $room['quantity_booked'] ?></span> |
                                                                                <span><strong>Tour Type:</strong> <?= htmlspecialchars($room['tour_type'] ?? 'Not specified') ?></span> |
                                                                                <span><strong>Capacity:</strong> <?= $room['capacity'] ?> guests</span>
                                                                            </div>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                <?php else: ?>
                                                                    <div class="alert alert-warning">
                                                                        <i class="fas fa-exclamation-triangle"></i> No room information found for this reservation.
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <!-- Payment Details Section -->
                                                            <div class="guest-detail-section">
                                                                <h4 class="guest-detail-heading">
                                                                    <i class="fas fa-money-bill-wave"></i> Payment Details
                                                                </h4>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Total Price:</div>
                                                                    <div class="col-8">₱<?= number_format($row['total_price'] ?? 0, 2) ?></div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Amount Paid:</div>
                                                                    <div class="col-8">
                                                                        <?= isset($row['amount_paid']) ? '₱' . number_format($row['amount_paid'], 2) : 'N/A' ?>
                                                                    </div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Reference Number:</div>
                                                                    <div class="col-8"><?= htmlspecialchars($row['reference_number'] ?? 'N/A') ?></div>
                                                                </div>
                                                                <div class="row guest-info-row">
                                                                    <div class="col-4 guest-info-label">Payment Status:</div>
                                                                    <div class="col-8">
                                                                        <span class="badge bg-warning"><?= htmlspecialchars($row['payment_status'] ?? 'N/A') ?></span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p class="mb-0">No pending reservations found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- All your existing modals remain the same -->
<!-- Success Modal (Approval) -->
<div class="modal fade" id="approvalModal" tabindex="-1" aria-labelledby="approvalModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-success">
            <div class="modal-header">
                <h5 class="modal-title" id="approvalModalLabel">Reservation Approved</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="approvalMessage">Reservation has been approved successfully.</p>
            </div>
            <div class="modal-footer">
                <a href="admin.php" class="btn btn-success btn-lg">Close</a>
            </div>
        </div>
    </div>
</div>

<!-- Error/Warning Modal-->
<div class="modal fade" id="emailFailedModal" tabindex="-1" aria-labelledby="emailFailedModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-warning">
            <div class="modal-header">
                <h5 class="modal-title" id="emailFailedModalLabel">Email Notification Failed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="emailFailedMessage">Reservation status was updated, but the email notification could not be sent.</p>
                <p id="emailError"></p>
            </div>
            <div class="modal-footer">
                <a href="admin.php" class="btn btn-warning btn-lg">Close</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectionModal" tabindex="-1" aria-labelledby="rejectionModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-error">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectionModalLabel">Reservation Rejected</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="rejectionMessage">Reservation has been rejected.</p>
                <p id="rejectionReason"></p>
            </div>
            <div class="modal-footer">
                <a href="admin.php" class="btn btn-danger btn-lg">Close</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content modal-error">
            <div class="modal-header">
                <h5 class="modal-title" id="errorModalLabel">Error</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="errorMessage">An error occurred.</p>
            </div>
            <div class="modal-footer">
                <a href="admin.php" class="btn btn-secondary btn-lg">Close</a>
            </div>
        </div>
    </div>
</div>
    
<script>
    function getUrlParams() {
        const params = {};
        const queryString = window.location.search;
        const urlParams = new URLSearchParams(queryString);
        for (const [key, value] of urlParams) {
            params[key] = value;
        }
        return params;
    }
    function forceCloseModals() {
        $('.modal').modal('hide');
        $('.modal').hide();
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('body').css('overflow', '');
        $('body').css('padding-right', '');
        window.location.href = 'admin.php';
    }
    document.addEventListener('DOMContentLoaded', function() {
        const params = getUrlParams();
        
        document.querySelectorAll('.btn-close, .modal-footer .btn, .modal-footer a').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                forceCloseModals();
            });
        });
        
        $('.btn-close, .modal-footer .btn, .modal-footer a').on('click', function(e) {
            e.preventDefault();
            forceCloseModals();
        });
        
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                forceCloseModals();
            }
        });
        
        $(document).on('click', '.modal', function(e) {
            if ($(e.target).hasClass('modal')) {
                forceCloseModals();
            }
        });
        
        if (params.status === 'approved') {
            let msg = `Reservation #${params.id} approved.`;
            if (params.email === 'sent') {
                msg += ' Email sent.';
            } else if (params.email === 'failed') {
                document.getElementById('emailFailedMessage').textContent = msg + ' Email failed.';
                if (params.error) {
                    document.getElementById('emailError').textContent = "Error: " + decodeURIComponent(params.error);
                }
                $('#emailFailedModal').modal('show');
                return;
            }
            document.getElementById('approvalMessage').textContent = msg;
            $('#approvalModal').modal('show');
        }
        
        if (params.status === 'rejected') {
            let msg = `Reservation #${params.id} rejected.`;
            if (params.email === 'sent') {
                msg += ' Email sent.';
            } else if (params.email === 'failed') {
                document.getElementById('emailFailedMessage').textContent = msg + ' Email failed.';
                if (params.error) {
                    document.getElementById('emailError').textContent = "Error: " + decodeURIComponent(params.error);
                }
                $('#emailFailedModal').modal('show');
                return;
            }
            document.getElementById('rejectionMessage').textContent = msg;
            if (params.reason) {
                document.getElementById('rejectionReason').textContent = "Reason: " + decodeURIComponent(params.reason);
            }
            $('#rejectionModal').modal('show');
        }
    });
</script>
</body>
</html>