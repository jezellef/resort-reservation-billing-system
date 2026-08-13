<?php
include 'db_connect.php';
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle Check-in
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkin_id'])) {
    $id = intval($_POST['checkin_id']);

    // Optional: Fetch amount_paid and total_price first (if logic needed for payment_status)
    $result = $conn->query("SELECT amount_paid, total_price FROM reservations WHERE id = $id");
    $row = $result->fetch_assoc();

    $amountPaid = floatval($row['amount_paid']);
    $totalPrice = floatval($row['total_price']);

    // Determine payment_status
    $paymentStatus = ($amountPaid >= $totalPrice) ? 'Paid' : 'Partial';

    // Now update all relevant columns
    $update = $conn->query("
        UPDATE reservations 
        SET 
            status = 'Checked In', 
            checkin_status = 'Checked In', 
            payment_status = '$paymentStatus',
            checkin_time = NOW()
        WHERE id = $id
    ");

    // Redirect after update
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Handle Check-out
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout_id'])) {
    $id = intval($_POST['checkout_id']);
    $hasDamage = isset($_POST['has_damage']) ? 1 : 0;
    $damageDescription = $conn->real_escape_string($_POST['damage_description'] ?? '');
    $damageFee = $hasDamage ? 2000 : 0;
    
    // Update reservation status
    $update = $conn->query("
        UPDATE reservations 
        SET 
            status = 'Checked Out',
            checkout_time = NOW(),
            has_damage = $hasDamage,
            damage_description = '$damageDescription',
            damage_fee = $damageFee,
            total_price = total_price + $damageFee
        WHERE id = $id
    ");
    
    echo "success";
    exit;
}

$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$whereClause = "";

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'approved';

switch ($filter) {
    case 'checked_in':
        $whereClause .= (empty($whereClause) ? "WHERE " : " AND ") . "status = 'Checked In'";
        break;
    case 'checked_out':
        $whereClause .= (empty($whereClause) ? "WHERE " : " AND ") . "status = 'Checked Out'";
        break;
    case 'approved':
    default:
        $whereClause .= (empty($whereClause) ? "WHERE " : " AND ") . "status = 'Approved'";
        break;
}

if (!empty($search)) {
    $whereClause = "WHERE (reservation_code LIKE '%$search%'
                    OR first_name LIKE '%$search%'
                    OR last_name LIKE '%$search%'
                    OR check_in LIKE '%$search%'
                    OR check_out LIKE '%$search%')";
    
    if ($filter == 'checked_in') {
        $whereClause .= " AND status = 'Checked In'";
    } else if ($filter == 'checked_out') {
        $whereClause .= " AND status = 'Checked Out'";
    } else if ($filter == 'approved') {
        $whereClause .= " AND status = 'Approved'";
    }
}

$sql = "SELECT id, reservation_code, first_name, last_name, email, contact_number, check_in, check_out, status, total_price, amount_paid, payment_status,
            guest_type, reservation_type, user_id, time, adult_count, kid_count, special_requests, extra_tent, corkage_quantity, corkage_fee_amount,
            pet_quantity, pet_fee_amount, base_price, extras_price, checkin_status, payment_status, 
            has_damage, damage_description, damage_fee
        FROM reservations
        $whereClause
        ORDER BY reservation_code DESC";

