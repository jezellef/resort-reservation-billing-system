<?php
// Making sure we have access to necessary variables
if (!isset($title, $columns, $data, $start_date, $end_date, $report_type, $filter_value)) {
    die("Error: Missing required variables for report template");
}

// Convert report data to a format the template can use
$results = $data;
$total_earnings = 0;

// Calculate totals based on report type
if ($report_type == 'sales' && isset($summary)) {
    $total_checkin = $summary['total_checkin'];
    $total_checkout = $summary['total_checkout'];
    $total_overall = $summary['total_overall'];
    $total_earnings = $total_overall;
} elseif ($report_type == 'reservation') {
    foreach ($results as $row) {
        $total_earnings += $row['total_amount'];
    }
} elseif ($report_type == 'check_in' || $report_type == 'check_out') {
    foreach ($results as $row) {
        $total_earnings += $row['amount'];
    }
}

// Calculate breakdowns for different report types
if ($report_type == 'sales' || $report_type == 'generate_earnings') {
    $public_earnings = 0;
    $private_earnings = 0;
    
    foreach ($results as $row) {
        if ($row['reservation_type'] == 'public') {
            $public_earnings += isset($row['total_amount']) ? $row['total_amount'] : 0;
        } else {
            $private_earnings += isset($row['total_amount']) ? $row['total_amount'] : 0;
        }
    }
    
    if (!isset($total_earnings) || $total_earnings == 0) {
        $total_earnings = $public_earnings + $private_earnings;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
    :root {
        --forest-green: #2e8b57;
        --light-green: #e8f5e9;
        --medium-green: #a5d6a7;
    }
    
    body {
        background-color: #f8fdf8 !important;
    }
    
    .card {
        border-color: var(--medium-green);
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    
    .card-header {
        background-color: var(--forest-green) !important;
    }
    
    .table-success {
        background-color: var(--light-green) !important;
    }
    
    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(165, 214, 167, 0.1);
    }
    
    .btn-success {
        background-color: var(--forest-green);
        border-color: var(--forest-green);
    }
    
    .text-success {
        color: var(--forest-green) !important;
    }
    
    .border-success {
        border-color: var(--forest-green) !important;
    }
    
    .report-header {
        background-color: var(--light-green);
        padding: 15px;
        border-radius: 5px;
        break-after: always; /* Force page break after header in PDF */
    }
    
    .report-footer {
        background-color: var(--light-green);
        padding: 10px;
        border-radius: 5px;
        margin-top: 20px;
        text-align: center;
        font-size: 12px;
    }
    
    /* Table handling for PDF export */
    .table-responsive {
        page-break-inside: avoid;
        break-inside: avoid;
    }
    
    .table {
        border-collapse: collapse;
        width: 100%;
    }
    
    /* Force a new page before summary if needed */
    .mt-4 h5:first-child {
        page-break-before: auto;
        break-before: auto;
    }
    
    /* Print styles */
    @media print {
        body {
            background-color: white !important;
        }
        
        .btn, .no-print {
            display: none !important;
        }
        
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        
        .report-header {
            background-color: #f9f9f9 !important;
            -webkit-print-color-adjust: exact;
            page-break-after: always;
            break-after: always;
        }
        
        .report-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            background-color: #f9f9f9 !important;
            -webkit-print-color-adjust: exact;
            page-break-before: avoid;
            break-before: avoid;
        }
        
        .page-break {
            page-break-before: always;
            break-before: page;
        }
        
        /* Prevent tables from breaking across pages when possible */
        table {
            page-break-inside: avoid;
            break-inside: avoid;
        }
        
        /* If table is too big, allow clean breaks between rows */
        tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }
    }
