<?php
session_start(); // Start the session at the beginning

// Store reservation data in session if provided in URL, regardless of login status
if (isset($_GET['check_in'])) $_SESSION['check_in'] = $_GET['check_in'];
if (isset($_GET['check_out'])) $_SESSION['check_out'] = $_GET['check_out'];
if (isset($_GET['tour_type'])) $_SESSION['tour_type'] = $_GET['tour_type'];
if (isset($_GET['time'])) $_SESSION['time'] = $_GET['time'];

// Initialize user data array
$user = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'contact_number' => ''
];

// Check if user is logged in and fetch their data
$isLoggedIn = isset($_SESSION["user_id"]);
if ($isLoggedIn) {
    $mysqli = require 'database.php';
    $stmt = $mysqli->prepare("SELECT first_name, last_name, email, contact_number FROM user WHERE id = ?");
    if ($stmt === false) {
        die("Error preparing the query: " . $mysqli->error);
    }

    $stmt->bind_param("i", $_SESSION["user_id"]);
    $stmt->execute();
    $result = $stmt->get_result();
    $userData = $result->fetch_assoc();
    
    if ($userData) {
        $user = $userData; // Override empty user with database values
    }
    
    $stmt->close();
    $mysqli->close();
}

// Retrieve reservation parameters from GET or SESSION
$check_in = isset($_GET['check_in']) ? $_GET['check_in'] : ($_SESSION['check_in'] ?? '');
$check_out = isset($_GET['check_out']) ? $_GET['check_out'] : ($_SESSION['check_out'] ?? '');
$tour_type = isset($_GET['tour_type']) ? $_GET['tour_type'] : ($_SESSION['tour_type'] ?? '');
$time = isset($_GET['time']) ? $_GET['time'] : ($_SESSION['time'] ?? '');

// Store values in session for persistence
$_SESSION['check_in'] = $check_in;
$_SESSION['check_out'] = $check_out;
$_SESSION['tour_type'] = $tour_type;
$_SESSION['time'] = $time;

// Convert time from display format to 24-hour format if necessary
if (!empty($time)) {
    // Check if time contains text with AM/PM format
    if (strpos($time, 'AM') !== false || strpos($time, 'PM') !== false) {
        // Extract the hour from time strings like "9:00 AM - 7:00 AM (next day)"
        if (strpos($time, '9:00 AM') !== false) {
            $time = '09:00:00';
        } elseif (strpos($time, '8:00 PM') !== false) {
            $time = '20:00:00';
        }
    }
}

// Flag to determine if values are available from homepage or session
$valuesPreSelected = !empty($check_in) && !empty($check_out) && !empty($tour_type);