$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reservations Management</title>
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
        form {
            margin-bottom: 10px;
            display: inline-block;
        }
        form input[type="text"] {
            padding: 6px;
            width: 250px;
            margin-right: 10px;
        }
        form button {
            padding: 6px 12px;
            background-color: #2196F3;
            color: white;
            border: none;
            border-radius: 4px;
        }
        form button:hover {
            background-color: #1976D2;
        }
        a.back-button {
            padding: 6px 12px;
            background-color: #f44336;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-left: 10px;
        }
        a.back-button:hover {
            background-color: #d32f2f;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        button {
            font-weight: bold;
            font-size: 14px;
            padding: 6px 15px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.3s, transform 0.1s;
            margin: 10px 0;
        }
        button:hover {
            background-color: #45a049;
        }
        button:disabled {
            background-color: gray;
            cursor: not-allowed;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 30px;
            border: 1px solid #888;
            width: 500px;
            text-align: center;
            border-radius: 10px;
            position: relative;
        }
        .modal-content h2 {
            margin-bottom: 10px;
        }
        .modal-content button {
            margin: 10px;
        }
        .close {
            position: absolute;
            top: 10px;
            right: 20px;
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover {
            color: black;
        }
        .filter-buttons {
            margin: 15px 0;
        }
        .filter-button {
            padding: 8px 16px;
            background-color: #e0e0e0;
            border: none;
            border-radius: 4px;
            margin-right: 10px;
            cursor: pointer;
        }
        .filter-button.active {
            background-color: #4CAF50;
            color: white;
        }
        .damage-form {
            text-align: left;
            margin: 15px 0;
        }
        .damage-form label {
            display: block;
            margin: 10px 0;
        }
        .damage-form textarea {
            width: 100%;
            height: 100px;
            padding: 8px;
            margin-top: 5px;
        }
        .summary-section {
            margin-top: 30px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .current-checkins {
            margin-top: 50px;
            border-top: 2px solid #4CAF50;
            padding-top: 20px;
        }
        .warning {
            color: #d32f2f;
            font-weight: bold;
        }
        .info-box {
            background-color: #f9f9f9;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
    </style>
    <script>
        let currentId = null;
        let currentAction = '';
        let currentTotal = 0;
        
        function openCheckInModal(id, checkinDate, total) {
            const today = new Date().toISOString().split('T')[0];
            if (checkinDate !== today) {
                alert("Check-in is only allowed on the reservation date.");
                return;
            }
        
            const modal = document.getElementById("modal");
            const modalContent = document.querySelector("#modal .modal-content");
        
            modalContent.innerHTML = `
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Confirm Check In</h2>
                <p><strong>Total Price:</strong> ₱${total.toFixed(2)}</p>
                <p style="color:green;"><strong>Assumed Payment:</strong> PAID IN FULL</p>
                <div class="info-box">
                    <p>Guest will be added to the current check-ins list below after confirmation.</p>
                </div>
                <form method="POST">
                    <input type="hidden" name="checkin_id" value="${id}">
                    <button type="submit" class="action-button">Confirm Check In</button>
                </form>
            `;
            modal.style.display = "block";
        }
        
        function openCheckOutModal(id, firstName, lastName) {
            currentId = id;
            currentAction = 'Check Out';
            
            const modal = document.getElementById("modal");
            const modalContent = document.querySelector("#modal .modal-content");
            
            modalContent.innerHTML = `
                <span class="close" onclick="closeModal()">&times;</span>
                <h2>Check Out: ${firstName} ${lastName}</h2>
                
                <div class="info-box">
                    <p class="warning">IMPORTANT: Please verify if there are any damages or missing items before checkout!</p>
                    <p>If damages or missing items are found, a fee of ₱2,000 will be charged to the guest.</p>
                </div>
                
                <div class="damage-form">
                    <label>
                        <input type="checkbox" id="hasDamage" onchange="toggleDamageDescription()"> 
                        Report damage or missing items
                    </label>
                    
                    <div id="damageDescriptionContainer" style="display: none;">
                        <label>
                            Damage Description:
                            <textarea id="damageDescription" placeholder="Describe the damage or missing items..."></textarea>
                        </label>
                        <p class="warning">A ₱2,000 penalty fee will be added to the bill.</p>
                    </div>
                </div>
                
                <button id="confirmBtn" onclick="confirmCheckout()">Confirm Check Out</button>
                <button onclick="closeModal()">Cancel</button>
            `;
            modal.style.display = "block";
        }
        
        function toggleDamageDescription() {
            const hasDamage = document.getElementById('hasDamage').checked;
            document.getElementById('damageDescriptionContainer').style.display = hasDamage ? 'block' : 'none';
        }
        
        function confirmCheckout() {
            const hasDamage = document.getElementById('hasDamage').checked;
            const damageDescription = hasDamage ? document.getElementById('damageDescription').value : '';
            
            if (hasDamage && damageDescription.trim() === '') {
                alert("Please provide a description of the damage or missing items.");
                return;
            }
            
            const formData = new FormData();
            formData.append('checkout_id', currentId);
            if (hasDamage) {
                formData.append('has_damage', '1');
                formData.append('damage_description', damageDescription);
            }
            
            const xhr = new XMLHttpRequest();
            xhr.open("POST", "", true);
            xhr.onload = function () {
                if (xhr.responseText.trim() === "success") {
                    alert("Check-out successful!" + (hasDamage ? " A damage fee of ₱2,000 has been applied." : ""));
                    window.location.reload();
                } else {
                    alert("Error during check-out. Please try again.");
                }
                closeModal();
            };
            xhr.send(formData);
        }
        
        function closeModal() {
            document.getElementById("modal").style.display = "none";
        }
        
        function setFilter(filter) {
            window.location.href = `?filter=${filter}${window.location.search.includes('search=') ? '&' + window.location.search.substring(1).split('&').find(param => param.startsWith('search=')) : ''}`;
        }
        
        window.onclick = function (event) {
            const modal = document.getElementById("modal");
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
    <div class="main-content">
    <h1>Reservation Management</h1>
    
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
            <th>Guest Name</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Status</th>
            <th>Total Price</th>
            <th>Amount Paid</th>
            <th>Payment Status</th>
            <?php if (isset($_GET['filter']) && $_GET['filter'] == 'checked_out'): ?>
                <th>Damage Fee</th>
            <?php endif; ?>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php if ($result->num_rows > 0): ?>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr data-id="<?= $row['id']; ?>">
                    <td><?= $row['id']; ?></td>
                    <td><?= htmlspecialchars($row['reservation_code']); ?></td>
                    <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                    <td><?= htmlspecialchars($row['check_in']); ?></td>
                    <td><?= htmlspecialchars($row['check_out']); ?></td>
                    <td class="status-cell"><?= htmlspecialchars($row['status']); ?></td>
                    <td>₱<?= number_format($row['total_price'], 2); ?></td>
                    <td>₱<?= number_format($row['amount_paid'], 2); ?></td>
                    <td><?= htmlspecialchars($row['payment_status']); ?></td>
                    <?php if (isset($_GET['filter']) && $_GET['filter'] == 'checked_out'): ?>
                        <td>₱<?= number_format($row['damage_fee'] ?? 0, 2); ?></td>
                    <?php endif; ?>
                    <td>
                        <?php if ($row['status'] === 'Checked Out'): ?>
                            <button class="action-button" disabled>Completed</button>
                        <?php elseif ($row['status'] === 'Checked In'): ?>
                            <button class="action-button" onclick="openCheckOutModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['first_name']); ?>', '<?= htmlspecialchars($row['last_name']); ?>')">Check Out</button>
                        <?php elseif ($row['status'] === 'Approved'): ?>
                            <button class="action-button" onclick="openCheckInModal(<?= $row['id']; ?>, '<?= htmlspecialchars($row['check_in'] ?? '') ?>', <?= $row['total_price']; ?>)">Check In</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="<?= (isset($_GET['filter']) && $_GET['filter'] == 'checked_out') ? '11' : '10'; ?>">No reservations found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    
    <?php if (!isset($_GET['filter']) || $_GET['filter'] != 'checked_out'): ?>
    <!-- Current Check-ins Summary Section -->
    <div class="current-checkins">
        <h2>Current Check-ins</h2>
        <?php
        // Get today's check-ins
        $today = date('Y-m-d');
        $currentCheckins = $conn->query("
            SELECT id, reservation_code, first_name, last_name, check_in, check_out, contact_number, 
                   adult_count, kid_count, total_price, amount_paid, payment_status
            FROM reservations 
            WHERE status = 'Checked In'
            ORDER BY check_in ASC
        ");
        ?>
        
        <?php if ($currentCheckins->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Guest Name</th>
                        <th>Contact Number</th>
                        <th>Adults</th>
                        <th>Children</th>
                        <th>Check In</th>
                        <th>Check Out</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($checkin = $currentCheckins->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($checkin['reservation_code']); ?></td>
                            <td><?= htmlspecialchars($checkin['first_name'] . ' ' . $checkin['last_name']); ?></td>
                            <td><?= htmlspecialchars($checkin['contact_number']); ?></td>
                            <td><?= $checkin['adult_count']; ?></td>
                            <td><?= $checkin['kid_count']; ?></td>
                            <td><?= htmlspecialchars($checkin['check_in']); ?></td>
                            <td><?= htmlspecialchars($checkin['check_out']); ?></td>
                            <td>₱<?= number_format($checkin['total_price'], 2); ?></td>
                            <td><?= htmlspecialchars($checkin['payment_status']); ?></td>
                            <td>
                                <button class="action-button" onclick="openCheckOutModal(<?= $checkin['id']; ?>, '<?= htmlspecialchars($checkin['first_name']); ?>', '<?= htmlspecialchars($checkin['last_name']); ?>')">Check Out</button>
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
    <!-- Damage Reports Section -->
    <div class="summary-section">
        <h2>Damage Reports</h2>
        <?php
        $damageReports = $conn->query("
            SELECT id, reservation_code, first_name, last_name, check_in, check_out, 
                   damage_description, damage_fee
            FROM reservations 
            WHERE status = 'Checked Out' AND has_damage = 1
            ORDER BY checkout_time DESC
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
                        <th>Fee</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($report = $damageReports->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($report['reservation_code']); ?></td>
                            <td><?= htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?></td>
                            <td><?= htmlspecialchars($report['check_in']); ?></td>
                            <td><?= htmlspecialchars($report['check_out']); ?></td>
                            <td><?= htmlspecialchars($report['damage_description']); ?></td>
                            <td>₱<?= number_format($report['damage_fee'], 2); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No damage reports found.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div id="modal" class="modal">
        <div class="modal-content">
            <!-- Modal content will be dynamically inserted here -->
        </div>
    </div>
</div>
</body>
</html>