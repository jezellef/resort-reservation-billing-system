<?php
session_start();

// Check if reservation details are in the session
if (!isset($_SESSION['reservation_details'])) {
    echo "No reservation details found in session.";
    exit;
}

// Retrieve reservation details
$reservation = $_SESSION['reservation_details'];

// Pricing configuration (similar to the previous document)
$tourPricing = [
    'whole_day' => [
        'brackets' => [
            ['max' => 10, 'price' => 12000],
            ['max' => 15, 'price' => 13000],
            ['max' => 20, 'price' => 15000],
            ['max' => 25, 'price' => 16000],
            ['max' => 30, 'price' => 18000]
        ],
        'additionalPerPerson' => 600,
        'displayName' => "Whole Day Tour"
    ],
    'day_tour' => [
        'brackets' => [
            ['max' => 10, 'price' => 7000],
            ['max' => 15, 'price' => 8000],
            ['max' => 20, 'price' => 9000],
            ['max' => 25, 'price' => 10000],
            ['max' => 30, 'price' => 11000]
        ],
        'additionalPerPerson' => 400,
        'displayName' => "Day Tour"
    ],
    'night_tour' => [
        'brackets' => [
            ['max' => 10, 'price' => 8000],
            ['max' => 15, 'price' => 9000],
            ['max' => 20, 'price' => 10000],
            ['max' => 25, 'price' => 11000],
            ['max' => 30, 'price' => 12000]
        ],
        'additionalPerPerson' => 500,
        'displayName' => "Night Tour"
    ]
];

$extras_prices = [
    'extra_mattress' => 150,
    'extra_pillow' => 50,
    'extra_blanket' => 50
];

// Calculate tour base price (similar logic from previous document)
function calculateTourPrice($tourType, $totalGuests) {
    global $tourPricing;
    
    if (!isset($tourPricing[$tourType])) {
        throw new Exception("Invalid tour type");
    }
    
    $pricing = $tourPricing[$tourType];
    
    $basePrice = 0;
    foreach ($pricing['brackets'] as $bracket) {
        if ($totalGuests <= $bracket['max']) {
            $basePrice = $bracket['price'];
            break;
        }
    }
    
    if ($basePrice === 0) {
        $lastBracket = end($pricing['brackets']);
        $extraGuests = $totalGuests - 30;
        $basePrice = $lastBracket['price'] + ($extraGuests * $pricing['additionalPerPerson']);
    }
    
    return $basePrice;
}

// Calculate total guests
$totalGuests = $reservation['adult_count'] + ($reservation['kid_count'] ?? 0);

// Calculate base tour price
$baseTourPrice = calculateTourPrice($reservation['tour_type'], $totalGuests);

// Calculate extras price
$total_extras = 0;
$extrasData = [];
foreach (['extra_mattress', 'extra_pillow', 'extra_blanket'] as $extra) {
    $extra_count = $reservation['extras'][$extra] ?? 0;
    if ($extra_count > 0) {
        $extra_total = $extra_count * $extras_prices[$extra];
        $total_extras += $extra_total;
        $extrasData[$extra] = $extra_count;
    }
}

// Calculate total bill
$totalBill = $baseTourPrice + $total_extras;

// Generate reservation code if not already present
if (!isset($reservation['reservation_code'])) {
    $date = date('Ymd');
    $random_string = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    $reservation_code = $date . '-' . $random_string;
    
    $_SESSION['reservation_details']['reservation_code'] = $reservation_code;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - Rainbow Forest Paradise</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f0f4f8;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .page-header {
            text-align: center;
            margin-bottom: 20px;
            color: #0369a1;
        }
        .billing-container {
            max-width: 800px;
            margin: 0 auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .bill-section {
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f0f9ff;
            border-radius: 8px;
        }
        
        .bill-section h3 {
            color: #0369a1;
            border-bottom: 2px solid #bae6fd;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        
        .bill-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            padding: 10px;
            background-color: white;
            border-radius: 6px;
        }
        
        .bill-total {
            font-weight: bold;
            font-size: 1.2rem;
            color: #0c4a6e;
            text-align: right;
            margin-top: 20px;
            padding: 15px;
            background-color: #e0f2fe;
            border-radius: 8px;
        }

        .actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-primary {
            background-color: #007bff;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1>Billing Details - Reservation Confirmation</h1>
        </div>
        
        <div class="billing-container">
            <!-- Reservation Details -->
            <div class="bill-section">
                <h3>Reservation Information</h3>
                <div class="bill-row">
                    <span>Reservation Code:</span>
                    <span><?php echo htmlspecialchars($reservation['reservation_code']); ?></span>
                </div>
                <div class="bill-row">
                    <span>Guest Name:</span>
                    <span><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></span>
                </div>
                <div class="bill-row">
                    <span>Contact Email:</span>
                    <span><?php echo htmlspecialchars($reservation['email']); ?></span>
                </div>
                <div class="bill-row">
                    <span>Contact Number:</span>
                    <span><?php echo htmlspecialchars($reservation['contact_number']); ?></span>
                </div>
            </div>
            
            <!-- Tour Details -->
            <div class="bill-section">
                <h3>Tour Details</h3>
                <div class="bill-row">
                    <span>Tour Type:</span>
                    <span><?php echo htmlspecialchars($tourPricing[$reservation['tour_type']]['displayName']); ?></span>
                </div>
                <div class="bill-row">
                    <span>Check-in Date:</span>
                    <span><?php echo htmlspecialchars($reservation['check_in']); ?></span>
                </div>
                <div class="bill-row">
                    <span>Check-out Date:</span>
                    <span><?php echo htmlspecialchars($reservation['check_out']); ?></span>
                </div>
                <div class="bill-row">
                    <span>Adult Guests:</span>
                    <span><?php echo htmlspecialchars($reservation['adult_count']); ?></span>
                </div>
                <div class="bill-row">
                    <span>Child Guests:</span>
                    <span><?php echo htmlspecialchars($reservation['kid_count'] ?? '0'); ?></span>
                </div>
            </div>
            
            <!-- Billing Breakdown -->
            <div class="bill-section">
                <h3>Billing Breakdown</h3>
                <div class="bill-row">
                    <span>Base Tour Price:</span>
                    <span>₱<?php echo number_format($baseTourPrice, 2); ?></span>
                </div>
                
                <?php if ($total_extras > 0): ?>
                    <div class="bill-row">
                        <span>Extras:</span>
                        <span>₱<?php echo number_format($total_extras, 2); ?></span>
                    </div>
                    
                    <?php foreach ($extrasData as $extra => $quantity): ?>
                        <div class="bill-row">
                            <span><?php 
                                $extraNames = [
                                    'extra_mattress' => 'Extra Mattress',
                                    'extra_pillow' => 'Extra Pillow',
                                    'extra_blanket' => 'Extra Blanket'
                                ];
                                echo htmlspecialchars($extraNames[$extra]) . " (x{$quantity})";
                            ?>:</span>
                            <span>₱<?php echo number_format($quantity * $extras_prices[$extra], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Total Bill -->
            <div class="bill-total">
                Total Bill: ₱<?php echo number_format($totalBill, 2); ?>
            </div>
            
            <!-- Action Buttons -->
            <div class="actions">
                <a href="guest_reservation.php" class="btn btn-secondary">Modify Reservation</a>
                <a href="payment.php" class="btn btn-primary">Proceed to Payment</a>
            </div>
        </div>
    </div>
</body>
</html>