</style>
</head>
<body class="bg-light">
    <?php include 'headers/adminheader.php'; ?>
    <div class="main-content">
        <div class="container mt-4">
            <div class="card shadow mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo $title; ?></h4>
                    <div>
                        <button id="printReport" class="btn btn-warning me-2">Print</button>
                        <button id="downloadPDF" class="btn btn-warning">Download PDF</button>
                    </div>
                </div>
                <div class="card-body" id="reportContent">
                    <div class="report-header mb-4">
                        <div class="row align-items-center">
                            <div class="col-md-2 text-center">
                                <!-- Logo can go here if needed -->
                            </div>
                            <div class="col-md-10">
                                <h2 class="text-success mb-0">Rainbow Forest Paradise Resort and Campsite</h2>
                                <p class="text-muted">Brgy. Cuyambay, Tanay, Rizal</p>
                                <div class="row">
                                    <div class="col-md-8">
                                        <h3 class="text-dark"><?php echo $title; ?></h3>
                                        <p class="lead">Period: <?php echo date('F d, Y', strtotime($start_date)); ?> to <?php echo date('F d, Y', strtotime($end_date)); ?></p>
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <p><strong>Total Records:</strong> <?php echo count($results); ?></p>
                                        <p><strong>Generated:</strong> <?php echo date('M d, Y h:i A'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <hr class="border-success">
                    </div>

                    <!-- Report Content Based on Type -->
                    <?php if ($report_type == 'sales' || $action == 'generate_earnings'): ?>
                        <!-- Sales/Earnings Report -->
                        <div class="row justify-content-center">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0">Earnings Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <table class="table table-bordered">
                                            <tbody>
                                                <?php if ($filter_value == 'both' || $filter_value == 'public'): ?>
                                                <tr>
                                                    <th>Public Reservations</th>
                                                    <td>₱<?php echo number_format($public_earnings, 2); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                
                                                <?php if ($filter_value == 'both' || $filter_value == 'private'): ?>
                                                <tr>
                                                    <th>Private Reservations</th>
                                                    <td>₱<?php echo number_format($private_earnings, 2); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                                
                                                <?php if ($filter_value == 'both'): ?>
                                                <tr class="table-success">
                                                    <th>Total Earnings</th>
                                                    <td>₱<?php echo number_format($total_earnings, 2); ?></td>
                                                </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0">Statistics</h5>
                                    </div>
                                    <div class="card-body">
                                        <p><strong>Total Reservations:</strong> <?php echo count($results); ?></p>
                                        <p><strong>Average Booking Value:</strong> 
                                            ₱<?php echo count($results) > 0 ? number_format($total_earnings / count($results), 2) : '0.00'; ?>
                                        </p>
                                        <?php if ($report_type == 'sales' && isset($summary)): ?>
                                        <p><strong>Total Check-in Payments:</strong> ₱<?php echo number_format($total_checkin, 2); ?></p>
                                        <p><strong>Total Check-out Payments:</strong> ₱<?php echo number_format($total_checkout, 2); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (count($results) > 0): ?>
                            <div class="table-responsive mt-4">
                                <table class="table table-striped table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <th><?php echo $column; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['reservation_id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['reservation_code']); ?></td>
                                                <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['check_in_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['check_out_date'])); ?></td>
                                                <td>₱<?php echo number_format($row['checkin_amount'], 2); ?></td>
                                                <td>₱<?php echo number_format($row['checkout_amount'], 2); ?></td>
                                                <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                                <td><?php echo ucfirst(htmlspecialchars($row['reservation_type'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-success">
                                        <tr>
                                            <th colspan="6" class="text-end">Total:</th>
                                            <th>₱<?php echo number_format($total_checkin, 2); ?></th>
                                            <th>₱<?php echo number_format($total_checkout, 2); ?></th>
                                            <th>₱<?php echo number_format($total_overall, 2); ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($report_type == 'check_in' || $report_type == 'check_out'): ?>
                        <!-- Check-in/Check-out Report -->
                        <?php if (count($results) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <th><?php echo $column; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['reservation_id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['reservation_codes']); ?></td>
                                                <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                                                <td>₱<?php echo number_format($row['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($row['payment_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($row['payment_notes']); ?></td>
                                                <td><?php echo ucfirst(htmlspecialchars($row['reservation_type'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-success">
                                        <tr>
                                            <th colspan="5" class="text-end">Total:</th>
                                            <th>₱<?php echo number_format($total_earnings, 2); ?></th>
                                            <th colspan="4"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="mt-4">
                                <h5>Payment Summary</h5>
                                <p><strong>Total Payments:</strong> <?php echo count($results); ?></p>
                                <p><strong>Total Amount:</strong> ₱<?php echo number_format($total_earnings, 2); ?></p>
                                
                                <?php
                                // Get payment methods breakdown
                                $payment_methods = [];
                                foreach ($results as $row) {
                                    $method = $row['payment_method'];
                                    if (!isset($payment_methods[$method])) {
                                        $payment_methods[$method] = 0;
                                    }
                                    $payment_methods[$method]++;
                                }
                                
                                foreach ($payment_methods as $method => $count) {
                                    echo "<p><strong>" . $method . " Payments:</strong> " . $count . "</p>";
                                }
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No payment records found for the selected criteria.
                            </div>
                        <?php endif; ?>
                    <?php elseif ($report_type == 'room_inventory'): ?>
                        <!-- Room Inventory Report -->
                        <?php if (count($results) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <th><?php echo $column; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['room_id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['room_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                                                <td><?php echo htmlspecialchars($row['description']); ?></td>
                                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                                <td><?php echo !empty($row['last_maintenance_date']) ? date('M d, Y', strtotime($row['last_maintenance_date'])) : 'N/A'; ?></td>
                                                <td><?php echo htmlspecialchars($row['reservation_count']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-4">
                                <h5>Room Inventory Summary</h5>
                                <p><strong>Total Rooms:</strong> <?php echo count($results); ?></p>
                                
                                <?php
                                // Room status breakdown
                                $total_reservations = 0;
                                $room_statuses = [];
                                $room_types = [];
                                
                                foreach ($results as $row) {
                                    $status = $row['status'];
                                    $type = $row['room_type'];
                                    $total_reservations += $row['reservation_count'];
                                    
                                    if (!isset($room_statuses[$status])) {
                                        $room_statuses[$status] = 0;
                                    }
                                    $room_statuses[$status]++;
                                    
                                    if (!isset($room_types[$type])) {
                                        $room_types[$type] = 0;
                                    }
                                    $room_types[$type]++;
                                }
                                
                                echo "<p><strong>Total Reservations in Period:</strong> " . $total_reservations . "</p>";
                                
                                echo "<h6 class='mt-3'>Room Status:</h6>";
                                foreach ($room_statuses as $status => $count) {
                                    echo "<p><strong>" . ucfirst($status) . " Rooms:</strong> " . $count . "</p>";
                                }
                                
                                echo "<h6 class='mt-3'>Room Types:</h6>";
                                foreach ($room_types as $type => $count) {
                                    echo "<p><strong>" . $type . ":</strong> " . $count . "</p>";
                                }
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No room inventory data found for the selected criteria.
                            </div>
                        <?php endif; ?>
                    <?php elseif ($report_type == 'reservation'): ?>
                        <!-- Reservation Report -->
                        <?php if (count($results) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-bordered">
                                    <thead class="table-dark">
                                        <tr>
                                            <?php foreach ($columns as $column): ?>
                                                <th><?php echo $column; ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($results as $row): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($row['reservation_id']); ?></td>
                                                <td><?php echo htmlspecialchars($row['reservation_code']); ?></td>
                                                <td><?php echo htmlspecialchars($row['guest_name']); ?></td>
                                                <td><?php echo htmlspecialchars($row['contact_number']); ?></td>
                                                <td><?php echo htmlspecialchars($row['room_type']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['check_in_date'])); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($row['check_out_date'])); ?></td>
                                                <td>₱<?php echo number_format($row['total_amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($row['status']); ?></td>
                                                <td><?php echo ucfirst(htmlspecialchars($row['type'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="table-success">
                                        <tr>
                                            <th colspan="7" class="text-end">Total:</th>
                                            <th>₱<?php echo number_format($total_earnings, 2); ?></th>
                                            <th colspan="2"></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="mt-4">
                                <h5>Reservation Summary</h5>
                                <p><strong>Total Reservations:</strong> <?php echo count($results); ?></p>
                                <p><strong>Total Revenue:</strong> ₱<?php echo number_format($total_earnings, 2); ?></p>
                                
                                <?php
                                // Reservation status breakdown
                                $statuses = [];
                                $types = [];
                                
                                foreach ($results as $row) {
                                    $status = $row['status'];
                                    $type = $row['type'];
                                    
                                    if (!isset($statuses[$status])) {
                                        $statuses[$status] = 0;
                                    }
                                    $statuses[$status]++;
                                    
                                    if (!isset($types[$type])) {
                                        $types[$type] = 0;
                                    }
                                    $types[$type]++;
                                }
                                
                                echo "<h6 class='mt-3'>Reservation Status:</h6>";
                                foreach ($statuses as $status => $count) {
                                    echo "<p><strong>" . ucfirst($status) . " Reservations:</strong> " . $count . "</p>";
                                }
                                
                                echo "<h6 class='mt-3'>Reservation Types:</h6>";
                                foreach ($types as $type => $count) {
                                    echo "<p><strong>" . ucfirst($type) . ":</strong> " . $count . "</p>";
                                }
                                ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                No reservations found for the selected criteria.
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No data available. Please select a valid report type.
                        </div>
                    <?php endif; ?>
                    
                    <div class="mt-4 text-center no-print">
                        <a href="generate_report.php" class="btn btn-secondary">Back to Report Generator</a>
                    </div>
                    
                    <!-- Report Footer with Page Numbers -->
                    <div class="report-footer mt-5">
                        <div class="row">
                            <div class="col-4 text-start">
                                <small>Rainbow Forest Paradise Resort</small>
                            </div>
                            <div class="col-4 text-center">
                                <small>Page <span class="page-num">1</span></small>
                            </div>
                            <div class="col-4 text-end">
                                <small><?php echo date('Y-m-d'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<script>
    // Function to update page numbers for print view
    function updatePageNumbers() {
        const pageNumElements = document.querySelectorAll('.page-num');
        pageNumElements.forEach((el, index) => {
            el.textContent = (index + 1);
        });
    }
    
    // Execute when page loads
    window.onload = function() {
        updatePageNumbers();
    }
    
    // Print functionality
    document.getElementById('printReport').addEventListener('click', function() {
        window.print();
    });

    // Generate PDF
    document.getElementById('downloadPDF').addEventListener('click', function() {
        // Get the current URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        
        // Construct the URL for the PDF generator script
        let pdfUrl = 'reportgenerate.php?';
        
        // Add all current parameters
        for (const [key, value] of urlParams.entries()) {
            pdfUrl += `${key}=${encodeURIComponent(value)}&`;
        }
        
        // Add a parameter to indicate this is a PDF request
        pdfUrl += 'format=pdf';
        
        // Redirect to the PDF generator
        window.location.href = pdfUrl;
    });
</script>
</body>
</html>