// Ensure time value is set based on tour type if not explicitly provided
if (empty($time) && !empty($tour_type)) {
    if ($tour_type == 'day_tour' || ($tour_type == 'whole_day' && empty($time))) {
        $time = '09:00:00';
    } elseif ($tour_type == 'night_tour') {
        $time = '20:00:00';
    } elseif ($tour_type == 'whole_day_morning') {
        $time = '09:00:00';
    } elseif ($tour_type == 'whole_day_night') {
        $time = '20:00:00';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Form</title>
    <link rel="stylesheet" href="styles/reservation.css?v=1.0">
</head>
<body>
<div class="container">
    <div class="page-header">
        <h1>Comprehensive Guided Tour Reservation Form and Booking Details</h1>
        <div class="subtitle">
        Complete your tour reservation by providing accurate personal and group information, 
        selecting your preferred tour type.
        </div>
    </div>
    <div class="content-wrapper">
        <?php if ($valuesPreSelected): ?>
        <div class="preselected-info">
            <p><strong>Your tour preferences have been saved.</strong> The dates, tour type, and time you selected on the homepage are locked in for this reservation.</p>
        </div>
        <?php endif; ?>
        <div class="form-container">
            <?php if (!isset($_SESSION["user_id"])): ?>
            <div class="login-banner">
                <p>Not logged in? You can still make a reservation as a guest.</p>
                <p>Already have an account? <a href="login.php?check_in=<?php echo urlencode($_SESSION['check_in'] ?? ''); ?>&check_out=<?php echo urlencode($_SESSION['check_out'] ?? ''); ?><?php echo $tour_type ? '&tour_type='.urlencode($tour_type) : ''; ?><?php echo $time ? '&time='.urlencode($time) : ''; ?>">Login</a> to auto-fill your information.</p>
            </div>
            <?php endif; ?>
            
            <form action="reservation_process.php" method="POST" class="reservation-form" id="reservation-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Check-in Date:</label>
                        <input type="date" name="check_in_display" id="check_in" required value="<?php echo htmlspecialchars($check_in); ?>" disabled>
                        <div class="date-locked-message">This date is locked for your reservation.</div>
                        <!-- Hidden field to ensure value is submitted -->
                        <input type="hidden" name="check_in" value="<?php echo htmlspecialchars($check_in); ?>">
                    </div>
                    <div class="form-group">
                        <label>Check-out Date:</label>
                        <input type="date" name="check_out_display" id="check_out" required value="<?php echo htmlspecialchars($check_out); ?>" disabled>
                        <div class="date-locked-message">This date is locked for your reservation.</div>
                        <!-- Hidden field to ensure value is submitted -->
                        <input type="hidden" name="check_out" value="<?php echo htmlspecialchars($check_out); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name:</label>
                        <input type="text" name="first_name" id="first_name" value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>" required>
                        <div id="first_name-error" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label>Last Name:</label>
                        <input type="text" name="last_name" id="last_name" value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>" required>
                        <div id="last_name-error" class="error-message"></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email:</label>
                        <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                        <div id="email-error" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label>Phone Number:</label>
                        <input type="text" name="contact_number" id="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" required>
                        <div id="contact_number-error" class="error-message"></div>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Number of Guests(Adults):</label>
                        <input type="number" name="adult_count" id="adult_count" min="1" value="1" required>
                        <div id="adult_count-error" class="error-message"></div>
                        <div class="guest-limit-notice">
                            <small style='color:#03624c;'>Maximum is 100 total guests (adults + children) allowed per reservation. Groups with more than 50 guests require an extra tent.</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Number of Guests(Kids):</label>
                        <input type="number" name="kid_count" id="kid_count" min="0" value="0">
                        <div id="kid_count-error" class="error-message"></div>
                    </div>
                </div>
                
                <!-- Extra tent section -->
                <div class="form-row" id="extra-tent-container" style="display: none;">
                    <div class="form-group">
                        <label for="extra_tent">Extra Tent Required:</label>
                        <div class="tent-info">
                            <input type="checkbox" name="extra_tent" id="extra_tent" value="1">
                            <span id="tent-required-note">Required for groups over 50 people (₱800 additional fee)</span>
                        </div>
                        <div id="extra_tent-error" class="error-message"></div>
                    </div>
                </div>

                <!-- Corkage fee section -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="corkage_fee">Corkage Fee:</label>
                        <div class="fee-info">
                            <input type="checkbox" name="corkage_fee" id="corkage_fee" value="1">
                            <span>Add corkage fee (₱100 per bottle)</span>
                            <div class="quantity-selector" id="corkage-quantity-container" style="display: none;">
                                <label for="corkage_quantity">Number of bottles:</label>
                                <input type="number" name="corkage_quantity" id="corkage_quantity" min="1" value="1">
                            </div>
                        </div>
                        <div id="corkage_fee-error" class="error-message"></div>
                    </div>
                </div>
                
                <!-- Pet fee section -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="pet_fee">Pet Fee:</label>
                        <div class="fee-info">
                            <input type="checkbox" name="pet_fee" id="pet_fee" value="1">
                            <span>Bringing pets (₱200 per pet, medium-sized only, 15kg or below)</span>
                            <div class="quantity-selector" id="pet-quantity-container" style="display: none;">
                                <label for="pet_quantity">Number of pets:</label>
                                <input type="number" name="pet_quantity" id="pet_quantity" min="1" value="1">
                            </div>
                        </div>
                        <div id="pet_fee-error" class="error-message"></div>
                        <div class="pet-policy">
                            <small style="color:#d35400;">Note: Only medium-sized pets (15kg or below) are allowed in the resort.</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tour Type:</label>
                        <input type="text" name="tour_type_display" id="tour_type_display" value="<?php echo ucwords(str_replace('_', ' ', $tour_type)); ?>" disabled>
                        <div class="date-locked-message">Tour type is locked for your reservation.</div>
                        <!-- Hidden field to ensure value is submitted -->
                        <input type="hidden" name="tour_type" value="<?php echo htmlspecialchars($tour_type); ?>">
                    </div>
                </div>
                
                <!-- Time selection display -->
                <div class="form-row" id="time-selection-container">
                    <div class="form-group">
                        <label>Preferred Time:</label>
                        <input type="text" name="time_display" id="time_display" value="<?php 
                            if ($time === '09:00:00' && ($tour_type === 'whole_day' || $tour_type === 'whole_day_morning')) {
                                echo '9:00 AM to 7:00 AM (next day)';
                            } elseif ($time === '20:00:00' && ($tour_type === 'whole_day' || $tour_type === 'whole_day_night')) {
                                echo '8:00 PM to 6:00 PM (next day)';
                            } elseif ($time === '09:00:00' && $tour_type === 'day_tour') {
                                echo '9:00 AM to 6:00 PM';
                            } elseif ($time === '20:00:00' && $tour_type === 'night_tour') {
                                echo '8:00 PM to 7:00 AM (next day)';
                            }
                        ?>" disabled>
                        <div class="date-locked-message">Time is locked for your reservation.</div>
                        <!-- Hidden field to ensure value is submitted -->
                        <input type="hidden" name="time" value="<?php echo htmlspecialchars($time); ?>">
                    </div>
                </div>
                
                <!-- Hidden input to store original tour_type and time -->
                <input type="hidden" id="original_tour_type" value="<?php echo htmlspecialchars($tour_type); ?>">
                <input type="hidden" id="original_time" value="<?php echo htmlspecialchars($time); ?>">
                <input type="hidden" id="values_preselected" value="<?php echo $valuesPreSelected ? 'true' : 'false'; ?>">
                
                <div class="rental-reminder">
                    <h4>Available Rental Items</h4>
                    <p>Please note that the resort offers the following items for rent:</p>
                    <ul>
                        <li>Extra Mattress - ₱150 each (Regular size)</li>
                        <li>Extra Mattress - ₱300 each (Large size)</li>
                        <li>Extra Pillow - ₱50 each</li>
                        <li>Extra Blanket - ₱50 each</li>
                        <li>Extra Tent - ₱800 each (Required for groups over 50 people)</li>
                    </ul>
                    <p>You can request these items upon your arrival at the resort.</p>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Special Requests:</label>
                        <textarea name="special_requests" id="special_requests" rows="3"></textarea>
                    </div>
                </div>
                
                <p class="note">Note: A confirmation email will be sent to your provided email address.</p>
                
                <div class="form-row actions">
                    <a href="home_p1.php" class="btn btn-secondary">Back to Home</a>
                    <button type="submit" class="btn" id="submit-button">Submit Reservation</button>
                </div>
            </form>
        </div>
        <div class="pricing-container" id="pricing-container">
            <h3>Tour Pricing Information</h3>
            <?php
            // Show pricing table based on selected tour type
            $effective_tour_type = $tour_type;
            if ($tour_type === 'whole_day') {
                if ($time === '09:00:00') {
                    $effective_tour_type = 'whole_day_morning';
                } elseif ($time === '20:00:00') {
                    $effective_tour_type = 'whole_day_night';
                }
            }
            
            switch($effective_tour_type) {
                case 'whole_day_morning':
                    echo '<div id="whole_day_morning-pricing" class="tour-pricing">';
                    echo '<h4>Whole Day Tour (Morning Start)</h4>';
                    echo '<p>Time: 9:00 AM to 7:00 AM (next day)</p>';
                    echo '<table class="pricing-table">
                            <tr><th>Number of Guests</th><th>Price (PHP)</th></tr>
                            <tr><td>1-10 persons</td><td>₱12,000</td></tr>
                            <tr><td>11-15 persons</td><td>₱13,000</td></tr>
                            <tr><td>16-20 persons</td><td>₱15,000</td></tr>
                            <tr><td>21-25 persons</td><td>₱16,000</td></tr>
                            <tr><td>26-30 persons</td><td>₱18,000</td></tr>
                            <tr><td colspan="2">Additional ₱600 per person beyond 30</td></tr>
                          </table></div>';
                    break;
                case 'whole_day_night':
                    echo '<div id="whole_day_night-pricing" class="tour-pricing">';
                    echo '<h4>Whole Day Tour (Night Start)</h4>';
                    echo '<p>Time: 8:00 PM to 6:00 PM (next day)</p>';
                    echo '<table class="pricing-table">
                            <tr><th>Number of Guests</th><th>Price (PHP)</th></tr>
                            <tr><td>1-10 persons</td><td>₱12,000</td></tr>
                            <tr><td>11-15 persons</td><td>₱13,000</td></tr>
                            <tr><td>16-20 persons</td><td>₱15,000</td></tr>
                            <tr><td>21-25 persons</td><td>₱16,000</td></tr>
                            <tr><td>26-30 persons</td><td>₱18,000</td></tr>
                            <tr><td colspan="2">Additional ₱600 per person beyond 30</td></tr>
                          </table></div>';
                    break;
                case 'day_tour':
                    echo '<div id="day_tour-pricing" class="tour-pricing">';
                    echo '<h4>Day Tour</h4>';
                    echo '<p>Time: 9:00 AM to 6:00 PM</p>';
                    echo '<table class="pricing-table">
                            <tr><th>Number of Guests</th><th>Price (PHP)</th></tr>
                            <tr><td>1-10 persons</td><td>₱7,000</td></tr>
                            <tr><td>11-15 persons</td><td>₱8,000</td></tr>
                            <tr><td>16-20 persons</td><td>₱9,000</td></tr>
                            <tr><td>21-25 persons</td><td>₱10,000</td></tr>
                            <tr><td>26-30 persons</td><td>₱11,000</td></tr>
                            <tr><td colspan="2">Additional ₱400 per person beyond 30</td></tr>
                          </table></div>';
                    break;
                case 'night_tour':
                    echo '<div id="night_tour-pricing" class="tour-pricing">';
                    echo '<h4>Night Tour</h4>';
                    echo '<p>Time: 8:00 PM to 7:00 AM (next day)</p>';
                    echo '<table class="pricing-table">
                            <tr><th>Number of Guests</th><th>Price (PHP)</th></tr>
                            <tr><td>1-10 persons</td><td>₱8,000</td></tr>
                            <tr><td>11-15 persons</td><td>₱9,000</td></tr>
                            <tr><td>16-20 persons</td><td>₱10,000</td></tr>
                            <tr><td>21-25 persons</td><td>₱11,000</td></tr>
                            <tr><td>26-30 persons</td><td>₱12,000</td></tr>
                            <tr><td colspan="2">Additional ₱500 per person beyond 30</td></tr>
                          </table></div>';
                    break;
            }
            ?>
            
            <!-- Booking Summary section in pricing container -->
            <div class="bill-calculation">
                <h4>Your Reservation Summary</h4>
                <p id="total-guests">Total Guests: 0</p>
                <p id="tour-type-display">Tour Type: Not selected</p>
                <p id="estimated-price">Estimated Price: ₱0</p>
                <p id="tent-fee" style="display: none;">Extra Tent Fee: ₱800</p>
                <!-- New fee displays will be inserted here via JavaScript -->
                <p id="total-price">Total Price: ₱0</p>
                <div id="included-amenities"></div>
                <p id="house-notice"></p>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Get form elements
    const form = document.getElementById("reservation-form");
    const adultCountInput = document.getElementById("adult_count");
    const kidCountInput = document.getElementById("kid_count");
    const extraTentContainer = document.getElementById("extra-tent-container");
    const extraTentCheckbox = document.getElementById("extra_tent");
    const tentFeeElement = document.getElementById("tent-fee");
    const submitButton = document.getElementById("submit-button");
    
    // Get corkage and pet fee elements
    const corkageFeeCheckbox = document.getElementById("corkage_fee");
    const corkageQuantityContainer = document.getElementById("corkage-quantity-container");
    const corkageQuantityInput = document.getElementById("corkage_quantity");
    const petFeeCheckbox = document.getElementById("pet_fee");
    const petQuantityContainer = document.getElementById("pet-quantity-container");
    const petQuantityInput = document.getElementById("pet_quantity");
    
    // Get original values from hidden fields
    const originalTourType = document.getElementById("original_tour_type").value;
    const originalTime = document.getElementById("original_time").value;
    const valuesPreSelected = document.getElementById("values_preselected").value === 'true';
    
    // Pricing data structure
    const pricingData = {
        whole_day_morning: {
            brackets: [
                { max: 10, price: 12000 },
                { max: 15, price: 13000 },
                { max: 20, price: 15000 },
                { max: 25, price: 16000 },
                { max: 30, price: 18000 }
            ],
            additionalPerPerson: 600,
            displayName: "Whole Day (Morning Start)"
        },
        whole_day_night: {
            brackets: [
                { max: 10, price: 12000 },
                { max: 15, price: 13000 },
                { max: 20, price: 15000 },
                { max: 25, price: 16000 },
                { max: 30, price: 18000 }
            ],
            additionalPerPerson: 600,
            displayName: "Whole Day (Night Start)"
        },
        day_tour: {
            brackets: [
                { max: 10, price: 7000 },
                { max: 15, price: 8000 },
                { max: 20, price: 9000 },
                { max: 25, price: 10000 },
                { max: 30, price: 11000 }
            ],
            additionalPerPerson: 400,
            displayName: "Day Tour"
        },
        night_tour: {
            brackets: [
                { max: 10, price: 8000 },
                { max: 15, price: 9000 },
                { max: 20, price: 10000 },
                { max: 25, price: 11000 },
                { max: 30, price: 12000 }
            ],
            additionalPerPerson: 500,
            displayName: "Night Tour"
        }
    };
    
    // Function to validate total guests and show tent requirement
    function validateTotalGuests() {
        const adultCount = parseInt(adultCountInput.value) || 0;
        const kidCount = parseInt(kidCountInput.value) || 0;
        const totalGuests = adultCount + kidCount;
        
        // Show extra tent option for groups over 50
        if (totalGuests > 50) {
            extraTentContainer.style.display = 'flex';
            extraTentCheckbox.checked = true; // Auto-check for groups over 50
        } else {
            extraTentContainer.style.display = 'none';
            extraTentCheckbox.checked = false;
        }
        
        // Cap at maximum guest limit
        const maxGuests = 100;
        if (totalGuests > maxGuests) {
            const adultErrorElement = document.getElementById("adult_count-error");
            if (adultErrorElement) {
                adultErrorElement.textContent = `Total guests cannot exceed ${maxGuests}`;
            }
        } else {
            const adultErrorElement = document.getElementById("adult_count-error");
            if (adultErrorElement) {
                adultErrorElement.textContent = '';
            }
        }
    }

    // Calculate price function with max guest handling
    function calculatePrice() {
        const adultCount = parseInt(adultCountInput.value) || 0;
        const kidCount = parseInt(kidCountInput.value) || 0;
        const totalGuests = adultCount + kidCount;
        const needsExtraTent = totalGuests > 50;
        
        // Get the effective tour type
        let tourType = originalTourType;
        
        // Map old 'whole_day' to specific type if time is available
        if (tourType === 'whole_day') {
            if (originalTime === '09:00:00') {
                tourType = 'whole_day_morning';
            } else if (originalTime === '20:00:00') {
                tourType = 'whole_day_night';
            } else {
                // Default to morning if no time selected
                tourType = 'whole_day_morning';
            }
        }
        
        if (!pricingData[tourType]) {
            return { 
                basePrice: 0, 
                additionalPrice: 0, 
                tentPrice: 0, 
                corkagePrice: 0, 
                petPrice: 0, 
                totalPrice: 0 
            };
        }
        
        // Apply maximum limit
        const maxGuests = 100;
        const validTotalGuests = Math.min(totalGuests, maxGuests);
        
        const pricing = pricingData[tourType];
        
        // Find the appropriate price bracket
        let basePrice = 0;
        for (const bracket of pricing.brackets) {
            if (validTotalGuests <= bracket.max) {
                basePrice = bracket.price;
                break;
            }
        }
        
        // If guests exceed the highest bracket but still under max
        let additionalPrice = 0;
        if (validTotalGuests > 30) {
            const lastBracket = pricing.brackets[pricing.brackets.length - 1];
            basePrice = lastBracket.price;
            additionalPrice = (validTotalGuests - 30) * pricing.additionalPerPerson;
        }
        
        // Add tent fee if required and checked
        let tentPrice = (needsExtraTent && extraTentCheckbox && extraTentCheckbox.checked) ? 800 : 0;
        
        // Calculate corkage fee if checked
        let corkagePrice = 0;
        if (corkageFeeCheckbox && corkageFeeCheckbox.checked) {
            const bottleCount = parseInt(corkageQuantityInput.value) || 1;
            corkagePrice = bottleCount * 100; // ₱100 per bottle
        }
        
        // Calculate pet fee if checked
        let petPrice = 0;
        if (petFeeCheckbox && petFeeCheckbox.checked) {
            const petCount = parseInt(petQuantityInput.value) || 1;
            petPrice = petCount * 200; // ₱200 per dog
        }
        
        const totalPrice = basePrice + additionalPrice + tentPrice + corkagePrice + petPrice;
        
        return { 
            basePrice, 
            additionalPrice, 
            tentPrice, 
            corkagePrice, 
            petPrice, 
            totalPrice 
        };
    }
    
    function updateAmenitiesInfo() {
        // Get the amenities container that we'll add to the HTML
        const amenitiesContainer = document.getElementById("included-amenities");
        const houseNoticeElement = document.getElementById("house-notice");
        
        // Get the current number of guests
        const adultCount = parseInt(adultCountInput.value) || 0;
        const kidCount = parseInt(kidCountInput.value) || 0;
        const totalGuests = adultCount + kidCount;
        
        // Base amenities for all reservations
        const baseAmenities = [
            "House 1 with roofdeck",
            "Kubo House",
            "Kitchen",
            "2 swimming pools",
            "Pavilion"
        ];
        
        // Create the amenities HTML
        if (totalGuests > 0) {
            let amenitiesHTML = '<h4>Included Amenities:</h4><ul>';
            
            // Add base amenities
            baseAmenities.forEach(amenity => {
                amenitiesHTML += `<li>${amenity}</li>`;
            });
            
            // Add House 2 for 30+ guests
            if (totalGuests >= 30) {
                amenitiesHTML += '<li>House 2</li>';
            }
            
            // Add Extra Tent for 50+ guests if checked
            if (totalGuests > 50 && extraTentCheckbox.checked) {
                amenitiesHTML += '<li>Extra Tent</li>';
            }
            
            amenitiesHTML += '</ul>';
            amenitiesContainer.innerHTML = amenitiesHTML;
            
            // Show or hide the house notice
            if (totalGuests > 0 && totalGuests < 30) {
                houseNoticeElement.innerHTML = '<strong>Note:</strong> The 2nd house is not included for groups with less than 30 guests.';
                houseNoticeElement.style.color = '#e74c3c';
                houseNoticeElement.style.display = 'block';
            } else if (totalGuests >= 30) {
                houseNoticeElement.innerHTML = '<strong>Note:</strong> Your reservation includes access to both houses.';
                houseNoticeElement.style.color = '#27ae60';
                houseNoticeElement.style.display = 'block';
            } else {
                houseNoticeElement.style.display = 'none';
            }
        } else {
            // Clear the amenities if no guests are specified
            amenitiesContainer.innerHTML = '';
            houseNoticeElement.style.display = 'none';
        }
    }
    
    // Function to update the fee display elements in the booking summary
    function updateFeeDisplayElements(priceInfo) {
        // Get or create fee elements
        let corkageFeeElement = document.getElementById("corkage-fee");
        if (!corkageFeeElement) {
            corkageFeeElement = document.createElement("p");
            corkageFeeElement.id = "corkage-fee";
            
            // Insert after tent fee
            const tentFeeElement = document.getElementById("tent-fee");
            if (tentFeeElement) {
                tentFeeElement.parentNode.insertBefore(corkageFeeElement, tentFeeElement.nextSibling);
            } else {
                document.getElementById("estimated-price").parentNode.insertBefore(corkageFeeElement, document.getElementById("total-price"));
            }
        }
        
        let petFeeElement = document.getElementById("pet-fee");
        if (!petFeeElement) {
            petFeeElement = document.createElement("p");
            petFeeElement.id = "pet-fee";
            
            // Insert after corkage fee
            if (corkageFeeElement) {
                corkageFeeElement.parentNode.insertBefore(petFeeElement, corkageFeeElement.nextSibling);
            } else {
                document.getElementById("estimated-price").parentNode.insertBefore(petFeeElement, document.getElementById("total-price"));
            }
        }
        
        // Update or hide fee displays
        if (priceInfo.corkagePrice > 0) {
            const bottleCount = parseInt(corkageQuantityInput.value) || 1;
            corkageFeeElement.textContent = `Corkage Fee (${bottleCount} bottle${bottleCount > 1 ? 's' : ''}): ₱${priceInfo.corkagePrice.toLocaleString()}`;
            corkageFeeElement.style.display = 'block';
        } else {
            corkageFeeElement.style.display = 'none';
        }
        
        if (priceInfo.petPrice > 0) {
            const petCount = parseInt(petQuantityInput.value) || 1;
            petFeeElement.textContent = `Pet Fee (${petCount} pet${petCount > 1 ? 's' : ''}): ₱${priceInfo.petPrice.toLocaleString()}`;
            petFeeElement.style.display = 'block';
        } else {
            petFeeElement.style.display = 'none';
        }
    }
    
    // Function to validate total guests doesn't exceed maximum
    function updateGuestInfo() {
        const adultCount = parseInt(adultCountInput.value) || 0;
        const kidCount = parseInt(kidCountInput.value) || 0;
        const totalGuests = adultCount + kidCount;
        let tourType = originalTourType; // Use originalTourType directly since it's preselected
        
        // If tour type is whole_day, determine which specific type based on time
        if (tourType === 'whole_day') {
            if (originalTime === '09:00:00') {
                tourType = 'whole_day_morning';
            } else if (originalTime === '20:00:00') {
                tourType = 'whole_day_night';
            } else {
                // Default to morning if no time selected
                tourType = 'whole_day_morning';
            }
        }
        
        // Update bill calculation
        const totalGuestsElement = document.getElementById("total-guests");
        const tourTypeElement = document.getElementById("tour-type-display");
        const estimatedPriceElement = document.getElementById("estimated-price");
        const tentFeeElement = document.getElementById("tent-fee");
        const totalPriceElement = document.getElementById("total-price");
        
        if (totalGuestsElement) {
            totalGuestsElement.textContent = `Total Guests: ${totalGuests}`;
        }
        
        if (tourType) {
            const priceInfo = calculatePrice();
            const estimatedPrice = priceInfo.basePrice + priceInfo.additionalPrice;
            
            // Get display name from pricing data
            let displayName = '';
            if (pricingData[tourType]) {
                displayName = pricingData[tourType].displayName;
            } else {
                displayName = "Tour Type Unknown";
            }
            
            if (tourTypeElement) {
                tourTypeElement.textContent = `Tour Type: ${displayName}`;
            }
            
            if (estimatedPriceElement) {
                estimatedPriceElement.textContent = `Estimated Price: ₱${estimatedPrice.toLocaleString()}`;
            }
            
            // Update fee displays
            updateFeeDisplayElements(priceInfo);
            
            // Show tent fee if applicable
            if (extraTentCheckbox && extraTentCheckbox.checked && tentFeeElement) {
                tentFeeElement.style.display = 'block';
            } else if (tentFeeElement) {
                tentFeeElement.style.display = 'none';
            }
            
            if (totalPriceElement) {
                totalPriceElement.textContent = `Total Price: ₱${priceInfo.totalPrice.toLocaleString()}`;
            }
            
            updateAmenitiesInfo();
        } else {
            if (tourTypeElement) tourTypeElement.textContent = "Tour Type: Not selected";
            if (estimatedPriceElement) estimatedPriceElement.textContent = "Estimated Price: ₱0";
            if (totalPriceElement) totalPriceElement.textContent = "Total Price: ₱0";
            if (tentFeeElement) tentFeeElement.style.display = 'none';
            updateAmenitiesInfo(); // Also update amenities when no tour type is selected
        }
    }
    
    // Add these event listeners after initializing the inputs
    adultCountInput.addEventListener("input", function() {
        validateTotalGuests();
        updateGuestInfo();
    });

    kidCountInput.addEventListener("input", function() {
        validateTotalGuests();
        updateGuestInfo();
    });

    // Extra tent checkbox event listener
    extraTentCheckbox.addEventListener("change", function() {
        updateGuestInfo();
    });
    
    // Corkage fee checkbox event listener
    if (corkageFeeCheckbox) {
        corkageFeeCheckbox.addEventListener("change", function() {
            if (this.checked) {
                corkageQuantityContainer.style.display = 'block';
            } else {
                corkageQuantityContainer.style.display = 'none';
            }
            updateGuestInfo();
        });
    }
    
    // Pet fee checkbox event listener
    if (petFeeCheckbox) {
        petFeeCheckbox.addEventListener("change", function() {
            if (this.checked) {
                petQuantityContainer.style.display = 'block';
            } else {
                petQuantityContainer.style.display = 'none';
            }
            updateGuestInfo();
        });
    }
    
    // Add event listeners for quantity inputs
    if (corkageQuantityInput) {
        corkageQuantityInput.addEventListener("input", updateGuestInfo);
    }
    
    if (petQuantityInput) {
        petQuantityInput.addEventListener("input", updateGuestInfo);
    }

    // Make sure to call updateGuestInfo() initially to populate the summary
    updateGuestInfo();
    
    // Initial setup for time selection options based on tour type
    function setupTimeOptions() {
        // Skip if time select doesn't exist
        const timeSelect = document.getElementById("time");
        const timeSelectionContainer = document.getElementById("time-selection-container");
        
        if (!timeSelect) return;
        
        // Make sure the time selection shows the correct option
        timeSelect.innerHTML = '';
        
        // Make sure time selection container is visible
        if (timeSelectionContainer) timeSelectionContainer.style.display = 'block';
        
        const effectiveTourType = originalTourType || (document.getElementById("tour_type") ? document.getElementById("tour_type").value : '');
        
        switch(effectiveTourType) {
            case 'whole_day_morning':
                timeSelect.innerHTML = '<option value="09:00:00" selected>9:00 AM to 7:00 AM (next day)</option>';
                break;
            case 'whole_day_night':
                timeSelect.innerHTML = '<option value="20:00:00" selected>8:00 PM to 6:00 PM (next day)</option>';
                break;
            case 'day_tour':
                timeSelect.innerHTML = '<option value="09:00:00" selected>9:00 AM to 6:00 PM</option>';
                break;
            case 'night_tour':
                timeSelect.innerHTML = '<option value="20:00:00" selected>8:00 PM to 7:00 AM (next day)</option>';
                break;
            case 'whole_day':
                // Handle legacy 'whole_day' value
                if (originalTime === '09:00:00') {
                    timeSelect.innerHTML = '<option value="09:00:00" selected>9:00 AM to 7:00 AM (next day)</option>';
                } else if (originalTime === '20:00:00') {
                    timeSelect.innerHTML = '<option value="20:00:00" selected>8:00 PM to 6:00 PM (next day)</option>';
                }
                break;
        }
    }
    
    // Add event listeners for the extra tent checkbox
    if (extraTentCheckbox) {
        extraTentCheckbox.addEventListener('change', function() {
            calculatePrice();
        });
    }
    
    // Add event listeners for guest count validation
    adultCountInput.addEventListener("input", validateTotalGuests);
    kidCountInput.addEventListener("input", validateTotalGuests);
    
    // Form validation with guest limit and tent requirement
    if (form) {
        form.addEventListener("submit", function(event) {
            let errors = {};

            const firstName = document.getElementById("first_name").value.trim();
            if (firstName === "") {
                errors.first_name = "First Name is required";
            }

            const lastName = document.getElementById("last_name").value.trim();
            if (lastName === "") {
                errors.last_name = "Last Name is required";
            }
            
            const email = document.getElementById("email").value.trim();
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (email === "") {
                errors.email = "Email is required";
            } else if (!emailRegex.test(email)) {
                errors.email = "Invalid email format";
            }

            const contactNumber = document.getElementById("contact_number").value.trim();
            const contactNumberRegex = /^\+?[0-9]{10,15}$/;
            if (contactNumber === "") {
                errors.contact_number = "Contact number is required";
            } else if (!contactNumberRegex.test(contactNumber)) {
                errors.contact_number = "Invalid contact number format";
            }
            
            // Validate total guests doesn't exceed maximum
            const adultCount = parseInt(adultCountInput.value) || 0;
            const kidCount = parseInt(kidCountInput.value) || 0;    
            const totalGuests = adultCount + kidCount;
            const maxGuests = 100;
            
            if (adultCount < 1) {
                errors.adult_count = "At least 1 adult is required";
            }
            
            if (totalGuests > maxGuests) {
                errors.adult_count = `Total guests cannot exceed ${maxGuests}`;
            }
            
            // Validate that tent is selected for groups over 50
            if (totalGuests > 50 && extraTentCheckbox && !extraTentCheckbox.checked) {
                errors.extra_tent = "An extra tent is required for groups larger than 50 people";
            }
            
            // Validate pet quantity if checked
            if (petFeeCheckbox && petFeeCheckbox.checked) {
                const petQuantity = parseInt(petQuantityInput.value) || 0;
                if (petQuantity < 1) {
                    errors.pet_fee = "Please specify at least 1 pet";
                }
            }
            
            // Validate corkage quantity if checked
            if (corkageFeeCheckbox && corkageFeeCheckbox.checked) {
                const corkageQuantity = parseInt(corkageQuantityInput.value) || 0;
                if (corkageQuantity < 1) {
                    errors.corkage_fee = "Please specify at least 1 bottle";
                }
            }

            if (Object.keys(errors).length > 0) {
                event.preventDefault();
                document.querySelectorAll('.error-message').forEach(field => field.textContent = '');
                for (const [field, message] of Object.entries(errors)) {
                    const errorElement = document.getElementById(`${field}-error`);
                    if (errorElement) {
                        errorElement.textContent = message;
                    }
                }
            }
        });
    }
    
    // Initialize the interface
    setupTimeOptions();
    
    // Show appropriate pricing table
    function showPricingTable(tourType) {
        // Show the appropriate pricing table
        const tourTypes = ['whole_day_morning', 'whole_day_night', 'day_tour', 'night_tour'];
        
        // Map 'whole_day' to the appropriate version based on time
        let effectiveTourType = tourType;
        if (tourType === 'whole_day') {
            if (originalTime === '09:00:00') {
                effectiveTourType = 'whole_day_morning';
            } else if (originalTime === '20:00:00') {
                effectiveTourType = 'whole_day_night';
            } else {
                effectiveTourType = 'whole_day_morning'; // Default if time not provided
            }
        }
        
        // Hide all pricing tables first
        tourTypes.forEach(type => {
            const table = document.getElementById(`${type}-pricing`);
            if (table) {
                table.style.display = 'none';
            }
        });
        
        // Show the selected one
        const selectedTable = document.getElementById(`${effectiveTourType}-pricing`);
        if (selectedTable) {
            selectedTable.style.display = 'block';
        }
    }
    
    // Show the appropriate pricing table on load
    showPricingTable(originalTourType || (document.getElementById("tour_type") ? document.getElementById("tour_type").value : ''));
    
    // Set a default value of 1 for adult count if empty
    if (!adultCountInput.value) {
        adultCountInput.value = 1;
    }
    
    // Initialize the corkage and pet fee quantity containers as hidden
    if (corkageQuantityContainer) {
        corkageQuantityContainer.style.display = 'none';
    }
    
    if (petQuantityContainer) {
        petQuantityContainer.style.display = 'none';
    }
    
    // Always update guest info on page load
    validateTotalGuests();
});
</script>
</body>
</html>