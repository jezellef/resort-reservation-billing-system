<?php
session_start();
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] != 'Super Admin') {
    header("Location: access_denied.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate Report</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
        .dashboard-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #2c3e50;
            position: relative;
            padding-bottom: 10px;
        }
        .dashboard-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            height: 4px;
            width: 290px;
            background: linear-gradient(to right, #3498db, #1abc9c);
        }
        .report-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            background: #f8f9fa;
        }
        .report-section h5 {
            color: #495057;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #dee2e6;
        }
        .date-inputs {
            display: none;
        }
        .date-inputs.show {
            display: block;
        }
    </style>
</head>
<body class="bg-light">
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="dashboard-title">Reports Dashboard</h2>
</div>
        
<div class="container mt-0">
    <div class="card shadow">
        <div class="card-header bg-success text-white">
            <h4 class="mb-0 text-center">Generate Reports</h4>
        </div>
        <div class="card-body">
            <form action="report_preview.php" method="get" id="reportForm">
                
                <!-- Report Type Selection -->
                <div class="report-section">
                    <h5><i class="fas fa-chart-bar"></i> Select Report Type</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="report_type" id="reservation_report" value="reservation" required>
                                <label class="form-check-label" for="reservation_report">
                                    <strong>Reservation Reports</strong><br>
                                    <small class="text-muted">Generate reports for all reservations, public, or private bookings</small>
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="report_type" id="earnings_report" value="earnings" required>
                                <label class="form-check-label" for="earnings_report">
                                    <strong>Earnings Reports</strong><br>
                                    <small class="text-muted">Generate financial earnings reports</small>
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="report_type" id="checkin_report" value="checkin" required>
                                <label class="form-check-label" for="checkin_report">
                                    <strong>Check-in Reports</strong><br>
                                    <small class="text-muted">Reports for guests who checked in</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="report_type" id="checkout_report" value="checkout" required>
                                <label class="form-check-label" for="checkout_report">
                                    <strong>Check-out Reports</strong><br>
                                    <small class="text-muted">Reports for guests who checked out</small>
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="report_type" id="sales_report" value="sales" required>
                                <label class="form-check-label" for="sales_report">
                                    <strong>Sales Reports</strong><br>
                                    <small class="text-muted">Comprehensive sales analysis reports</small>
                                </label>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="radio" name="report_type" id="today_report" value="today" required>
                                <label class="form-check-label" for="today_report">
                                    <strong>Today's Reservations</strong><br>
                                    <small class="text-muted">Current day reservations (no date range needed)</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Date Range Section -->
                <div class="report-section date-inputs" id="dateSection">
                    <h5><i class="fas fa-calendar"></i> Date Range</h5>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" name="start_date" id="start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" name="end_date" id="end_date" class="form-control">
                        </div>
                    </div>
                </div>

                <!-- Reservation Type Filter (for applicable reports) -->
                <div class="report-section date-inputs" id="reservationTypeSection">
                    <h5><i class="fas fa-filter"></i> Reservation Type</h5>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <select name="type" id="type" class="form-select">
                                <option value="all">Both Public and Private</option>
                                <option value="public_all">Public Only</option> 
                                <option value="private_all">Private Only</option> 
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Status Filter (for check-in/check-out reports) -->
                <div class="report-section" id="statusSection" style="display: none;">
                    <h5><i class="fas fa-check"></i> Status Filter</h5>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <select name="status_filter" id="status_filter" class="form-select">
                                <option value="all">All Statuses</option>
                                <option value="completed">Completed Only</option>
                                <option value="pending">Pending Only</option>
                                <option value="confirmed">Confirmed Only</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Payment Status Filter (for sales reports) -->
                <div class="report-section" id="paymentSection" style="display: none;">
                    <h5><i class="fas fa-money-bill"></i> Payment Status</h5>
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <select name="payment_status" id="payment_status" class="form-select">
                                <option value="all">All Payment Statuses</option>
                                <option value="paid">Fully Paid</option>
                                <option value="partial">Partially Paid</option>
                                <option value="unpaid">Unpaid</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Generate Button -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5">
                        <i class="fas fa-file-alt"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportTypeInputs = document.querySelectorAll('input[name="report_type"]');
    const dateSection = document.getElementById('dateSection');
    const reservationTypeSection = document.getElementById('reservationTypeSection');
    const statusSection = document.getElementById('statusSection');
    const paymentSection = document.getElementById('paymentSection');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');

    reportTypeInputs.forEach(input => {
        input.addEventListener('change', function() {
            const selectedType = this.value;
            
            // Reset all sections
            dateSection.classList.remove('show');
            statusSection.style.display = 'none';
            paymentSection.style.display = 'none';
            reservationTypeSection.classList.remove('show');
            
            // Remove required attributes
            startDate.removeAttribute('required');
            endDate.removeAttribute('required');
            
            switch(selectedType) {
                case 'today':
                    // Today's report doesn't need date inputs
                    reservationTypeSection.classList.add('show');
                    statusSection.style.display = 'block';
                    break;
                    
                case 'checkin':
                case 'checkout':
                    dateSection.classList.add('show');
                    reservationTypeSection.classList.add('show');
                    statusSection.style.display = 'block';
                    startDate.setAttribute('required', 'required');
                    endDate.setAttribute('required', 'required');
                    break;
                    
                case 'sales':
                    dateSection.classList.add('show');
                    reservationTypeSection.classList.add('show');
                    paymentSection.style.display = 'block';
                    startDate.setAttribute('required', 'required');
                    endDate.setAttribute('required', 'required');
                    break;
                    
                default: // reservation, earnings
                    dateSection.classList.add('show');
                    reservationTypeSection.classList.add('show');
                    startDate.setAttribute('required', 'required');
                    endDate.setAttribute('required', 'required');
                    break;
            }
        });
    });

    // Set today's date as default
    const today = new Date().toISOString().split('T')[0];
    startDate.value = today;
    endDate.value = today;
});
</script>

</body>
</html>