<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'db_connect.php';

// Set character set
$conn->set_charset("utf8");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form submission
$message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and validate inputs
    $guest_name = $conn->real_escape_string($_POST['guest_name']);
    $email = $conn->real_escape_string($_POST['email']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $visit_date = $conn->real_escape_string($_POST['visit_date']);
    $visit_time = $conn->real_escape_string($_POST['visit_time']);
    $pax = $conn->real_escape_string($_POST['pax']);
    $payment_amount = $conn->real_escape_string($_POST['payment_amount']);
    $payment_method = $conn->real_escape_string($_POST['payment_method']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $reference_number = isset($_POST['reference_number']) ? $conn->real_escape_string($_POST['reference_number']) : '';
    
    // Handle multiple room selections
    $rooms_data = $_POST['rooms']; // This will be an array
    $total_rooms = 0;
    
    // Calculate total rooms and determine visit type
    foreach ($rooms_data as $room_data) {
        if ($room_data['quantity'] > 0) {
            $total_rooms += intval($room_data['quantity']);
        }
    }
    
    // Determine visit type based on selected rooms
    $visit_type = 'public'; // Default
    foreach ($rooms_data as $room_id => $room_data) {
        if ($room_data['quantity'] > 0) {
            // Check if this room is private
            $room_check = $conn->query("SELECT name FROM rooms WHERE id = $room_id");
            if ($room_check && $room_check->num_rows > 0) {
                $room_info = $room_check->fetch_assoc();
                if (stripos($room_info['name'], 'private') !== false || 
                    stripos($room_info['name'], 'kubo') !== false || 
                    stripos($room_info['name'], 'hut') !== false) {
                    $visit_type = 'private';
                    break;
                }
            }
        }
    }
    
    $success = true;
    $conn->begin_transaction();
    
    try {
        // Insert a record for each room type selected
        foreach ($rooms_data as $room_id => $room_data) {
            if ($room_data['quantity'] > 0) {
                // Get room type name
                $room_query = $conn->query("SELECT name FROM rooms WHERE id = $room_id");
                $room_type = '';
                if ($room_query && $room_query->num_rows > 0) {
                    $room_info = $room_query->fetch_assoc();
                    $room_type = $room_info['name'];
                }
                
                // Insert multiple records if quantity > 1
                for ($i = 0; $i < intval($room_data['quantity']); $i++) {
                    $sql = "INSERT INTO walkins (guest_name, email, phone, visit_date, visit_time, visit_type, room_type, room_id, pax, payment_amount, payment_method, notes, reference_number)
                            VALUES ('$guest_name', '$email', '$phone', '$visit_date', '$visit_time', '$visit_type', '$room_type', '$room_id', '$pax', '$payment_amount', '$payment_method', '$notes', '$reference_number')";
                    
                    if (!$conn->query($sql)) {
                        throw new Exception("Error inserting record: " . $conn->error);
                    }
                }
            }
        }
        
        $conn->commit();
        $message = "<div class='alert alert-success'>Guest walk-in records saved successfully! Total rooms booked: $total_rooms</div>";
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<div class='alert alert-danger'>Error: " . $e->getMessage() . "</div>";
    }
}

// Get room options for select dropdown
$room_options = "";
$room_query = "SELECT id, name FROM rooms ORDER BY name";
$room_result = $conn->query($room_query);
$rooms_available = [];
if ($room_result && $room_result->num_rows > 0) {
    while($room = $room_result->fetch_assoc()) {
        $rooms_available[] = $room;
        $room_options .= "<option value='" . $room['id'] . "'>" . htmlspecialchars($room['name']) . "</option>";
    }
} else {
    $room_options = "<option value='0'>No rooms available</option>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Walk-in Registration</title>
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="styles/adminstyle.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --accent-color: #1abc9c;
            --light-bg: #f8f9fa;
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            --text-color: #333;
            --label-color: #2c3e50;
            --border-color: #ddd;
        }

        .container {
            max-width: 1200px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        h1 {
            text-align: center;
            color: var(--secondary-color);
            margin-bottom: 30px;
            font-weight: 600;
        }

        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
   
        .form-col {
            flex: 1;
            padding: 0 15px;
            min-width: 250px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--label-color);
        }

        input[type="text"], 
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="time"],
        input[type="number"],
        select, 
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            box-sizing: border-box;
            font-family: inherit;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }

        .form-actions {
            text-align: center;
            margin-top: 40px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 14px 30px;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            text-transform: uppercase;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--secondary-color), var(--primary-color));
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }

        .alert {
            padding: 15px;
            margin-bottom: 25px;
            border-radius: var(--border-radius);
            border-left: 5px solid;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-color: #155724;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-color: #721c24;
        }

        .section-title {
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            margin: 35px 0 25px;
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 1.3rem;
        }

        .required-field {
            color: #e74c3c;
            margin-left: 2px;
        }
        
        .go-back-btn {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #e74c3c;
            color: #fff;
            font-weight: bold;
            border-radius: 30px;
            text-decoration: none;
            transition: background 0.3s;
        }
        
        .go-back-btn:hover {
            background-color: #c0392b;
        }

        .room-selection {
            border: 1px solid var(--border-color);
            padding: 20px;
            border-radius: var(--border-radius);
            background: var(--light-bg);
        }

        .room-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .room-item:last-child {
            border-bottom: none;
        }

        .room-name {
            font-weight: 600;
            color: var(--secondary-color);
        }

        .room-quantity {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quantity-input {
            width: 80px !important;
            text-align: center;
        }

        .total-rooms {
            margin-top: 15px;
            padding: 10px;
            background: white;
            border-radius: var(--border-radius);
            text-align: center;
            font-weight: 600;
            color: var(--secondary-color);
        }

        /* Responsive styles */
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
            }
            .form-col {
                padding: 0 10px;
            }
            .container {
                padding: 20px 15px;
                margin: 20px 10px;
            }
            .room-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        /* Add subtle animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            animation: fadeIn 0.3s ease-out forwards;
        }

        /* Staggered animation delay for form groups */
        .form-row:nth-child(1) .form-group { animation-delay: 0.1s; }
        .form-row:nth-child(2) .form-group { animation-delay: 0.2s; }
        .form-row:nth-child(3) .form-group { animation-delay: 0.3s; }
        .form-row:nth-child(4) .form-group { animation-delay: 0.4s; }
    </style>
