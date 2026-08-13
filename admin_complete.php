<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

// Get database connection
$mysqli = require 'database.php';

// Pagination
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;

// Get completed reservations count
$count_query = "SELECT COUNT(*) FROM guest_reservations WHERE status = 'completed'";
$count_result = $mysqli->query($count_query);
$total_records = $count_result->fetch_row()[0];
$total_pages = ceil($total_records / $records_per_page);

// Get completed reservations with pagination
$query = "SELECT reservation_code, first_name, last_name, check_in_date, check_out_date, 
          guest_adult + guest_kid as total_guests, room_type, payment_amount, payment_method, 
          created_at, updated_at
          FROM guest_reservations 
          WHERE status = 'completed' 
          ORDER BY check_out_date DESC 
          LIMIT ?, ?";

$stmt = $mysqli->prepare($query);
$stmt->bind_param("ii", $offset, $records_per_page);
$stmt->execute();
$result = $stmt->get_result();
$completed_reservations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate total revenue from completed reservations
$revenue_query = "SELECT SUM(payment_amount) as total_revenue FROM guest_reservations WHERE status = 'completed'";
$revenue_result = $mysqli->query($revenue_query);
$total_revenue = $revenue_result->fetch_assoc()['total_revenue'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completed Reservations - Admin</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
            color: white;
        }
        .sidebar-link {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: block;
            padding: 10px 15px;
            transition: all 0.3s;
        }
        .sidebar-link:hover, .sidebar-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar-link i {
            margin-right: 10px;
        }
        .admin-content {
            padding: 20px;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .badge.bg-completed {
            background-color: #0d6efd;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-2 col-md-3 px-0 sidebar">
                <div class="d-flex flex-column">
                    <div class="p-3 text-center">
                        <h4>Resort Admin</h4>
                        <hr>
                    </div>
                    <a href="admin_dashboard.php" class="sidebar-link">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                    <a href="admin_pending.php" class="sidebar-link">
                        <i class="bi bi-hourglass-split"></i> Pending Reservations
                    </a>
                    <a href="admin_confirmed.php" class="sidebar-link">
                        <i class="bi bi-check-circle"></i> Confirmed Reservations
                    </a>
                    <a href="admin_complete.php" class="sidebar-link active">
                        <i class="bi bi-calendar-check"></i> Completed Reservations
                    </a>
                    <a href="admin_cancelled.php" class="sidebar-link">
                        <i class="bi bi-x-circle"></i> Cancelled Reservations
                    </a>
                    <a href="admin_settings.php" class="sidebar-link">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                    <hr>
                    <a href="admin_logout.php" class="sidebar-link">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-10 col-md-9 ms-auto admin-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>Completed Reservations</h2>
                    <div>
                        <span class="me-2"><?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin'; ?></span>
                        <a href="admin_logout.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                </div>
                
                <!-- Stats Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5><i class="bi bi-calendar-check text-primary me-2"></i>Total Completed Reservations</h5>
                                <h3><?php echo $total_records; ?></h3>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="bi bi-cash-stack text-success me-2"></i>Total Revenue</h5>
                                <h3>₱<?php echo number_format($total_revenue, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Reservations Table -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Completed Reservations</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Code</th>
                                        <th>Guest</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Room Type</th>
                                        <th>Guests</th>
                                        <th>Amount</th>
                                        <th>Payment</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($completed_reservations)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-3">No completed reservations found</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($completed_reservations as $reservation): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($reservation['reservation_code']); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($reservation['check_in_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($reservation['check_out_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($reservation['room_type'] ?? 'Standard'); ?></td>
                                                <td><?php echo $reservation['total_guests']; ?></td>
                                                <td>₱<?php echo number_format($reservation['payment_amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($reservation['payment_method'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <a href="admin_view_reservation.php?code=<?php echo $reservation['reservation_code']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <div class="card-footer">
                        <nav>
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">Previous</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $current_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">Next</a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <a class="page-link" href="#">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>