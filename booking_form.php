<?php
session_start();
if (isset($_SESSION['booking_error'])) {
    echo "<div style='background: red; color: white; padding: 10px; margin: 10px;'>ERROR: " . $_SESSION['booking_error'] . "</div>";
    unset($_SESSION['booking_error']); 
}
$userLoggedIn = isset($_SESSION["user_id"]);
$userData = null;
if ($userLoggedIn) {
    $mysqli = require __DIR__ . "/database.php";
    $stmt = $mysqli->prepare("SELECT first_name, last_name, email, contact_number FROM user WHERE id = ?");
    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    $stmt->close();
}
function getUserStatus() {
    if (isset($_SESSION["user_id"])) {
        $mysqli = require __DIR__ . "/database.php";
        $stmt = $mysqli->prepare("SELECT first_name, last_name FROM user WHERE id = ?");
        $stmt->bind_param("i", $_SESSION["user_id"]);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user ?: null;
    }
    return null;
}
$current_user = getUserStatus();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Form</title> 
    <link rel="icon" type="image/png" href="images/rlogo.png">
    <link rel="stylesheet" href="styles/booking-styles.css?v=1.1">
    <style>
        /* Payment Summary Styles */
        .payment-summary {
            background-color: #f9f9f9;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .payment-row:last-child {
            border-bottom: none;
        }

        .payment-row.total {
            font-weight: bold;
            font-size: 18px;
            border-top: 2px solid #ddd;
            margin-top: 10px;
            padding-top: 15px;
        }

        .payment-row.subtotal-row {
            font-weight: 500;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
        }

        .entrance-breakdown {
            display: grid;
            grid-template-columns: 1fr auto;
            padding: 5px 0 5px 20px;
            font-size: 0.9em;
            color: #666;
            border-bottom: 1px solid #eee;
        }

        .entrance-breakdown:last-of-type {
            margin-bottom: 10px;
        }

        .entrance-fee-note {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .private-room-free-notice {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            color: #155724;
        }

        .section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .section-title {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #4CAF50;
            display: inline-block;
        }
    </style>
    <?php if ($userLoggedIn && $userData): ?>
    <script>
        const userData = <?php echo json_encode($userData); ?>;
    </script>
    <?php else: ?>
    <script>
        const userData = null;
    </script>
    <?php endif; ?>
</head>
<body>
<header class="page-header">
        <div class="navbar">
            <div class="logo">
                <div class="logo-text">
                    <h1>Rainbow Forest Paradise Resort and Campsite</h1>
                </div>
            </div>
            <ul class="nav-links">
                <li><a href="index.php">HOME</a></li>
                <li><a href="aboutus.php">ABOUT</a></li>
                <li><a href="accommodation.php">ACCOMMODATIONS</a></li>
                <li><a href="contact.php">CONTACT US</a></li>
                <li><a href="booking_form.php">BOOK NOW</a></li>
            </ul>
            <div class="icon">
                <?php if($current_user): ?>
                    <div class="user-info">
                        <span class="user-name">Hello, <?= htmlspecialchars($current_user["first_name"]) ?></span>
                        <div class="user-actions">
                            <a href="account.php" class="profile-btn">My Profile</a>
                            <form action="logout.php" method="post">
                                <button type="submit" class="logout-btn">Logout</button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="user-icon">
                        <img src="images/logo.png" alt="User Icon">
                    </a>
                <?php endif; ?>
            </div>
        </div>
</header>
<div class="container">
    <div class="headh1" >
        <h1>Book Your Stay</h1>
    </div>
        <div class="steps">
            <div class="step active" id="step-1-indicator">
                <div class="step-number">1</div>
                <div class="step-title">Dates & Guests</div>
            </div>
            <div class="step" id="step-2-indicator">
                <div class="step-number">2</div>
                <div class="step-title">Room Selection</div>
            </div>
            <div class="step" id="step-3-indicator">
                <div class="step-number">3</div>
                <div class="step-title">Guest Details</div>
            </div>
            <div class="step" id="step-4-indicator">
                <div class="step-number">4</div>
                <div class="step-title">Payment & Confirmation</div>
            </div>
        </div>
        <form id="booking-form" method="POST" action="process_booking_p2.php" enctype="multipart/form-data">
            <!-- Step 1: Dates & Guests -->
            <div class="step-content active" id="step-1">
                <h2>Select Your Dates</h2>
                <div class="date-range">
                    <div class="form-group">
                        <label for="arrival-date">
                            Check-in Date
                            <span style="color: #dc3545; font-size: 12px; font-weight: normal;">(Cannot be today)</span>
                        </label>
                        <input type="date" id="arrival-date" name="check_in" required placeholder="Select arrival date">
                        <div id="arrival-date-error" class="date-error-message">
                            Please select a future date. Same-day reservations are not allowed.
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="departure-date">Check-out Date</label>
                        <input type="date" id="departure-date" name="check_out" required placeholder="Select departure date">
                        <div id="departure-date-error" class="date-error-message">
                           Check-out date must be on or after the arrival date
                        </div>
                    </div>
                </div>
                <div class="navigation">
                    <div></div>
                    <button type="button" class="next" id="to-step-2">Check Available Rooms</button>
                </div>
            </div>
            <!-- Step 2: Room Selection -->
            <div class="step-content" id="step-2">
                <h2>Select Your Rooms</h2>
                <p>Please select the rooms you'd like to book. You can choose multiple rooms by adjusting the quantity.</p>
                <div class="tour-type-selection">
                    <h3>Default Tour Type</h3>
                    <div class="form-group">
                        <label for="tour-type">Select Default Tour Type (for rooms without specific tour type)</label>
                        <select id="tour-type" name="default_tour_type" required>
                            <option value="day_tour">Day Tour (8AM-5PM)</option>
                            <option value="overnight_am">Overnight AM (9AM-7AM)</option>
                            <option value="overnight_pm">Overnight PM (8PM-7AM)</option>
                        </select>
                    </div>
                    <p class="help-text">Note: You can set specific tour types for individual rooms after selecting them.</p>
                </div>
                <div class="room-options" id="available-rooms">
                    <!-- Room cards will be populated here -->
                </div>
                <div class="guest-counts" style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px;">
                    <h3>Number of Guests</h3>
                    <div style="display: flex; gap: 20px;">
                        <div class="form-group">
                            <label for="adults">Number of Adults</label>
                            <input type="number" id="adults" name="adults" min="1" max="100" value="1" required>
                        </div>
                        <div class="form-group">
                            <label for="children">Number of Children</label>
                            <input type="number" id="children" name="children" min="0" max="100" value="0">
                        </div>
                        <div class="form-group">
                            <label for="pwd-senior">Number of PWD/Senior Guests</label>
                            <input type="number" id="pwd-senior" name="pwd_senior" min="0" value="0" required>
                        </div>
                    </div>
                    <div id="guest-validation-message" style="color: #ff9800; margin-top: 10px; font-size: 14px; font-style: italic;"></div>
                </div>
                <div class="selected-rooms-summary">
                    <h3>Selected Rooms</h3>
                    <div id="selected-rooms-list">
                        <p>No rooms selected yet</p>
                    </div>
                    <p><strong>Total Rooms: </strong><span id="total-rooms-count">0</span></p>
                    <p><strong>Total Capacity: </strong><span id="total-capacity">0</span> guests</p>
                </div>
                <div class="additional-options-section">
                    <h3>Additional Options</h3>
                    <div id="second-house-option" class="form-group" style="margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; display: none;">
                        <h3>Add Second House for Private</h3>
                        <div class="checkbox-group">
                            <input type="checkbox" id="add-second-house" name="add_second_house">
                            <label for="add-second-house">Add 2nd House to Private Booking (₱5,000)</label>
                        </div>
                    </div>
                </div>
                <div class="navigation">
                    <button type="button" class="prev" id="back-to-step-1">Previous</button>
                    <button type="button" class="next" id="to-step-3">Continue to Guest Details</button>
                </div>
            </div>
            <!-- Step 3: Guest Details -->
            <div class="step-content" id="step-3">
                <h2>Guest Details</h2>
                <?php if (!$userLoggedIn): ?>
                <p><a href="login.php" id="login-link">Login</a> to use your personal details</p>
                <?php endif; ?>
                <br>
                <div class="form-group">
                    <label for="first-name">First Name</label>
                    <input type="text" id="first-name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last-name">Last Name</label>
                    <input type="text" id="last-name" name="last_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="tel" id="phone" name="contact_number" required>
                </div>
                <div class="form-group">
                    <label for="special-requests">Special Requests (optional)</label>
                    <textarea id="special-requests" name="special_requests" rows="4"></textarea>
                </div>
                <div class="navigation">
                    <button type="button" class="prev" id="back-to-step-2">Previous</button>
                    <button type="button" class="next" id="to-step-4">Continue to Payment</button>
                </div>
            </div>
            <!-- Step 4: Payment & Confirmation -->
            <div class="step-content" id="step-4">
                <h2>Payment & Confirmation</h2>
                <div class="confirmation-details">
                    <div class="confirmation-item">
                        <div class="confirmation-label">Check-in:</div>
                        <div class="confirmation-value" id="summary-checkin">-</div>
                    </div>
                    <div class="confirmation-item">
                        <div class="confirmation-label">Check-out:</div>
                        <div class="confirmation-value" id="summary-checkout">-</div>
                    </div>
                    <div class="confirmation-item">
                        <div class="confirmation-label">Duration:</div>
                        <div class="confirmation-value" id="summary-nights">-</div>
                    </div>
                    <div class="confirmation-item">
                        <div class="confirmation-label">Guests:</div>
                        <div class="confirmation-value" id="summary-guests">-</div>
                    </div>
                    <div class="confirmation-item">
                        <div class="confirmation-label">Selected Rooms:</div>
                        <div class="confirmation-value" id="summary-rooms">-</div>
                    </div>
                </div>

                <!-- NEW PAYMENT SUMMARY SECTION -->
                <div class="section">
                    <h2 class="section-title">Payment Summary</h2>
                    
                    <div class="payment-summary">
                        <div class="payment-row" id="room-total-row">
                            <span>Room Total:</span>
                            <span id="summary-room-total">₱0.00</span>
                        </div>
                        
                        <div class="payment-row" id="entrance-fees-row">
                            <span>Entrance Fees:</span>
                            <span id="summary-entrance-fees">₱0.00</span>
                        </div>
                        
                        <div id="entrance-breakdown-container">
                            <!-- Entrance fee breakdown will be inserted here -->
                        </div>
                        
                        <div class="payment-row" id="extras-row" style="display: none;">
                            <span>Extra Tent/Second House:</span>
                            <span id="summary-extras">₱0.00</span>
                        </div>
                        
                        <div class="payment-row" id="additional-fee-row" style="display: none;">
                            <span>Additional Items:</span>
                            <span id="summary-additional-fee">₱0.00</span>
                        </div>
                        
                        <div class="payment-row subtotal-row">
                            <span>Subtotal (before VAT):</span>
                            <span id="summary-subtotal">₱0.00</span>
                        </div>
                        
                        <div class="payment-row">
                            <span>VAT (12% included):</span>
                            <span id="summary-vat">₱0.00</span>
                        </div>
                        
                        <div class="payment-row total">
                            <span><strong>Total Amount (VAT included):</strong></span>
                            <span><strong id="summary-total">₱0.00</strong></span>
                        </div>
                        
                        <input type="hidden" name="total_amount" id="total-amount-input">
                        <input type="hidden" name="vat_amount" id="vat-amount-input">
                    </div>
                    
                    <div class="private-room-free-notice" id="private-room-notice" style="display: none;">
                        <h3>🎉 Private Room Benefit</h3>
                        <p><strong>Entrance fees are FREE for the first 30 guests!</strong></p>
                        <p id="excess-guest-notice" style="display: none; margin-top: 10px;"></p>
                    </div>
                    
                    <div class="entrance-fee-note" id="entrance-fee-notice" style="display: none;">
                        <h3>Important Note: Bring the following to avail discount</h3>
                        <ul>
                            <li>PWD ID</li>
                            <li>PWD Notebook</li>
                            <li>Senior ID</li>
                        </ul>
                    </div>
                    
                    <div class="confirmation-item downpayment-section" style="margin-top: 20px;">
                        <div class="confirmation-label">Required Downpayment (40%):</div>
                        <div class="confirmation-value" id="summary-downpayment">₱0.00</div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 15px; border: 2px solid #4CAF50; padding: 15px; border-radius: 5px;">
                        <label for="downpayment-amount"><strong>Enter Downpayment Amount (min 40% required)</strong></label>
                        <input type="number" id="downpayment-amount" name="amount_paid" placeholder="Enter downpayment amount" min="0" step="1" required>
                        <div id="downpayment-validation" style="color: #d32f2f; margin-top: 5px; font-size: 14px;"></div>
                        <p style="margin-top: 10px; font-size: 14px;">
                            <strong>Note:</strong> A minimum downpayment of 40% of the total amount is required to secure your reservation.
                        </p>
                    </div>
                    
                    <div class="expiration-timer" id="expiration-timer" style="display: none;">
                        <p>Your reservation will expire in <span class="countdown">03:00:00</span> if payment is not completed.</p>
                        <p>Please complete your payment before the timer expires to secure your booking.</p>
                    </div>
                </div>

                <h3>Payment Method</h3>
                <div class="payment-methods">
                    <div class="payment-method selected" data-method="GCASH">
                        GCash
                    </div>
                    <div class="payment-method" data-method="RCBC">
                        RCBC
                    </div>
                </div>
                <input type="hidden" name="payment_method" id="payment-method-input" value="GCASH">
                <div id="gcash-details" class="payment-details">
                    <p><strong>GCash Details:</strong></p>
                    <p>Account Name: Ja*******e Di***e J.</p>
                    <p>GCash Number:09194880560</p>
                    <div class="form-group" style="margin-top: 15px;">
                        <label for="gcash-reference">GCash Reference Number: Please input the reference number in the transaction detail</label>
                        <input type="text" id="gcash-reference" name="gcash_reference" placeholder="Enter GCash reference number">
                    </div>
                    <div class="form-group">
                        <label for="gcash_payment_receipt">Upload Proof of Payment</label>
                        <input type="file" name="gcash_payment_receipt" id="gcash_payment_receipt" class="form-control">
                        <small class="form-text text-muted">Accepted formats: JPG or PNG</small>
                    </div>
                </div>
                <div id="bank-details" class="payment-details" style="display: none;">
                    <p><strong>RCBC Details:</strong></p>
                    <p>Account Name: Jacqueline Divine Juco</p>
                    <p>Account Number: 0000007590976487</p>
                    <p>Please enter the reference number in the transaction receipt</p>
                    <div class="form-group" style="margin-top: 15px;">
                        <label for="bank-reference">Transaction Receipt Reference Number</label>
                        <input type="text" id="bank-reference" name="bank_reference" placeholder="Enter bank reference number">
                    </div>
                    <div class="form-group">
                        <label for="bank_payment_receipt">Upload Payment Receipt</label>
                        <input type="file" name="bank_payment_receipt" id="bank_payment_receipt" class="form-control">
                        <small class="form-text text-muted">Accepted formats: JPG or PNG</small>
                    </div>
                </div>
                <div id="selected-rooms-data"></div>
                <div class="navigation">
                    <button type="button" class="prev" id="back-to-step-3">Previous</button>
                    <button type="button" class="save-for-later" id="save-for-later">
                        Save Reservation (Hold Reservation for 3 hours)
                    </button>
                    <button type="submit" class="submit" id="complete-booking">
                        Complete Reservation (Confirm now with payment)
                    </button>
                </div>
            </div>
        </form>
    </div>

<script>
    function getURLParameter(name) {
        return decodeURIComponent((new RegExp('[?|&]' + name + '=' + '([^&;]+?)(&|#|;|$)').exec(location.search) || [null, ''])[1].replace(/\+/g, '%20')) || null;
    }

    document.addEventListener('DOMContentLoaded', function() {
        // Global variables
        let selectedRooms = [];
        let totalPrice = 0;
        let selectedRoomCapacity = 0;
        let isPrivateRoomSelected = false;
        let reservationStartTime = null;
        let timerInterval = null;
        
        // Elements
        const arrivalDateInput = document.getElementById('arrival-date');
        const departureDateInput = document.getElementById('departure-date');
        const toStep2Button = document.getElementById('to-step-2');
        const toStep3Button = document.getElementById('to-step-3');
        const toStep4Button = document.getElementById('to-step-4');
        const backToStep1Button = document.getElementById('back-to-step-1');
        const backToStep2Button = document.getElementById('back-to-step-2');
        const backToStep3Button = document.getElementById('back-to-step-3');
        const stepIndicators = document.querySelectorAll('.step');
        const stepContents = document.querySelectorAll('.step-content');
        const availableRoomsContainer = document.getElementById('available-rooms');
        const selectedRoomsList = document.getElementById('selected-rooms-list');
        const totalRoomsCount = document.getElementById('total-rooms-count');
        const totalCapacityDisplay = document.getElementById('total-capacity');
        const adultsInput = document.getElementById('adults');
        const childrenInput = document.getElementById('children');
        const pwdSeniorInput = document.getElementById('pwd-senior');
        const guestValidationMessage = document.getElementById('guest-validation-message');
        const addSecondHouseOption = document.getElementById('second-house-option');
        const addSecondHouseCheckbox = document.getElementById('add-second-house');
        const completeBookingButton = document.getElementById('complete-booking');
        const saveForLaterButton = document.getElementById('save-for-later');
        const downpaymentAmountInput = document.getElementById('downpayment-amount');
        const downpaymentValidation = document.getElementById('downpayment-validation');
        const expirationTimer = document.getElementById('expiration-timer');
        const countdown = document.querySelector('.countdown');
        const tourTypeSelect = document.getElementById('tour-type');
        const paymentMethods = document.querySelectorAll('.payment-method');
        const paymentMethodInput = document.getElementById('payment-method-input');
        const gcashDetails = document.getElementById('gcash-details');
        const bankDetails = document.getElementById('bank-details');
        const summaryCheckin = document.getElementById('summary-checkin');
        const summaryCheckout = document.getElementById('summary-checkout');
        const summaryNights = document.getElementById('summary-nights');
        const summaryGuests = document.getElementById('summary-guests');
        const summaryRooms = document.getElementById('summary-rooms');
        const summaryTotal = document.getElementById('summary-total');
        const summaryDownpayment = document.getElementById('summary-downpayment');
        const totalAmountInput = document.getElementById('total-amount-input');
    
        // ADDED: Hide save button when downpayment is entered
        downpaymentAmountInput.addEventListener('input', function() {
            const downpaymentValue = parseFloat(this.value) || 0;
            if (downpaymentValue > 0) {
                saveForLaterButton.style.display = 'none';
            } else {
                saveForLaterButton.style.display = 'inline-block';
            }
            validateDownpayment();
        });

        // Check URL parameters
        const reservationCode = getURLParameter('reservation_code');
        const continuePayment = getURLParameter('continue');
        const urlAdults = getURLParameter('adults');
        const urlChildren = getURLParameter('children');
        const urlPwdSenior = getURLParameter('pwd_senior');
        const urlAmountPaid = getURLParameter('amount_paid');
        
        // Check session storage for saved data
        const savedData = sessionStorage.getItem('saved_reservation_data');
        let sessionData = null;
        if (savedData) {
            try {
                sessionData = JSON.parse(savedData);
            } catch (e) {
                console.error('Error parsing saved reservation data:', e);
            }
        }
        
        if (reservationCode && continuePayment === 'true') {
            console.log('Loading reservation with guest data from URL/session');
            
            if (urlAdults || urlChildren || urlPwdSenior || urlAmountPaid || sessionData) {
                const guestData = {
                    adults: parseInt(urlAdults) || (sessionData ? sessionData.adults : 1),
                    children: parseInt(urlChildren) || (sessionData ? sessionData.children : 0),
                    pwd_senior: parseInt(urlPwdSenior) || (sessionData ? sessionData.pwd_senior : 0),
                    amount_paid: parseFloat(urlAmountPaid) || (sessionData ? sessionData.amount_paid : 0)
                };
                
                console.log('Guest data from URL/session:', guestData);
                window.tempGuestData = guestData;
            }
            
            loadSavedReservationAndGoToStep4(reservationCode);
            return;
        }

        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        const todayString = `${yyyy}-${mm}-${dd}`;
        arrivalDateInput.min = todayString;

        function loadSavedReservationAndGoToStep4(reservationCode) {
            console.log('Loading saved reservation:', reservationCode);
            showLoadingOverlay('Loading your reservation...');
            
            fetch('get_saved_reservation.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    reservation_code: reservationCode
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Reservation data received:', data);
                removeLoadingOverlay();
                
                if (data.success && data.reservation) {
                    const reservation = data.reservation;
                    
                    if (window.tempGuestData) {
                        console.log('Merging with temporary guest data:', window.tempGuestData);
                        reservation.adults = window.tempGuestData.adults;
                        reservation.children = window.tempGuestData.children;
                        reservation.pwd_senior = window.tempGuestData.pwd_senior;
                        reservation.amount_paid = window.tempGuestData.amount_paid;
                        delete window.tempGuestData;
                    }
                    
                    const sessionData = sessionStorage.getItem('saved_reservation_data');
                    if (sessionData) {
                        try {
                            const parsedSessionData = JSON.parse(sessionData);
                            reservation.adults = parsedSessionData.adults;
                            reservation.children = parsedSessionData.children;
                            reservation.pwd_senior = parsedSessionData.pwd_senior;
                            reservation.amount_paid = parsedSessionData.amount_paid || reservation.amount_paid;
                            console.log('Applied session data including amount_paid:', reservation.amount_paid);
                            sessionStorage.removeItem('saved_reservation_data');
                        } catch (e) {
                            console.error('Error parsing session data:', e);
                        }
                    }
                    
                    populateFormWithSavedData(reservation);
                    
                    setTimeout(() => {
                        goToStep(4);
                        console.log('Navigated to step 4 with correct data');
                    }, 500);
                } else {
                    alert('Error loading reservation: ' + (data.message || 'Unknown error'));
                    window.location.href = 'saved_billing.php?code=' + reservationCode;
                }
            })
            .catch(error => {
                console.error('Error loading reservation:', error);
                removeLoadingOverlay();
                alert('An error occurred while loading your reservation: ' + error.message);
                window.location.href = 'index.php';
            });
        }

        function showLoadingOverlay(message) {
            stepContents.forEach(content => content.classList.remove('active'));
            stepIndicators.forEach(indicator => indicator.classList.remove('active'));
            
            const loadingDiv = document.createElement('div');
            loadingDiv.id = 'loading-overlay';
            loadingDiv.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(255,255,255,0.9);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                font-size: 18px;
                color: #333;
            `;
            loadingDiv.innerHTML = `<div><p>${message}</p><div style="text-align:center; margin-top:10px;">⏳</div></div>`;
            document.body.appendChild(loadingDiv);
        }

        function removeLoadingOverlay() {
            const loadingOverlay = document.getElementById('loading-overlay');
            if (loadingOverlay) {
                loadingOverlay.remove();
            }
        }
    
        function populateFormWithSavedData(reservation) {
            console.log('Populating form with data:', reservation);
            try {
                if (reservation.check_in) arrivalDateInput.value = reservation.check_in;
                if (reservation.check_out) departureDateInput.value = reservation.check_out;
                
                if (reservation.adults !== undefined) adultsInput.value = reservation.adults;
                if (reservation.adult_count !== undefined) adultsInput.value = reservation.adult_count;
                
                if (reservation.children !== undefined) childrenInput.value = reservation.children;
                if (reservation.kid_count !== undefined) childrenInput.value = reservation.kid_count;
                
                if (reservation.pwd_senior !== undefined) pwdSeniorInput.value = reservation.pwd_senior;
                if (reservation.pwd_senior_count !== undefined) pwdSeniorInput.value = reservation.pwd_senior_count;
                
                // RESTORE DOWNPAYMENT AMOUNT
                if (reservation.amount_paid !== undefined && reservation.amount_paid > 0) {
                    downpaymentAmountInput.value = reservation.amount_paid;
                    saveForLaterButton.style.display = 'none';
                    console.log('Restored downpayment amount:', reservation.amount_paid);
                }
                
                if (reservation.first_name) document.getElementById('first-name').value = reservation.first_name;
                if (reservation.last_name) document.getElementById('last-name').value = reservation.last_name;
                if (reservation.email) document.getElementById('email').value = reservation.email;
                if (reservation.contact_number) document.getElementById('phone').value = reservation.contact_number;
                if (reservation.special_requests) document.getElementById('special-requests').value = reservation.special_requests;
                
                const adults = parseInt(reservation.adults || reservation.adult_count || 0);
                const children = parseInt(reservation.children || reservation.kid_count || 0);
                const pwdSenior = parseInt(reservation.pwd_senior || reservation.pwd_senior_count || 0);
                const totalGuests = adults + children + pwdSenior;
                
                let shouldCheckSecondHouse = false;
                
                if (reservation.add_second_house && reservation.add_second_house != '0') {
                    shouldCheckSecondHouse = true;
                } else if (reservation.extras_price && parseFloat(reservation.extras_price) >= 3000) {
                    shouldCheckSecondHouse = true;
                }
                
                const hasPrivateRoom = reservation.rooms && reservation.rooms.some(r => r.room_id == 28);
                if (hasPrivateRoom && totalGuests >= 30) {
                    shouldCheckSecondHouse = true;
                }
                
                if (shouldCheckSecondHouse) {
                    addSecondHouseCheckbox.checked = true;
                    console.log('Second house checkbox checked due to stored data or guest count');
                }
                
                if (reservation.default_tour_type) {
                    tourTypeSelect.value = reservation.default_tour_type;
                }
                
                selectedRooms = [];
                if (reservation.rooms && reservation.rooms.length > 0) {
                    console.log('Processing rooms:', reservation.rooms);
                    reservation.rooms.forEach(roomData => {
                        console.log('Adding room to selection:', roomData);
                        selectedRooms.push({
                            id: parseInt(roomData.room_id),
                            name: roomData.room_name,
                            quantity: parseInt(roomData.quantity),
                            capacity: parseInt(roomData.capacity),
                            dayPrice: parseFloat(roomData.day_price),
                            nightPrice: parseFloat(roomData.night_price),
                            tourType: roomData.tour_type || reservation.default_tour_type || 'day_tour'
                        });
                    });
                }
                
                isPrivateRoomSelected = selectedRooms.some(r => r.id === 28);
                
                if (reservation.payment_method) {
                    paymentMethodInput.value = reservation.payment_method;
                    paymentMethods.forEach(method => {
                        if (method.dataset.method === reservation.payment_method) {
                            method.classList.add('selected');
                        } else {
                            method.classList.remove('selected');
                        }
                    });
                    if (reservation.payment_method === 'GCASH') {
                        gcashDetails.style.display = 'block';
                        bankDetails.style.display = 'none';
                    } else if (reservation.payment_method === 'RCBC') {
                        gcashDetails.style.display = 'none';
                        bankDetails.style.display = 'block';
                    }
                }
                
                const sameDay = reservation.check_in === reservation.check_out;
                if (isPrivateRoomSelected) {
                    addSecondHouseOption.style.display = 'block';
                    console.log('Showing second house option for private room');
                } else {
                    addSecondHouseOption.style.display = 'none';
                }
                
                updateSelectedRoomsDisplay();
                updateSummary();
                
                // ADD THIS LINE TO FIX GCASH AVAILABILITY FOR PRIVATE ROOMS
                updatePaymentMethodOptions();
                
                stepIndicators.forEach((indicator, index) => {
                    if (index < 3) {
                        indicator.classList.add('completed');
                    }
                });
                
                console.log('Form population completed successfully');
            } catch (error) {
                console.error('Error populating form:', error);
                alert('Error loading reservation data. Please try again.');
            }
        }

        function showErrorMessage(elementId, message) {
            let element = document.getElementById(elementId);
            if (!element) {
                element = document.createElement('div');
                element.id = elementId;
                element.style.cssText = `
                    color: #dc3545;
                    font-size: 14px;
                    margin-top: 8px;
                    padding: 8px 12px;
                    background-color: #f8d7da;
                    border-radius: 4px;
                    border-left: 4px solid #dc3545;
                    display: none;
                `;
                
                if (elementId === 'arrival-date-error') {
                    arrivalDateInput.parentNode.appendChild(element);
                } else if (elementId === 'departure-date-error') {
                    departureDateInput.parentNode.appendChild(element);
                }
            }
            
            element.textContent = message;
            element.style.display = 'block';
        }
        
        function hideErrorMessage(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.style.display = 'none';
            }
        }
        
        function showSuccessMessage(elementId, message) {
            let element = document.getElementById(elementId);
            if (!element) {
                element = document.createElement('div');
                element.id = elementId;
                element.style.cssText = `
                    color: #155724;
                    font-size: 14px;
                    margin-top: 8px;
                    padding: 8px 12px;
                    background-color: #d4edda;
                    border-radius: 4px;
                    border-left: 4px solid #28a745;
                    display: none;
                `;
                departureDateInput.parentNode.appendChild(element);
            }
            
            element.textContent = message;
            element.style.display = 'block';
        }
        
        function hideSuccessMessage(elementId) {
            const element = document.getElementById(elementId);
            if (element) {
                element.style.display = 'none';
            }
        }
        
        let dateValidationTimeout;
        let isUserInteracting = false;
        
        arrivalDateInput.addEventListener('focus', function() {
            isUserInteracting = true;
            this.classList.remove('invalid');
            this.style.borderColor = '';
            this.style.backgroundColor = '';
            hideErrorMessage('arrival-date-error');
        });
        
        arrivalDateInput.addEventListener('blur', function() {
            setTimeout(() => {
                isUserInteracting = false;
            }, 300);
        });
        
        arrivalDateInput.addEventListener('change', function() {
            const selectedDate = this.value;
            
            if (dateValidationTimeout) {
                clearTimeout(dateValidationTimeout);
            }
            
            if (!selectedDate) {
                return;
            }
            
            dateValidationTimeout = setTimeout(() => {
                validateArrivalDate(selectedDate);
            }, 200);
        });
        
        function validateArrivalDate(selectedDate) {
            const today = new Date();
            const todayString = today.toISOString().split('T')[0];
            
            departureDateInput.min = selectedDate;
            
            if (selectedDate <= todayString) {
                arrivalDateInput.classList.add('invalid');
                arrivalDateInput.style.borderColor = '#dc3545';
                arrivalDateInput.style.backgroundColor = '#fff5f5';
                
                showErrorMessage('arrival-date-error', 'Please select a future date. Same-day reservations are not allowed.');
                
                setTimeout(() => {
                    arrivalDateInput.value = "";
                    arrivalDateInput.style.borderColor = '';
                    arrivalDateInput.style.backgroundColor = '';
                }, 2000);
                return false;
            }
            
            arrivalDateInput.classList.remove('invalid');
            arrivalDateInput.style.borderColor = '';
            arrivalDateInput.style.backgroundColor = '';
            hideErrorMessage('arrival-date-error');
            
            if (departureDateInput.value && departureDateInput.value < selectedDate) {
                departureDateInput.value = selectedDate;
                showSuccessMessage('date-success', 'Dates updated successfully!');
            }
            
            if (selectedRooms.length > 0) {
                updateSelectedRoomsDisplay();
                updateSummary();
            }
            
            updateTourTypeOptions(selectedDate, departureDateInput.value);
            
            return true;
        }
        
        departureDateInput.addEventListener('change', function() {
            const departureDate = this.value;
            const arrivalDate = arrivalDateInput.value;
            
            if (!departureDate) {
                return;
            }
            
            if (arrivalDate && departureDate < arrivalDate) {
                this.classList.add('invalid');
                this.style.borderColor = '#dc3545';
                this.style.backgroundColor = '#fff5f5';
                
                showErrorMessage('departure-date-error', 'Departure date cannot be before arrival date.');
                
                setTimeout(() => {
                    this.value = arrivalDate;
                    this.classList.remove('invalid');
                    this.style.borderColor = '';
                    this.style.backgroundColor = '';
                    hideErrorMessage('departure-date-error');
                }, 2000);
                return;
            }
            
            this.classList.remove('invalid');
            this.style.borderColor = '';
            this.style.backgroundColor = '';
            hideErrorMessage('departure-date-error');
            
            if (arrivalDate && departureDate) {
                showSuccessMessage('date-success', 'Dates selected successfully!');
                
                setTimeout(() => {
                    hideSuccessMessage('date-success');
                }, 3000);
            }
            
            if (selectedRooms.length > 0) {
                updateSelectedRoomsDisplay();
                updateSummary();
            }
            
            updateTourTypeOptions(arrivalDate, departureDate);
        });
        
        arrivalDateInput.addEventListener('input', function() {
            if (this.classList.contains('invalid')) {
                this.classList.remove('invalid');
                this.style.borderColor = '';
                this.style.backgroundColor = '';
                hideErrorMessage('arrival-date-error');
            }
        });
        
        departureDateInput.addEventListener('input', function() {
            if (this.classList.contains('invalid')) {
                this.classList.remove('invalid');
                this.style.borderColor = '';
                this.style.backgroundColor = '';
                hideErrorMessage('departure-date-error');
            }
        });
        
        function goToStep(stepNumber) {
            if (stepNumber === 2 && !validateStep1()) return;
            if (stepNumber === 3 && !validateStep2()) return;
            if (stepNumber === 4 && !validateStep3()) return;
            
            stepContents.forEach(content => content.classList.remove('active'));
            stepIndicators.forEach(indicator => indicator.classList.remove('active'));
            
            document.getElementById(`step-${stepNumber}`).classList.add('active');
            document.getElementById(`step-${stepNumber}-indicator`).classList.add('active');
            
            if (stepNumber === 2) {
                fetchAvailableRooms();
            } else if (stepNumber === 3) {
                if (typeof userData !== 'undefined' && userData !== null) {
                    document.getElementById('first-name').value = userData.first_name || '';
                    document.getElementById('last-name').value = userData.last_name || '';
                    document.getElementById('email').value = userData.email || '';
                    document.getElementById('phone').value = userData.contact_number || '';
                }
            } else if (stepNumber === 4) {
                updateSummary();
            }
        }
        
        function goBack() {
            window.history.back();
        }
        
        function validateStep1() {
            const arrivalDate = arrivalDateInput.value;
            const departureDate = departureDateInput.value;
            
            if (!arrivalDate || !departureDate) {
                alert('Please select both arrival and departure dates.');
                return false;
            }
            
            return true;
        }
        
        function validateStep2() {
            const adults = parseInt(adultsInput.value) || 0;
            const children = parseInt(childrenInput.value) || 0;
            const pwdSenior = parseInt(pwdSeniorInput.value) || 0;
            const totalGuests = adults + children + pwdSenior;
            
            if (selectedRooms.length === 0) {
                alert('Please select at least one room.');
                return false;
            }
            
            if (!isPrivateRoomSelected && totalGuests > selectedRoomCapacity) {
                guestValidationMessage.textContent = `Reminder: The total number of guests (${totalGuests}) exceeds the capacity of selected rooms (${selectedRoomCapacity}). Please ensure you have adequate accommodations.`;
                guestValidationMessage.style.color = '#ff9800';
                guestValidationMessage.style.fontStyle = 'italic';
            } else {
                guestValidationMessage.textContent = '';
            }
            
            return true;
        }
        
        function validateStep3() {
            const firstName = document.getElementById('first-name').value.trim();
            const lastName = document.getElementById('last-name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            
            if (!firstName || !lastName || !email || !phone) {
                alert('Please fill in all required guest details.');
                return false;
            }
            
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                alert('Please enter a valid email address.');
                return false;
            }
            
            return true;
        }
        
        function fetchAvailableRooms() {
            const arrivalDate = arrivalDateInput.value;
            const departureDate = departureDateInput.value;
            
            console.log('Fetching rooms for:', arrivalDate, 'to', departureDate);
            selectedRooms = [];
            updateSelectedRoomsDisplay();
            
            availableRoomsContainer.innerHTML = '<div style="text-align: center; padding: 20px;"><p>Checking room availability...</p><div style="margin-top: 10px;">⏳</div></div>';
            
            fetch('check_availability.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    check_in: arrivalDate,
                    check_out: departureDate
                })
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Raw response:', text);
                
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                }
                
                console.log('Parsed data:', data);
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                if (data.success && data.debug) {
                    console.log('Debug info:', data.debug);
                }
            
                const availableRooms = data.rooms || [];
                
                 if (availableRooms.length === 0) {
                    availableRoomsContainer.innerHTML = `
                        <div style="text-align: center; padding: 20px; background-color: #fff3cd; border-radius: 5px;">
                            <h3>No Rooms Available</h3>
                            <p>Sorry, no rooms are available for your selected dates.</p>
                        </div>
                    `;
                    return;
                }
                
                displayAvailableRooms(availableRooms);
                const sameDay = arrivalDate === departureDate;
                addSecondHouseOption.style.display = sameDay ? 'none' : 'block';
                
                updateTourTypeOptions(arrivalDate, departureDate);
            })
             .catch(error => {
                console.error('Error:', error);
                availableRoomsContainer.innerHTML = `
                    <div style="text-align: center; padding: 20px; background-color: #f8d7da; border-radius: 5px;">
                        <h3>Error Loading Rooms</h3>
                        <p>Error: ${error.message}</p>
                        <button onclick="fetchAvailableRooms()" style="background-color: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                            Try Again
                        </button>
                    </div>
                `;
            });
        }
       
        function updateTourTypeOptions(checkIn, checkOut) {
            const tourTypeSelect = document.getElementById('tour-type');
            const sameDay = checkIn === checkOut;
            
            tourTypeSelect.innerHTML = '';
            
            if (sameDay) {
                const dayTourOption = document.createElement('option');
                dayTourOption.value = 'day_tour';
                dayTourOption.textContent = 'Day Tour (8AM-5PM)';
                tourTypeSelect.appendChild(dayTourOption);
            } else {
                const overnightAMOption = document.createElement('option');
                overnightAMOption.value = 'overnight_am';
                overnightAMOption.textContent = 'Overnight AM (9AM-7AM)';
                tourTypeSelect.appendChild(overnightAMOption);
                
                const overnightPMOption = document.createElement('option');
                overnightPMOption.value = 'overnight_pm';
                overnightPMOption.textContent = 'Overnight PM (6PM-4PM)';
                tourTypeSelect.appendChild(overnightPMOption);
            }
        }
        
        function displayAvailableRooms(rooms) {
            availableRoomsContainer.innerHTML = '';
            
            const arrivalDate = new Date(arrivalDateInput.value);
            const departureDate = new Date(departureDateInput.value);
            const sameDay = arrivalDateInput.value === departureDateInput.value;
            const nights = sameDay ? 1 : Math.round((departureDate - arrivalDate) / (1000 * 60 * 60 * 24));
            
            rooms.forEach(room => {
                const roomCard = document.createElement('div');
                roomCard.className = 'room-card';
                
                let price;
                let priceDisplay;
                
                if (room.id == 28) {
                    const basePrice = sameDay ? room.day_tour_price : room.night_tour_price;
                    price = basePrice;
                    
                    priceDisplay = sameDay 
                        ? `₱${basePrice.toLocaleString()} (varies by guest count)` 
                        : `₱${basePrice.toLocaleString()} per night (varies by guest count)`;
                } else {
                    const basePrice = sameDay ? room.day_tour_price : room.night_tour_price;
                    price = sameDay ? basePrice : basePrice * nights;
                    
                    priceDisplay = sameDay 
                        ? `₱${price.toLocaleString()}` 
                        : `₱${basePrice.toLocaleString()} × ${nights} night${nights > 1 ? 's' : ''} = ₱${price.toLocaleString()}`;
                }
                
                const availabilityDisplay = room.available_quantity < room.total_quantity 
                    ? `<span style="color: #ff6b35; font-weight: bold;">${room.available_quantity} of ${room.total_quantity || room.real_quantity} available</span>`
                    : `<span style="color: #4CAF50; font-weight: bold;">${room.available_quantity} available</span>`;
                
                roomCard.innerHTML = `
                    <div class="room-image">
                        <img src="${room.image || 'images/room-placeholder.jpg'}" alt="${room.name}">
                    </div>
                    <div class="room-details">
                        <h3>${room.name}</h3>
                        <p class="room-description">${room.description || 'No description available'}</p>
                        <p class="room-info"><strong>Capacity:</strong> ${room.capacity} guests</p>
                        <p class="room-info"><strong>Price:</strong> ${priceDisplay}</p>
                        <p class="room-info"><strong>Availability:</strong> ${availabilityDisplay}</p>
                        <div class="room-selection">
                            <label for="room-${room.id}-quantity">Quantity:</label>
                            <select id="room-${room.id}-quantity" class="room-quantity" data-room-id="${room.id}" data-room-name="${room.name}" data-room-capacity="${room.capacity}" data-day-price="${room.day_tour_price}" data-night-price="${room.night_tour_price}" data-max-available="${room.available_quantity}">
                                <option value="0">0</option>
                                ${Array.from({length: room.available_quantity}, (_, i) => i + 1).map(num => `<option value="${num}">${num}</option>`).join('')}
                            </select>
                        </div>
                    </div>
                `;
                
                if (room.id == 28) {
                    const tourTypeContainer = document.createElement('div');
                    tourTypeContainer.className = 'tour-type-container';
                    tourTypeContainer.innerHTML = `
                        <label for="room-${room.id}-tour-type">Tour Type:</label>
                        <select id="room-${room.id}-tour-type" class="room-tour-type" data-room-id="${room.id}">
                            ${sameDay ? `
                                <option value="day_tour">Day Tour (9AM-6PM)</option>
                            ` : `
                                <option value="overnight_am">Whole Day (9AM-7AM)</option>
                                <option value="overnight_pm">Overnight (6PM-4PM)</option>
                                <option value="whole_day">Whole Day (8PM-6PM)</option>
                            `}
                        </select>
                    `;
                    roomCard.querySelector('.room-selection').appendChild(tourTypeContainer);
                } else {
                    if ((room.id >= 1 && room.id <= 9) || room.id == 15 || room.id == 14) {
                        const tourTypeContainer = document.createElement('div');
                        tourTypeContainer.className = 'room-tour-type-container';
                        
                        if (sameDay) {
                            tourTypeContainer.innerHTML = `
                                <p><strong>Tour Type:</strong> Day Tour (8AM-5PM)</p>
                                <input type="hidden" id="room-${room.id}-tour-type" value="day_tour">
                            `;
                        } else {
                            if (room.id == 14) {
                                tourTypeContainer.innerHTML = `
                                    <p><strong>Tour Type:</strong> Overnight (2PM-12NN)</p>
                                    <input type="hidden" id="room-${room.id}-tour-type" value="overnight_special">
                                `;
                            } else {
                                tourTypeContainer.innerHTML = `
                                    <label for="room-${room.id}-tour-type">Tour Type:</label>
                                    <select id="room-${room.id}-tour-type" class="room-tour-type" data-room-id="${room.id}">
                                        <option value="overnight_am">Overnight (9AM-7AM)</option>
                                        <option value="overnight_pm">Overnight (6PM-4PM)</option>
                                    </select>
                                `;
                            }
                        }
                        roomCard.querySelector('.room-details').appendChild(tourTypeContainer);
                    }
                }
                
                availableRoomsContainer.appendChild(roomCard);
            });
            
            document.querySelectorAll('.room-quantity').forEach(select => {
                select.addEventListener('change', function() {
                    const roomId = parseInt(this.dataset.roomId);
                    const quantity = parseInt(this.value);
                    const roomName = this.dataset.roomName;
                    const capacity = parseInt(this.dataset.roomCapacity);
                    const dayPrice = parseInt(this.dataset.dayPrice);
                    const nightPrice = parseInt(this.dataset.nightPrice);
                    const maxAvailable = parseInt(this.dataset.maxAvailable);
                    
                    if (quantity > maxAvailable) {
                        alert(`Only ${maxAvailable} units of ${roomName} are available for your selected dates.`);
                        this.value = "0";
                        return;
                    }
                    
                    const isPrivateRoom = roomId == 28;
                    
                    if (isPrivateRoom && quantity > 0) {
                        if (selectedRooms.length > 0 && selectedRooms.some(r => r.id !== 28)) {
                            document.querySelectorAll('.room-quantity').forEach(select => {
                                if (parseInt(select.dataset.roomId) !== 28) {
                                    select.value = "0";
                                }
                            });
                            selectedRooms = selectedRooms.filter(r => r.id === 28);
                        }
                    } else if (quantity > 0 && selectedRooms.some(r => r.id === 28)) {
                        alert("The Private Room can only be booked exclusively. Please deselect the Private Room first.");
                        this.value = "0";
                        return;
                    }
                    
                    if (quantity > 0) {
                        const existingRoomIndex = selectedRooms.findIndex(r => r.id === roomId);
                        
                        if (existingRoomIndex !== -1) {
                            selectedRooms[existingRoomIndex].quantity = quantity;
                        } else {
                            let tourType = '';
                            
                            const tourTypeElement = document.getElementById(`room-${roomId}-tour-type`);
                            if (tourTypeElement) {
                                tourType = tourTypeElement.value;
                            } else {
                                tourType = document.getElementById('tour-type').value;
                            }
                            
                            selectedRooms.push({
                                id: roomId,
                                name: roomName,
                                quantity: quantity,
                                capacity: capacity,
                                dayPrice: dayPrice,
                                nightPrice: nightPrice,
                                tourType: tourType
                            });
                        }
                    } else {
                        selectedRooms = selectedRooms.filter(r => r.id !== roomId);
                    }
                    
                    updateSelectedRoomsDisplay();
                    
                    isPrivateRoomSelected = selectedRooms.some(r => r.id === 28);
                    
                    const sameDay = arrivalDateInput.value === departureDateInput.value;
                    
                    console.log('Private room selected:', isPrivateRoomSelected);
                    console.log('Same day booking:', sameDay);
                    console.log('Should show second house option:', isPrivateRoomSelected);
                    
                    // Show second house option for ALL private room bookings (day tour and overnight)
                    if (isPrivateRoomSelected) {
                        addSecondHouseOption.style.display = 'block';
                        console.log('Showing second house option');
                    } else {
                        addSecondHouseOption.style.display = 'none';
                        addSecondHouseCheckbox.checked = false;
                        console.log('Hiding second house option');
                    }
                    
                    updatePaymentMethodOptions();
                });
            });
            
            document.querySelectorAll('.room-tour-type').forEach(select => {
                select.addEventListener('change', function() {
                    const roomId = parseInt(this.dataset.roomId);
                    const tourType = this.value;
                    
                    const roomIndex = selectedRooms.findIndex(r => r.id === roomId);
                    if (roomIndex !== -1) {
                        selectedRooms[roomIndex].tourType = tourType;
                        updateSelectedRoomsDisplay();
                    }
                });
            });
        }
        
        function calculatePrivateRoomPrice(tourType, totalGuests, nights = 1) {
            let basePrice = 0;
            let additionalPerHead = 0;
            let additionalGuestCount = 0;
            let includes2ndHouse = false;
            let includesExtraTent = false;
            
            if (totalGuests >= 30) {
                includes2ndHouse = true;
                
                if (totalGuests >= 50) {
                    includesExtraTent = true;
                }
            }
            
            // DAY TOUR PRICING (9AM-6PM)
            if (tourType === 'day_tour') {
                if (totalGuests <= 10) {
                    basePrice = 8000;
                } else if (totalGuests <= 15) {
                    basePrice = 9000;
                } else if (totalGuests <= 20) {
                    basePrice = 10000;
                } else if (totalGuests <= 25) {
                    basePrice = 11000;
                } else if (totalGuests <= 30) {
                    basePrice = 12000;
                } else {
                    basePrice = 12000;
                    additionalGuestCount = totalGuests - 30;
                    additionalPerHead = 400;
                }
                
            // NIGHT TOUR PRICING (8PM-7AM) 
            } else if (tourType === 'overnight_pm') {
                if (totalGuests <= 10) {
                    basePrice = 9000;
                } else if (totalGuests <= 15) {
                    basePrice = 10000;
                } else if (totalGuests <= 20) {
                    basePrice = 11000;
                } else if (totalGuests <= 25) {
                    basePrice = 12000;
                } else if (totalGuests <= 30) {
                    basePrice = 13000;
                } else {
                    basePrice = 13000;
                    additionalGuestCount = totalGuests - 30;
                    additionalPerHead = 500;
                }
                
            // WHOLE DAY/22 HOURS PRICING (9AM-7AM or 8PM-6PM)
            } else if (tourType === 'whole_day' || tourType === 'overnight_am') {
                if (totalGuests <= 10) {
                    basePrice = 12000;
                } else if (totalGuests <= 15) {
                    basePrice = 13000;
                } else if (totalGuests <= 20) {
                    basePrice = 15000;  // FIXED: Was 15000, correct ✓
                } else if (totalGuests <= 25) {
                    basePrice = 16000;
                } else if (totalGuests <= 30) {
                    basePrice = 18000;
                } else {
                    basePrice = 18000;
                    additionalGuestCount = totalGuests - 30;
                    additionalPerHead = 600;
                }
            }
            
            let totalRoomPrice = basePrice + (additionalGuestCount * additionalPerHead);
            
            // IMPORTANT: Only multiply by nights for overnight tours (NOT day tour)
            if (tourType !== 'day_tour') {
                totalRoomPrice *= nights;
            }
            
            // Auto-check second house for 30+ guests
            if (totalGuests >= 30) {
                const addSecondHouseCheckbox = document.getElementById('add-second-house');
                if (addSecondHouseCheckbox) {
                    addSecondHouseCheckbox.checked = true;
                    addSecondHouseCheckbox.disabled = true;
                    
                    let noteContainer = document.getElementById('second-house-auto-note');
                    if (!noteContainer) {
                        noteContainer = document.createElement('div');
                        noteContainer.id = 'second-house-auto-note';
                        noteContainer.className = 'auto-inclusion-note';
                        noteContainer.style.color = '#4CAF50';
                        noteContainer.style.fontSize = '13px';
                        noteContainer.style.marginTop = '5px';
                        noteContainer.textContent = 'Second house automatically included with 30+ guests';
                        addSecondHouseCheckbox.parentNode.appendChild(noteContainer);
                    }
                }
            } else {
                const addSecondHouseCheckbox = document.getElementById('add-second-house');
                if (addSecondHouseCheckbox) {
                    addSecondHouseCheckbox.disabled = false;
                    
                    const noteContainer = document.getElementById('second-house-auto-note');
                    if (noteContainer) {
                        noteContainer.remove();
                    }
                }
            }
            
            // Auto-include extra tent for 50+ guests
            if (totalGuests >= 50) {
                let extraTentField = document.getElementById('extra-tent-auto');
                if (!extraTentField) {
                    const extraTentContainer = document.createElement('div');
                    extraTentContainer.id = 'extra-tent-container';
                    extraTentContainer.className = 'form-group';
                    extraTentContainer.style.padding = '15px';
                    extraTentContainer.style.backgroundColor = '#f8f9fa';
                    extraTentContainer.style.borderRadius = '5px';
                    extraTentContainer.style.marginTop = '15px';
                    
                    extraTentContainer.innerHTML = `
                        <h3>Extra Tent (Automatically included)</h3>
                        <p class="auto-inclusion-note" style="color: #4CAF50;">
                            Extra tent is automatically included for 50+ guests (₱800)
                        </p>
                        <input type="hidden" id="extra-tent-auto" name="extra_tent" value="1">
                    `;
                    
                    const additionalOptionsSection = document.querySelector('.additional-options-section');
                    if (additionalOptionsSection) {
                        additionalOptionsSection.appendChild(extraTentContainer);
                    }
                }
            } else {
                const extraTentContainer = document.getElementById('extra-tent-container');
                if (extraTentContainer) {
                    extraTentContainer.remove();
                }
            }
            
            return totalRoomPrice;
        }
        
        function updateSelectedRoomsDisplay() {
            if (selectedRooms.length === 0) {
                selectedRoomsList.innerHTML = '<p>No rooms selected yet</p>';
                totalRoomsCount.textContent = '0';
                totalCapacityDisplay.textContent = '0';
                return;
            }
            
            let totalRooms = 0;
            selectedRoomCapacity = 0;
            totalPrice = 0;
            
            const arrivalDate = new Date(arrivalDateInput.value);
            const departureDate = new Date(departureDateInput.value);
            const sameDay = arrivalDateInput.value === departureDateInput.value;
            const nights = sameDay ? 1 : Math.round((departureDate - arrivalDate) / (1000 * 60 * 60 * 24));
            
            let html = '<ul>';
            
            selectedRooms.forEach(room => {
                totalRooms += room.quantity;
                selectedRoomCapacity += room.capacity * room.quantity;
                
                let roomPrice = 0;
                
                if (room.id === 28) {
                    const adults = parseInt(adultsInput.value) || 0;
                    const children = parseInt(childrenInput.value) || 0;
                    const pwdSenior = parseInt(pwdSeniorInput.value) || 0;
                    const totalGuests = adults + children + pwdSenior;
                    
                    roomPrice = calculatePrivateRoomPrice(room.tourType, totalGuests, nights);
                } else {
                    const basePrice = sameDay ? room.dayPrice : room.nightPrice;
                    roomPrice = basePrice * (sameDay ? 1 : nights);
                }
                
                const subtotal = roomPrice * room.quantity;
                totalPrice += subtotal;
                
                html += `
                    <li>
                        <div class="selected-room">
                            <span class="room-name">${room.name}</span>
                            <span class="room-details">
                                Qty: ${room.quantity} × ₱${roomPrice.toLocaleString()} 
                                ${!sameDay ? `(${nights} night${nights > 1 ? 's' : ''})` : ''}
                                = ₱${subtotal.toLocaleString()}
                            </span>
                        </div>
                    </li>
                `;
            });
            
            html += '</ul>';
            
            selectedRoomsList.innerHTML = html;
            totalRoomsCount.textContent = totalRooms;
            totalCapacityDisplay.textContent = selectedRoomCapacity;
            
            calculateTotalPrice();
        }
        
        function calculateEntranceFees() {
            if (isPrivateRoomSelected) {
                return 0;
            }
            
            const adultCount = parseInt(adultsInput.value) || 0;
            const childCount = parseInt(childrenInput.value) || 0;
            const pwdSeniorCount = parseInt(pwdSeniorInput.value) || 0;
            
            const sameDay = arrivalDateInput.value === departureDateInput.value;
            
            // ADD THIS: Calculate number of nights
            const arrivalDate = new Date(arrivalDateInput.value);
            const departureDate = new Date(departureDateInput.value);
            const nights = sameDay ? 1 : Math.round((departureDate - arrivalDate) / (1000 * 60 * 60 * 24));
            
            const isDayTour = sameDay || selectedRooms.some(r => r.tourType === 'day_tour');
            
            let adultFee, childFee;
            
            if (isDayTour) {
                adultFee = 200;
                childFee = 150;
            } else {
                adultFee = 250;
                childFee = 200;
            }
            
            const pwdSeniorDiscount = 0.2;
            
            const totalAdultFee = adultCount * adultFee;
            const totalChildFee = childCount * childFee;
            const totalPwdSeniorFee = pwdSeniorCount * adultFee * (1 - pwdSeniorDiscount);
            
            // MULTIPLY BY NIGHTS HERE
            const totalEntranceFee = (totalAdultFee + totalChildFee + totalPwdSeniorFee) * nights;
            
            return totalEntranceFee;
        }
        
        function calculateTotalPrice() {
            const arrivalDate = new Date(arrivalDateInput.value);
            const departureDate = new Date(departureDateInput.value);
            const sameDay = arrivalDateInput.value === departureDateInput.value;
            const nights = sameDay ? 1 : Math.round((departureDate - arrivalDate) / (1000 * 60 * 60 * 24));
        
            // Calculate room total
            let roomsPrice = totalPrice;
            
            // Calculate entrance fees
            const adults = parseInt(adultsInput.value) || 0;
            const children = parseInt(childrenInput.value) || 0;
            const pwdSenior = parseInt(pwdSeniorInput.value) || 0;
            
            const displayIsDayTour = sameDay || selectedRooms.some(r => r.tourType === 'day_tour');
            
            let entranceFees = 0;
            let adultFeeRate = displayIsDayTour ? 200 : 250;
            let childFeeRate = displayIsDayTour ? 150 : 200;
            let pwdSeniorFeeRate = displayIsDayTour ? 160 : 200;
            
            if (!isPrivateRoomSelected) {
                // MULTIPLY BY NIGHTS HERE - FIX THE CALCULATION
                entranceFees += (adults * adultFeeRate) * nights;
                entranceFees += (children * childFeeRate) * nights;
                entranceFees += (pwdSenior * pwdSeniorFeeRate) * nights;
            }
            
            // Calculate extras (second house)
            let extrasPrice = 0;
            const privateRoom = selectedRooms.find(r => r.id === 28);
            const totalGuests = adults + children + pwdSenior;
            const has30PlusGuests = privateRoom && totalGuests >= 30;
            
            if (addSecondHouseCheckbox.checked && !has30PlusGuests) {
                extrasPrice = sameDay ? 5000 : 5000 * nights;
            }
            
            // Calculate total with VAT
            let totalWithVAT = roomsPrice + entranceFees + extrasPrice;
            
            // Calculate VAT (12% included in total)
            const vatAmount = Math.round(totalWithVAT * (12/112));
            const subtotalBeforeVAT = totalWithVAT - vatAmount;
            
            // Update display - Room Total
            document.getElementById('summary-room-total').textContent = `₱${roomsPrice.toLocaleString()}`;
            
            // Show/hide entrance fees section
            const entranceFeesRow = document.getElementById('entrance-fees-row');
            const entranceBreakdownContainer = document.getElementById('entrance-breakdown-container');
            const entranceFeeNotice = document.getElementById('entrance-fee-notice');
            
            if (!isPrivateRoomSelected && entranceFees > 0) {
                entranceFeesRow.style.display = 'flex';
                document.getElementById('summary-entrance-fees').textContent = `₱${entranceFees.toLocaleString()}`;
                
                // Build entrance breakdown
                let breakdownHTML = '';
                if (adults > 0) {
                    breakdownHTML += `
                        <div class="entrance-breakdown">
                            <div>• Adults (${adults} × ₱${adultFeeRate} × ${nights} night${nights > 1 ? 's' : ''}):</div>
                            <div>₱${(adults * adultFeeRate * nights).toLocaleString()}</div>
                        </div>
                    `;
                }
                if (children > 0) {
                    breakdownHTML += `
                        <div class="entrance-breakdown">
                            <div>• Children (${children} × ₱${childFeeRate} × ${nights} night${nights > 1 ? 's' : ''}):</div>
                            <div>₱${(children * childFeeRate * nights).toLocaleString()}</div>
                        </div>
                    `;
                }
                if (pwdSenior > 0) {
                    breakdownHTML += `
                        <div class="entrance-breakdown">
                            <div>• PWD/Senior (${pwdSenior} × ₱${pwdSeniorFeeRate} × ${nights} night${nights > 1 ? 's' : ''}):</div>
                            <div>₱${(pwdSenior * pwdSeniorFeeRate * nights).toLocaleString()}</div>
                        </div>
                    `;
                }
                entranceBreakdownContainer.innerHTML = breakdownHTML;
                entranceFeeNotice.style.display = 'block';
            } else {
                entranceFeesRow.style.display = 'none';
                entranceBreakdownContainer.innerHTML = '';
                entranceFeeNotice.style.display = 'none';
            }
            
            // Show/hide extras row
            const extrasRow = document.getElementById('extras-row');
            if (extrasPrice > 0) {
                extrasRow.style.display = 'flex';
                document.getElementById('summary-extras').textContent = `₱${extrasPrice.toLocaleString()}`;
            } else {
                extrasRow.style.display = 'none';
            }
            
            document.getElementById('summary-subtotal').textContent = `₱${subtotalBeforeVAT.toLocaleString()}`;
            document.getElementById('summary-vat').textContent = `₱${vatAmount.toLocaleString()}`;
            document.getElementById('summary-total').textContent = `₱${totalWithVAT.toLocaleString()}`;
            
            totalAmountInput.value = totalWithVAT;
            document.getElementById('vat-amount-input').value = vatAmount;
            
            const minimumDownpayment = Math.ceil(totalWithVAT * 0.4);
            document.getElementById('summary-downpayment').textContent = `₱${minimumDownpayment.toLocaleString()}`;
            downpaymentAmountInput.min = minimumDownpayment;
            
            return totalWithVAT;
        }
        
        function updateSummary() {
            const arrivalDate = new Date(arrivalDateInput.value);
            const departureDate = new Date(departureDateInput.value);
            
            summaryCheckin.textContent = arrivalDate.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            summaryCheckout.textContent = departureDate.toLocaleDateString('en-US', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
            
            if (arrivalDateInput.value === departureDateInput.value) {
                summaryNights.textContent = 'Day Visit';
            } else {
                const nights = Math.round((departureDate - arrivalDate) / (1000 * 60 * 60 * 24));
                
                summaryNights.innerHTML = '';
                
                const nightsText = document.createTextNode(`${nights} night${nights > 1 ? 's' : ''}`);
                summaryNights.appendChild(nightsText);
                
                if (nights > 1) {
                    const noteElem = document.createElement('div');
                    noteElem.className = 'nights-info';
                    noteElem.style.fontSize = '13px';
                    noteElem.style.fontStyle = 'italic';
                    noteElem.style.marginTop = '3px';
                    noteElem.style.color = '#666';
                    noteElem.textContent = `(Prices shown include all ${nights} nights)`;
                    summaryNights.appendChild(noteElem);
                }
            }
            
            const adults = parseInt(adultsInput.value) || 0;
            const children = parseInt(childrenInput.value) || 0;
            const pwdSenior = parseInt(pwdSeniorInput.value) || 0;
            const totalGuests = adults + children + pwdSenior;
            
            const guestText = `${adults} adult${adults > 1 ? 's' : ''}, ${children} child${children > 1 ? 'ren' : ''}, ${pwdSenior} PWD/Senior guest${pwdSenior !== 1 ? 's' : ''} (Total: ${totalGuests})`;
            
            if (isPrivateRoomSelected) {
                summaryGuests.textContent = `${guestText} (Entrance fees included in private room)`;
            } else {
                const entranceFees = calculateEntranceFees();
                const guestTextWithFees = `${guestText}`;
                summaryGuests.textContent = guestTextWithFees;
            }
            
            if (selectedRooms.length > 0) {
                let roomsText = '';
                selectedRooms.forEach((room, index) => {
                    roomsText += `${room.quantity} × ${room.name}`;
                    if (index < selectedRooms.length - 1) {
                        roomsText += ', ';
                    }
                });
                summaryRooms.textContent = roomsText;
            } else {
                summaryRooms.textContent = 'No rooms selected';
            }
            
            const total = calculateTotalPrice();
            
            updateSelectedRoomsData();
        }
        
        pwdSeniorInput.addEventListener('change', function() {
            validateGuestCount();
            
            if (selectedRooms.some(r => r.id === 28)) {
                updateSelectedRoomsDisplay();
            }
            
            updateSummary();
        });
        
        function updateSelectedRoomsData() {
            const container = document.getElementById('selected-rooms-data');
            container.innerHTML = '';
            
            const arrivalDate = new Date(arrivalDateInput.value);
            const departureDate = new Date(departureDateInput.value);
            const nights = Math.round((departureDate - arrivalDate) / (1000 * 60 * 60 * 24));
            
            const nightsInput = document.createElement('input');
            nightsInput.type = 'hidden';
            nightsInput.name = 'nights';
            nightsInput.value = nights;
            container.appendChild(nightsInput);
            
            selectedRooms.forEach((room, index) => {
                const roomIdInput = document.createElement('input');
                roomIdInput.type = 'hidden';
                roomIdInput.name = `room_id[${index}]`;
                roomIdInput.value = room.id;
                container.appendChild(roomIdInput);
                
                const roomQuantityInput = document.createElement('input');
                roomQuantityInput.type = 'hidden';
                roomQuantityInput.name = `room_quantity[${index}]`;
                roomQuantityInput.value = room.quantity;
                container.appendChild(roomQuantityInput);
                
                const roomTourTypeInput = document.createElement('input');
                roomTourTypeInput.type = 'hidden';
                roomTourTypeInput.name = `room_tour_type[${index}]`;
                roomTourTypeInput.value = room.tourType;
                container.appendChild(roomTourTypeInput);
            });
        }
        
        function updatePaymentMethodOptions() {
            if (isPrivateRoomSelected) {
                paymentMethodInput.value = 'RCBC';
                paymentMethods.forEach(method => {
                    if (method.dataset.method === 'RCBC') {
                        method.classList.add('selected');
                    } else {
                        method.classList.remove('selected');
                        method.style.opacity = '0.4';
                        method.style.pointerEvents = 'none';
                    }
                });
                gcashDetails.style.display = 'none';
                bankDetails.style.display = 'block';
            } else {
                paymentMethods.forEach(method => {
                    method.style.opacity = '1';
                    method.style.pointerEvents = 'auto';
                });
            }
        }
        
        function validateDownpayment() {
            const total = parseFloat(totalAmountInput.value);
            const minDownpayment = Math.ceil(total * 0.4);
            const downpayment = parseFloat(downpaymentAmountInput.value) || 0;
            
            if (downpayment < minDownpayment) {
                downpaymentValidation.textContent = `Minimum required downpayment is ₱${minDownpayment.toLocaleString()} (40% of the total).`;
                return false;
            }
            
            if (downpayment > total) {
                downpaymentValidation.textContent = `Downpayment cannot be greater than the total amount (₱${total.toLocaleString()}).`;
                return false;
            }
            
            downpaymentValidation.textContent = '';
            return true;
        }
        
        paymentMethods.forEach(method => {
            method.addEventListener('click', function() {
                if (this.style.pointerEvents === 'none') return;
                
                paymentMethods.forEach(m => m.classList.remove('selected'));
                
                this.classList.add('selected');
                
                paymentMethodInput.value = this.dataset.method;
                
                if (this.dataset.method === 'GCASH') {
                    gcashDetails.style.display = 'block';
                    bankDetails.style.display = 'none';
                } else if (this.dataset.method === 'RCBC') {
                    gcashDetails.style.display = 'none';
                    bankDetails.style.display = 'block';
                }
            });
        });
        
        addSecondHouseCheckbox.addEventListener('change', function() {
            calculateTotalPrice();
            updateSummary();
        });
        
        adultsInput.addEventListener('change', function() {
            validateGuestCount();
            
            if (selectedRooms.some(r => r.id === 28)) {
                updateSelectedRoomsDisplay();
            }
            
            updateSummary();
        });
        
        childrenInput.addEventListener('change', function() {
            validateGuestCount();
            
            if (selectedRooms.some(r => r.id === 28)) {
                updateSelectedRoomsDisplay();
            }
            
            updateSummary();
        });
        
        function validateGuestCount() {
            const adults = parseInt(adultsInput.value) || 0;
            const children = parseInt(childrenInput.value) || 0;
            const pwdSenior = parseInt(pwdSeniorInput.value) || 0;
            const totalGuests = adults + children + pwdSenior;
            
            if (!isPrivateRoomSelected && totalGuests > selectedRoomCapacity) {
                guestValidationMessage.textContent = `Reminder: The total number of guests (${totalGuests}) exceeds the capacity of selected rooms (${selectedRoomCapacity}). Please ensure you have adequate accommodations.`;
                guestValidationMessage.style.color = '#ff9800';
                guestValidationMessage.style.fontStyle = 'italic';
            } else {
                guestValidationMessage.textContent = '';
            }
        }
        
        downpaymentAmountInput.addEventListener('input', validateDownpayment);
        
        // Save for later button with downpayment
        saveForLaterButton.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Save for later button clicked');
            
            if (selectedRooms.length === 0) {
                alert('Please select at least one room.');
                return;
            }
            
            const formData = new FormData(document.getElementById('booking-form'));
            formData.append('action', 'save_for_later');
            
            // Include downpayment amount
            const downpaymentValue = downpaymentAmountInput.value;
            if (downpaymentValue) {
                formData.set('amount_paid', downpaymentValue);
                console.log('Including downpayment amount:', downpaymentValue);
            }
            
            // Store guest data for continuation
            const guestData = {
                adults: adultsInput.value,
                children: childrenInput.value,
                pwd_senior: pwdSeniorInput.value,
                amount_paid: downpaymentValue
            };
            sessionStorage.setItem('saved_reservation_data', JSON.stringify(guestData));
            
            console.log('Form data being sent:');
            for (let [key, value] of formData.entries()) {
                console.log(`${key}: ${value}`);
            }
            
            console.log('Sending AJAX request...');
            
            saveForLaterButton.disabled = true;
            saveForLaterButton.textContent = 'Saving...';
            
            fetch('process_booking_p2.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                return response.text();
            })
            .then(text => {
                console.log('Raw response text:', text);
                
                // Clean up any non-JSON content before the actual JSON
                let cleanedText = text.trim();
                const jsonStart = cleanedText.indexOf('{');
                const jsonEnd = cleanedText.lastIndexOf('}');
                
                if (jsonStart !== -1 && jsonEnd !== -1) {
                    cleanedText = cleanedText.substring(jsonStart, jsonEnd + 1);
                    console.log('Cleaned JSON text:', cleanedText);
                }
                
                let data;
                try {
                    data = JSON.parse(cleanedText);
                    console.log('Parsed JSON data:', data);
                } catch (e) {
                    console.error('JSON parse error:', e);
                    console.error('Failed to parse:', cleanedText);
                    alert('Server error. Your reservation may have been saved. Please check your email. Error: ' + e.message);
                    saveForLaterButton.disabled = false;
                    saveForLaterButton.textContent = 'Save Reservation';
                    return;
                }
                
                if (data.success) {
                    console.log('Success! Reservation code:', data.reservation_code);
                    
                    // Store the guest data for the next page
                    const continuationData = {
                        adults: adultsInput.value,
                        children: childrenInput.value,
                        pwd_senior: pwdSeniorInput.value,
                        amount_paid: downpaymentValue || 0
                    };
                    sessionStorage.setItem('saved_reservation_data', JSON.stringify(continuationData));
                    
                    alert('Your reservation has been saved successfully! Redirecting to billing page...');
                    
                    // Build the redirect URL
                    const redirectUrl = 'saved_billing.php?code=' + encodeURIComponent(data.reservation_code);
                    console.log('Redirecting to:', redirectUrl);
                    
                    // Force redirect after a short delay
                    setTimeout(function() {
                        window.location.href = redirectUrl;
                    }, 500);
                } else {
                    alert('Error: ' + (data.message || 'Unknown error occurred'));
                    saveForLaterButton.disabled = false;
                    saveForLaterButton.textContent = 'Save Reservation';
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('An error occurred while saving your reservation. Please check your email for confirmation. Error: ' + error.message);
                saveForLaterButton.disabled = false;
                saveForLaterButton.textContent = 'Save Reservation';
            });
        });
        
        document.getElementById('booking-form').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!validatePaymentStep()) {
                return;
            }
            this.submit();
        });
        
        function validatePaymentStep() {
            if (!validateDownpayment()) {
                alert('Please enter the minimum required downpayment amount.');
                return false;
            }
            const paymentMethod = paymentMethodInput.value;
            if (paymentMethod === 'GCASH') {
                const gcashReference = document.getElementById('gcash-reference').value.trim();
                if (!gcashReference) {
                    alert('Please enter your GCash reference number.');
                    return false;
                }
            } else if (paymentMethod === 'RCBC') {
                const bankReference = document.getElementById('bank-reference').value.trim();
                if (!bankReference) {
                    alert('Please enter your bank transaction reference number.');
                    return false;
                }
            }
            return true;
        }
        
        toStep2Button.addEventListener('click', function() {
            goToStep(2);
        });
        
        toStep3Button.addEventListener('click', function() {
            goToStep(3);
        });
        
        toStep4Button.addEventListener('click', function() {
            goToStep(4);
        });
        
        backToStep1Button.addEventListener('click', function() {
            goToStep(1);
        });
        
        backToStep2Button.addEventListener('click', function() {
            goToStep(2);
        });
        
        backToStep3Button.addEventListener('click', function() {
            goToStep(3);
        });
        
        const loginLink = document.getElementById('login-link');
        if (loginLink) {
            loginLink.addEventListener('click', function(e) {
                e.preventDefault();
                saveBookingDataToSession();
                window.location.href = 'login.php?return_url=' + encodeURIComponent('booking_p2.php?step=3');
            });
        }
        
        function saveBookingDataToSession() {
            const arrivalDate = new Date(arrivalDateInput.value);
            const departureDate = new Date(departureDateInput.value);
            const nights = Math.round((departureDate - arrivalDate) / (1000 * 60 * 60 * 24));
            
            const bookingData = {
                check_in: arrivalDateInput.value,
                check_out: departureDateInput.value,
                nights: nights,
                selected_rooms: selectedRooms,
                adults: adultsInput.value,
                children: childrenInput.value,
                pwd_senior: pwdSeniorInput.value,
                add_second_house: addSecondHouseCheckbox.checked,
                default_tour_type: tourTypeSelect.value,
                amount_paid: downpaymentAmountInput.value || ''
            };
            
            sessionStorage.setItem('booking_data', JSON.stringify(bookingData));
            
            fetch('save_booking_session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(bookingData)
            });
        }
        
        function loadBookingDataFromSession() {
            const bookingData = sessionStorage.getItem('booking_data');
            if (bookingData) {
                try {
                    const data = JSON.parse(bookingData);
                    arrivalDateInput.value = data.check_in;
                    departureDateInput.value = data.check_out;
                    adultsInput.value = data.adults;
                    childrenInput.value = data.children;
                    pwdSeniorInput.value = data.pwd_senior;
                    addSecondHouseCheckbox.checked = data.add_second_house;
                    tourTypeSelect.value = data.default_tour_type;
                    if (data.amount_paid) {
                        downpaymentAmountInput.value = data.amount_paid;
                    }
                    
                    fetchAvailableRooms().then(() => {
                        setTimeout(() => {
                            data.selected_rooms.forEach(savedRoom => {
                                const roomQuantitySelect = document.querySelector(`#room-${savedRoom.id}-quantity`);
                                if (roomQuantitySelect) {
                                    roomQuantitySelect.value = savedRoom.quantity;
                                    
                                    const event = new Event('change');
                                    roomQuantitySelect.dispatchEvent(event);
                                    
                                    if (savedRoom.id === 28) {
                                        const tourTypeSelect = document.querySelector(`#room-${savedRoom.id}-tour-type`);
                                        if (tourTypeSelect) {
                                            tourTypeSelect.value = savedRoom.tourType;
                                        }
                                    }
                                }
                            });
                            
                            sessionStorage.removeItem('booking_data');
                        }, 500);
                    });
                } catch (e) {
                    console.error('Error loading booking data from session:', e);
                }
            }
        }
        
        const step = getURLParameter('step');
        if (step) {
            goToStep(parseInt(step));
            loadBookingDataFromSession();
        }
        
        if (window.location.href.includes('return_from_login=true')) {
            fetch('get_booking_session.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.booking_data) {
                        sessionStorage.setItem('booking_data', JSON.stringify(data.booking_data));
                        loadBookingDataFromSession();
                    }
                });
        }
    });
</script>
</body>
</html>