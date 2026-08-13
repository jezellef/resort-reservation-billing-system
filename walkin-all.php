<?php
include 'db_connect.php';
$conn->set_charset("utf8");

// Fetch all walk-in records with room type
$sql = "SELECT w.id, w.guest_name, w.phone, w.visit_date, w.visit_time, w.visit_type, 
               w.payment_amount, w.payment_method, w.room_type, w.pax, w.reference_number, w.notes
        FROM walkins w
        ORDER BY w.visit_date DESC, w.visit_time DESC";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Walk-in Guest List</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
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
            width: 300px;
            background: linear-gradient(to right, #3498db, #1abc9c);
        }
        .coontainer {
            max-width: 1500px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0,0,0,0.05);
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        .btn-new {
            display: inline-block;
            margin-bottom: 20px;
            background: #4ca1af;
            color: #fff;
            padding: 10px 20px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
        }
        .btn-new:hover {
            background: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 13px;
        }
        th, td {
            padding: 10px 8px;
            border: 1px solid #ddd;
            text-align: left;
            vertical-align: top;
        }
        th {
            background-color: #f7f7f7;
            color: #2c3e50;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tr:hover {
            background-color: #e8f4f8;
        }
        .time-12hr {
            font-weight: 600;
            color: #2c3e50;
        }
        .visit-type-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .visit-type-public {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        .visit-type-private {
            background-color: #fce4ec;
            color: #c2185b;
        }
        .payment-method {
            text-transform: uppercase;
            font-weight: 600;
        }
        .payment-cash {
            color: #4caf50;
        }
        .payment-gcash {
            color: #2196f3;
        }
        .payment-rcbc {
            color: #ff9800;
        }
        .notes-cell {
            max-width: 150px;
            word-wrap: break-word;
            font-size: 12px;
        }
        .reference-number {
            font-family: monospace;
            font-size: 12px;
            background-color: #f5f5f5;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .summary-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            min-width: 120px;
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            display: block;
        }
        .stat-label {
            font-size: 12px;
            opacity: 0.9;
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .coontainer {
                padding: 15px;
                margin: 10px;
            }
            table {
                font-size: 12px;
            }
            th, td {
                padding: 8px 6px;
            }
        }

        @media (max-width: 768px) {
            .summary-stats {
                justify-content: center;
            }
            .stat-card {
                min-width: 100px;
                padding: 10px 15px;
            }
            .stat-number {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
<div class="d-flex justify-content-between align-items-center mb-4">
         <h2 class="dashboard-title">Walk-in Dashboard</h2>
</div>

    <div class="coontainer">
        <h1>Walk-in Guest Records</h1>
        
        <?php 
        // Calculate summary statistics
        $total_guests = 0;
        $total_revenue = 0;
        $total_rooms = 0;
        $payment_methods = ['cash' => 0, 'gcash' => 0, 'rcbc' => 0];
        
        if ($result && $result->num_rows > 0) {
            $result_copy = $conn->query($sql); // Get a fresh copy for calculations
            while($row = $result_copy->fetch_assoc()) {
                $total_guests++;
                $total_revenue += $row['payment_amount'];
                $total_rooms++;
                if (isset($payment_methods[$row['payment_method']])) {
                    $payment_methods[$row['payment_method']]++;
                }
            }
        }
        ?>
        
        <div class="summary-stats">
            <div class="stat-card">
                <span class="stat-number"><?php echo $total_guests; ?></span>
                <span class="stat-label">Total Bookings</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $total_rooms; ?></span>
                <span class="stat-label">Total Rooms</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">₱<?php echo number_format($total_revenue, 0); ?></span>
                <span class="stat-label">Total Revenue</span>
            </div>
        </div>
        
        <a href="walk-in.php" class="btn-new">+ New Walk-in</a>

        <?php if ($result && $result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Guest Name</th>
                        <th>Phone</th>
                        <th>Visit Date</th>
                        <th>Visit Time</th>
                        <th>Room Type</th>
                        <th>Type</th>
                        <th>Guests (Pax)</th>
                        <th>Payment</th>
                        <th>Method</th>
                      
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['guest_name']) ?></td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>
                            <td><?= date('M j, Y', strtotime($row['visit_date'])) ?></td>
                            <td class="time-12hr">
                                <?php 
                                // Convert military time to 12-hour format
                                $time_12hr = date('g:i A', strtotime($row['visit_time']));
                                echo $time_12hr;
                                ?>
                            </td>
                            <td><?= htmlspecialchars($row['room_type']) ?></td>
                            <td>
                                <span class="visit-type-badge visit-type-<?= $row['visit_type'] ?>">
                                    <?= ucfirst($row['visit_type']) ?>
                                </span>
                            </td>
                            <td style="text-align: center;"><?= $row['pax'] ?></td>
                            <td>₱<?= number_format($row['payment_amount'], 2) ?></td>
                            <td>
                                <span class="payment-method payment-<?= strtolower($row['payment_method']) ?>">
                                    <?= strtoupper($row['payment_method']) ?>
                                </span>
                            </td>
                            
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p style="text-align: center; color: #666; font-style: italic; padding: 40px;">No walk-in guests found.</p>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

<?php $conn->close(); ?>