</head>
<body>
<?php include 'headers/adminheader.php'; ?>
<div class="main-content">
    <div class="container">
        <h1>Guest Walk-in Registration</h1>
        
        <?php echo $message; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <h3 class="section-title">Guest Information</h3>
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="guest_name">Guest Name <span class="required-field">*</span></label>
                        <input type="text" id="guest_name" name="guest_name" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="guest@example.com">
                    </div>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" placeholder="e.g., 09123456789">
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="pax">Number of Guests <span class="required-field">*</span></label>
                        <input type="number" id="pax" name="pax" min="1" value="1" required>
                    </div>
                </div>
            </div>

            <h3 class="section-title">Visit Details</h3>
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="visit_date">Visit Date <span class="required-field">*</span></label>
                        <input type="date" id="visit_date" name="visit_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="visit_time">Visit Time <span class="required-field">*</span></label>
                        <input type="time" id="visit_time" name="visit_time" required value="<?php echo date('H:i'); ?>">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label>Room Selection <span class="required-field">*</span></label>
                <div class="room-selection">
                    <?php foreach ($rooms_available as $room): ?>
                        <div class="room-item">
                            <span class="room-name"><?php echo htmlspecialchars($room['name']); ?></span>
                            <div class="room-quantity">
                                <label for="room_<?php echo $room['id']; ?>">Quantity:</label>
                                <input type="number" 
                                       id="room_<?php echo $room['id']; ?>" 
                                       name="rooms[<?php echo $room['id']; ?>][quantity]" 
                                       class="quantity-input" 
                                       min="0" 
                                       max="20" 
                                       value="0" 
                                       onchange="updateTotalRooms()">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="total-rooms" id="totalRooms">
                        Total Rooms: 0
                    </div>
                </div>
            </div>

            <h3 class="section-title">Payment Information</h3>
            <div class="form-row">
                <div class="form-col">
                    <div class="form-group">
                        <label for="payment_amount">Payment Amount (₱) <span class="required-field">*</span></label>
                        <input type="number" id="payment_amount" name="payment_amount" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="form-col">
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" onchange="toggleReferenceField()">
                            <option value="cash">Cash</option>
                            <option value="gcash">GCash</option>
                            <option value="rcbc">RCBC</option>
                        </select>
                    </div>
                
                    <div class="form-group" id="reference_group" style="display: none;">
                        <label for="reference_number">Reference Number <span class="required-field">*</span></label>
                        <input type="text" id="reference_number" name="reference_number">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes">Additional Notes</label>
                <textarea id="notes" name="notes" rows="3" placeholder="Any special requirements or additional information"></textarea>
            </div>
            
            <div class="form-actions">
                <input type="submit" value="Register Walk-in Guest" class="btn-primary">
            </div>
            
            <!-- Go Back Button -->
            <a href="walkin-all.php" class="go-back-btn">Go Back to Walk-in Records</a>
        </form>
    </div>

    <script>
        function toggleReferenceField() {
            var method = document.getElementById("payment_method").value;
            var referenceGroup = document.getElementById("reference_group");

            if (method !== "cash") {
                referenceGroup.style.display = "block";
                document.getElementById("reference_number").required = true;
            } else {
                referenceGroup.style.display = "none";
                document.getElementById("reference_number").required = false;
            }
        }

        function updateTotalRooms() {
            let total = 0;
            const quantityInputs = document.querySelectorAll('.quantity-input');
            
            quantityInputs.forEach(input => {
                total += parseInt(input.value) || 0;
            });
            
            document.getElementById('totalRooms').textContent = `Total Rooms: ${total}`;
        }

        // Initialize on page load
        document.addEventListener("DOMContentLoaded", function() {
            toggleReferenceField();
            updateTotalRooms();
        });

        // Form validation enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(event) {
                const guestName = document.getElementById('guest_name').value.trim();
                const pax = document.getElementById('pax').value;
                const paymentAmount = document.getElementById('payment_amount').value;
                
                // Check if at least one room is selected
                let totalRooms = 0;
                const quantityInputs = document.querySelectorAll('.quantity-input');
                quantityInputs.forEach(input => {
                    totalRooms += parseInt(input.value) || 0;
                });
                
                let errors = [];
                
                if (guestName === '') {
                    errors.push('Please enter a guest name');
                }
                
                if (pax <= 0) {
                    errors.push('Number of guests must be at least 1');
                }
                
                if (totalRooms === 0) {
                    errors.push('Please select at least one room');
                }
                
                if (paymentAmount <= 0) {
                    errors.push('Payment amount must be greater than 0');
                }
                
                if (errors.length > 0) {
                    event.preventDefault();
                    alert(errors.join('\n'));
                }
            });
        });
    </script>
</div>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>