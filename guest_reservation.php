    <?php
    session_start(); 
    $user = [];
    if (isset($_SESSION["user_id"])) {
        $mysqli = require 'database.php';
        $stmt = $mysqli->prepare("SELECT first_name, last_name, email, contact_number FROM user WHERE id = ?");
        if ($stmt === false) {
            die("Error preparing the query: " . $mysqli->error);
        }
        $stmt->bind_param("i", $_SESSION["user_id"]);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc(); 
        $stmt->close();
        $mysqli->close();
    }

    // Store check-in, check-out dates from URL parameters
    if (isset($_GET['check_in']) && isset($_GET['check_out'])) {
        $_SESSION['check_in'] = $_GET['check_in'];
        $_SESSION['check_out'] = $_GET['check_out'];
    }

    // Get tour_type and time from URL parameters if available
    $tour_type = isset($_GET['tour_type']) ? $_GET['tour_type'] : '';
    $time = isset($_GET['time']) ? $_GET['time'] : '';

    // Convert tour_type to the appropriate field values
    $day_tour = ($tour_type === 'day_tour') ? 1 : 0;
    $whole_day_morning_tour = ($tour_type === 'whole_day_morning') ? 1 : 0;
    $whole_day_night_tour = ($tour_type === 'whole_day_night') ? 1 : 0;
    $night_tour = ($tour_type === 'night_tour') ? 1 : 0;

    // Map original tour_type to new structure
    if ($tour_type === 'whole_day' && $time === '09:00:00') {
        $tour_type = 'whole_day_morning';
        $whole_day_morning_tour = 1;
    } else if ($tour_type === 'whole_day' && $time === '20:00:00') {
        $tour_type = 'whole_day_night';
        $whole_day_night_tour = 1;
    }

    // Always set this to true to enforce prefilled fields
    $valuesPreSelected = true;
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
        <div class="preselected-info">
            <p><strong>Your tour preferences have been saved.</strong> The dates, tour type, and time you selected on the homepage are locked in for this reservation.</p>
        </div>
        <div class="form-container">
            <?php if (!isset($_SESSION["user_id"])): ?>
            <div class="login-banner">
                <p>Not logged in? You can still make a reservation as a guest.</p>
                <p>Already have an account? <a href="login.php?check_in=<?php echo urlencode($_SESSION['check_in'] ?? ''); ?>&check_out=<?php echo urlencode($_SESSION['check_out'] ?? ''); ?><?php echo $tour_type ? '&tour_type='.urlencode($tour_type) : ''; ?><?php echo $time ? '&time='.urlencode($time) : ''; ?>">Login</a> to auto-fill your information.</p>
            </div>
            <?php endif; ?>
            
            <form action="guest_reservation_process.php" method="POST" class="reservation-form" id="reservation-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Check-in Date:</label>
                        <input type="date" name="check_in_display" id="check_in" required value="<?php echo isset($_SESSION['check_in']) ? htmlspecialchars($_SESSION['check_in']) : ''; ?>" disabled>
                        <div id="check_in-error" class="error-message"></div>
                        <div class="date-locked-message">This date is locked for your reservation.</div>
                        <!-- Hidden field to ensure value is submitted with the form -->
                        <input type="hidden" name="check_in" value="<?php echo htmlspecialchars($_SESSION['check_in'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Check-out Date:</label>
                        <input type="date" name="check_out_display" id="check_out" required value="<?php echo isset($_SESSION['check_out']) ? htmlspecialchars($_SESSION['check_out']) : ''; ?>" disabled>
                        <div id="check_out-error" class="error-message"></div>
                        <div class="date-locked-message">This date is locked for your reservation.</div>
                        <!-- Hidden field to ensure value is submitted with the form -->
                        <input type="hidden" name="check_out" value="<?php echo htmlspecialchars($_SESSION['check_out'] ?? ''); ?>">
                    </div>
                </div>
                <!-- Set dates_available to available by default -->
                <input type="hidden" id="dates_available" name="dates_available" value="available">
                
                <div class="form-row">
                    <div class="form-group">
                        <label>First Name:</label>
                        <input type="text" name="first_name" id="first_name" required value="<?php echo htmlspecialchars($user['first_name'] ?? ''); ?>">
                        <div id="first_name-error" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label>Last Name:</label>
                        <input type="text" name="last_name" id="last_name" required value="<?php echo htmlspecialchars($user['last_name'] ?? ''); ?>">
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
                        <input type="text" name="contact_number" id="contact_number" required value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
                        <div id="contact_number-error" class="error-message"></div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Number of Guests(Adults):</label>
                        <input type="number" name="adult_count" id="adult_count" min="1" required value="1">
                        <div id="adult_count-error" class="error-message"></div>
                    </div>
                    <div class="form-group">
                        <label>Number of Guests(Kids):</label>
                        <input type="number" name="kid_count" id="kid_count" min="0" value="0">
                        <div id="kid_count-error" class="error-message"></div>
                    </div>
                </div>
                
                <!-- Extra tent section - will show automatically when needed -->
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
                                <input type="number" name="corkage_quantity" id="corkage_quantity" min="1" value="">
                            </div>
                        </div>
                        <div id="corkage_quantity-error" class="error-message"></div>
                    </div>
                </div>
                
                <!-- Modified pet fee section -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="pet_fee">Pet Fee:</label>
                        <div class="fee-info">
                            <input type="checkbox" name="pet_fee" id="pet_fee" value="1">
                            <span>Bringing pets (₱200 per pet, medium-sized only, 15kg or below)</span>
                            <div class="quantity-selector" id="pet-quantity-container" style="display: none;">
                                <label for="pet_quantity">Number of pets:</label>
                                <input type="number" name="pet_quantity" id="pet_quantity" min="1" value="">
                            </div>
                        </div>
                        <div id="pet_quantity-error" class="error-message"></div>
                        <div class="pet-policy">
                            <small style="color:#d35400;">Note: Only medium-sized dogs (15kg or below) are allowed in the resort.</small>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tour Type:</label>
                        <select name="tour_type_display" disabled>
                            <option value="day_tour" <?php if ($tour_type == 'day_tour') echo 'selected'; ?>>Day Tour</option>
                            <option value="night_tour" <?php if ($tour_type == 'night_tour') echo 'selected'; ?>>Night Tour</option>
                            <option value="whole_day_morning" <?php if ($tour_type == 'whole_day_morning') echo 'selected'; ?>>Whole Day (Morning)</option>
                            <option value="whole_day_night" <?php if ($tour_type == 'whole_day_night') echo 'selected'; ?>>Whole Day (Night)</option>
                        </select>
                        <div id="tour_type-error" class="error-message"></div>
                        
                        <!-- Hidden fields for the actual database columns and to ensure values are submitted -->
                        <input type="hidden" name="tour_type" value="<?php echo htmlspecialchars($tour_type); ?>">
                        <input type="hidden" name="day_tour" id="day_tour_field" value="<?php echo $day_tour; ?>">
                        <input type="hidden" name="whole_day_morning_tour" id="whole_day_morning_tour_field" value="<?php echo $whole_day_morning_tour; ?>">
                        <input type="hidden" name="whole_day_night_tour" id="whole_day_night_tour_field" value="<?php echo $whole_day_night_tour; ?>">
                        <input type="hidden" name="night_tour" id="night_tour_field" value="<?php echo $night_tour; ?>">
                        
                        <div class="date-locked-message">Tour type is locked for your reservation.</div>
                    </div>
                </div>
                
                <!-- Time selection container -->
                <div class="form-row" id="time-selection-container">
                    <div class="form-group">
                        <label>Preferred Time:</label>
                        <select name="time_display" disabled>
                            <?php if ($tour_type === 'whole_day_morning'): ?>
                                <option value="09:00:00" selected>9:00 AM to 7:00 AM (next day)</option>
                            <?php elseif ($tour_type === 'whole_day_night'): ?>
                                <option value="20:00:00" selected>8:00 PM to 6:00 PM (next day)</option>
                            <?php elseif ($tour_type === 'day_tour'): ?>
                                <option value="09:00:00" selected>9:00 AM to 6:00 PM</option>
                            <?php elseif ($tour_type === 'night_tour'): ?>
                                <option value="20:00:00" selected>8:00 PM to 7:00 AM (next day)</option>
                            <?php endif; ?>
                        </select>
                        <!-- Hidden field to ensure value is submitted with the form -->
                        <input type="hidden" name="time" value="<?php echo htmlspecialchars($time); ?>">
                        <div class="date-locked-message">Time is locked for your reservation.</div>
                    </div>
                </div>
                
                <!-- Hidden input to store original tour_type and time for JS -->
                <input type="hidden" id="original_tour_type" value="<?php echo htmlspecialchars($tour_type); ?>">
                <input type="hidden" id="original_time" value="<?php echo htmlspecialchars($time); ?>">
                <input type="hidden" id="values_preselected" value="true">
                
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
            <div id="whole_day_morning-pricing" class="tour-pricing">
                <h4>Whole Day Tour (Morning Start)</h4>
                <p>Time: 9:00 AM to 7:00 AM (next day)</p>
                <table class="pricing-table">
                    <tr>
                        <th>Number of Guests</th>
                        <th>Price (PHP)</th>
                    </tr>
                    <tr>
                        <td>1-10 persons</td>
                        <td>₱12,000</td>
                    </tr>
                    <tr>
                        <td>11-15 persons</td>
                        <td>₱13,000</td>
                    </tr>
                    <tr>
                        <td>16-20 persons</td>
                        <td>₱15,000</td>
                    </tr>
                    <tr>
                        <td>21-25 persons</td>
                        <td>₱16,000</td>
                    </tr>
                    <tr>
                        <td>26-30 persons</td>
                        <td>₱18,000</td>
                    </tr>
                    <tr>
                        <td colspan="2">Additional ₱600 per person beyond 30</td>
                    </tr>
                </table>
            </div>
            <div id="whole_day_night-pricing" class="tour-pricing">
                <h4>Whole Day Tour (Night Start)</h4>
                <p>Time: 8:00 PM to 6:00 PM (next day)</p>
                <table class="pricing-table">
                    <tr>
                        <th>Number of Guests</th>
                        <th>Price (PHP)</th>
                    </tr>
                    <tr>
                        <td>1-10 persons</td>
                        <td>₱12,000</td>
                    </tr>
                    <tr>
                        <td>11-15 persons</td>
                        <td>₱13,000</td>
                    </tr>
                    <tr>
                        <td>16-20 persons</td>
                        <td>₱15,000</td>
                    </tr>
                    <tr>
                        <td>21-25 persons</td>
                        <td>₱16,000</td>
                    </tr>
                    <tr>
                        <td>26-30 persons</td>
                        <td>₱18,000</td>
                    </tr>
                    <tr>
                        <td colspan="2">Additional ₱600 per person beyond 30</td>
                    </tr>
                </table>
            </div>
            <div id="day_tour-pricing" class="tour-pricing">
                <h4>Day Tour</h4>
                <p>Time: 9:00 AM to 6:00 PM</p>
                <table class="pricing-table">
                    <tr>
                        <th>Number of Guests</th>
                        <th>Price (PHP)</th>
                    </tr>
                    <tr>
                        <td>1-10 persons</td>
                        <td>₱7,000</td>
                    </tr>
                    <tr>
                        <td>11-15 persons</td>
                        <td>₱8,000</td>
                    </tr>
                    <tr>
                        <td>16-20 persons</td>
                        <td>₱9,000</td>
                    </tr>
                    <tr>
                        <td>21-25 persons</td>
                        <td>₱10,000</td>
                    </tr>
                    <tr>
                        <td>26-30 persons</td>
                        <td>₱11,000</td>
                    </tr>
                    <tr>
                        <td colspan="2">Additional ₱400 per person beyond 30</td>
                    </tr>
                </table>
            </div>
            <div id="night_tour-pricing" class="tour-pricing">
                <h4>Night Tour</h4>
                <p>Time: 8:00 PM to 7:00 AM (next day)</p>
                <table class="pricing-table">
                    <tr>
                        <th>Number of Guests</th>
                        <th>Price (PHP)</th>
                    </tr>
                    <tr>
                        <td>1-10 persons</td>
                        <td>₱8,000</td>
                    </tr>
                    <tr>
                        <td>11-15 persons</td>
                        <td>₱9,000</td>
                    </tr>
                    <tr>
                        <td>16-20 persons</td>
                        <td>₱10,000</td>
                    </tr>
                    <tr>
                        <td>21-25 persons</td>
                        <td>₱11,000</td>
                    </tr>
                    <tr>
                        <td>26-30 persons</td>
                        <td>₱12,000</td>
                    </tr>
                    <tr>
                        <td colspan="2">Additional ₱500 per person beyond 30</td>
                    </tr>
                </table>
            </div>
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
    const form = document.getElementById("reservation-form");
    const adultCountInput = document.getElementById("adult_count");
    const kidCountInput = document.getElementById("kid_count");
    const pricingContainer = document.getElementById("pricing-container");
    const extraTentContainer = document.getElementById("extra-tent-container");
    const extraTentCheckbox = document.getElementById("extra_tent");
    const tentFeeElement = document.getElementById("tent-fee");
    const submitButton = document.getElementById("submit-button");
    const datesAvailableField = document.getElementById("dates_available");
    
    // Define these variables to fix references
    const tourTypeSelect = document.querySelector('select[name="tour_type_display"]');
    const timeSelect = document.querySelector('select[name="time_display"]');
    
    // Original tour type and time values
    const originalTourType = document.getElementById("original_tour_type").value;
    const originalTime = document.getElementById("original_time").value;
    
    // Elements for bill calculation
    const totalGuestsElement = document.getElementById("total-guests");
    const tourTypeElement = document.getElementById("tour-type-display");
    const estimatedPriceElement = document.getElementById("estimated-price");
    const totalPriceElement = document.getElementById("total-price");
    
    // New fee elements
    const corkageFeeCheckbox = document.getElementById("corkage_fee");
    const corkageQuantityContainer = document.getElementById("corkage-quantity-container");
    const corkageQuantityInput = document.getElementById("corkage_quantity");
    const petFeeCheckbox = document.getElementById("pet_fee");
    const petQuantityContainer = document.getElementById("pet-quantity-container");
    const petQuantityInput = document.getElementById("pet_quantity");
    
    // Create elements for fee display in summary
    const corkageFeeElement = document.createElement("p");
    corkageFeeElement.id = "corkage-fee";
    corkageFeeElement.style.display = "none";
    
    const petFeeElement = document.createElement("p");
    petFeeElement.id = "pet-fee";
    petFeeElement.style.display = "none";
    
    corkageFeeCheckbox.checked = false;
    petFeeCheckbox.checked = false;
    corkageFeeElement.style.display = 'none';
    petFeeElement.style.display = 'none';
    corkageQuantityContainer.style.display = 'none';
    petQuantityContainer.style.display = 'none';
    tentFeeElement.style.display = 'none';
    
    // Insert fee elements before the total price
    totalPriceElement.parentNode.insertBefore(corkageFeeElement, totalPriceElement);
    totalPriceElement.parentNode.insertBefore(petFeeElement, totalPriceElement);
    
    // Add guest limit notice
    const adultCountGroup = adultCountInput.parentNode;
    const guestLimitNotice = document.createElement("div");
    guestLimitNotice.className = "guest-limit-notice";
    guestLimitNotice.innerHTML = "<small style='color:#03624c;'>Maximum is 100 total guests (adults + children) allowed per reservation. Groups with more than 50 guests require an extra tent.</small>";
    adultCountGroup.appendChild(guestLimitNotice);
    
    // Function to validate total guests doesn't exceed maximum
    function validateTotalGuests() {
        const adultCount = parseInt(adultCountInput.value) || 0;
        const kidCount = parseInt(kidCountInput.value) || 0;
        const totalGuests = adultCount + kidCount;
        
        const maxGuests = 100;
        const adultCountError = document.getElementById("adult_count-error");
        const kidCountError = document.getElementById("kid_count-error");
        
        // Clear previous error messages
        adultCountError.textContent = "";
        kidCountError.textContent = "";
        
        if (totalGuests > maxGuests) {
            // Determine which field was last changed to show the error there
            const lastChangedField = document.activeElement;
            if (lastChangedField === adultCountInput) {
                adultCountError.textContent = `Total guests cannot exceed ${maxGuests}. Please reduce the number.`;
            } else if (lastChangedField === kidCountInput) {
                kidCountError.textContent = `Total guests cannot exceed ${maxGuests}. Please reduce the number.`;
            } else {
                // If unsure which was last changed, show error on both
                adultCountError.textContent = `Combined total cannot exceed ${maxGuests} guests.`;
            }
            return false;
        }
        
        // Show/hide extra tent option based on guest count
        if (totalGuests > 50) {
            extraTentContainer.style.display = 'block';
            extraTentCheckbox.checked = true; // Auto-check the tent option
            document.getElementById("tent-required-note").style.fontWeight = "bold";
            document.getElementById("tent-required-note").style.color = "#d35400";
        } else {
            extraTentContainer.style.display = 'none';
            extraTentCheckbox.checked = false;
        }
        
        return true;
    }
    
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

    // Function to show pricing table based on tour type
    function showPricingTable(tourType) {
        // Hide all pricing tables first
        document.querySelectorAll('.tour-pricing').forEach(table => {
            table.style.display = 'none';
        });
        
        // Always show the pricing container
        pricingContainer.style.display = 'block';
        
        // Map old 'whole_day' to specific type if time is available
        if (tourType === 'whole_day') {
            if (originalTime === '09:00:00') {
                tourType = 'whole_day_morning';
            } else if (originalTime === '20:00:00') {
                tourType = 'whole_day_night';
            }
        }
        
        // Show the relevant pricing table if a tour type is selected
        if (tourType) {
            const selectedTable = document.getElementById(`${tourType}-pricing`);
            if (selectedTable) {
                selectedTable.style.display = 'block';
            }
        }
    }

    // Function to calculate price based on tour type and guest count
    function calculatePrice(tourType, totalGuests) {
        // Map old 'whole_day' to specific type based on time
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
            return 0;
        }
        
        // Apply maximum limit
        const maxGuests = 100;
        totalGuests = Math.min(totalGuests, maxGuests);
        
        const pricing = pricingData[tourType];
        
        // Find the appropriate price bracket
        let basePrice = 0;
        for (const bracket of pricing.brackets) {
            if (totalGuests <= bracket.max) {
                basePrice = bracket.price;
                break;
            }
        }
        
        // If guests exceed the highest bracket but still under max
        if (basePrice === 0) {
            const lastBracket = pricing.brackets[pricing.brackets.length - 1];
            const extraGuests = totalGuests - 30;
            basePrice = lastBracket.price + (extraGuests * pricing.additionalPerPerson);
        }
        
        return basePrice;
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

    // Modified update guest info function with all fees
    function updateGuestInfo() {
        const adultCount = parseInt(adultCountInput.value) || 0;
        const kidCount = parseInt(kidCountInput.value) || 0;
        const totalGuests = adultCount + kidCount;
        let tourType = originalTourType; // Use originalTourType directly since it's preselected
        
        // Update bill calculation
        totalGuestsElement.textContent = `Total Guests: ${totalGuests}`;
        
        if (tourType) {
            const estimatedPrice = calculatePrice(tourType, totalGuests);
            
            // Get display name from pricing data
            let displayName = '';
            if (pricingData[tourType]) {
                displayName = pricingData[tourType].displayName;
            } else if (tourType === 'whole_day') {
                // Handle legacy 'whole_day' value
                if (originalTime === '09:00:00') {
                    displayName = pricingData['whole_day_morning'].displayName;
                } else if (originalTime === '20:00:00') {
                    displayName = pricingData['whole_day_night'].displayName;
                } else {
                    displayName = "Whole Day";
                }
            }
            
            tourTypeElement.textContent = `Tour Type: ${displayName}`;
            estimatedPriceElement.textContent = `Estimated Price: ₱${estimatedPrice.toLocaleString()}`;
            
            // Calculate total with all fees
            let totalPrice = estimatedPrice;
            
            // Add tent fee if applicable
            if (extraTentCheckbox.checked) {
                tentFeeElement.style.display = 'block';
                tentFeeElement.textContent = `Extra Tent Fee: ₱800`;
                totalPrice += 800; // Add tent fee to total
            } else {
                tentFeeElement.style.display = 'none';
            }
            
            // Add corkage fee if applicable - FIXED to check the checkbox AND quantity
            if (corkageFeeCheckbox.checked) {
                const corkageQuantity = parseInt(corkageQuantityInput.value) || 0;
                if (corkageQuantity > 0) {
                    const corkageTotal = corkageQuantity * 100; // ₱100 per bottle
                    corkageFeeElement.textContent = `Corkage Fee (${corkageQuantity} bottle${corkageQuantity > 1 ? 's' : ''}): ₱${corkageTotal}`;
                    corkageFeeElement.style.display = 'block';
                    totalPrice += corkageTotal;
                } else {
                    corkageFeeElement.style.display = 'none';
                }
            } else {
                corkageFeeElement.style.display = 'none';
                // Reset quantity input when checkbox is unchecked
                corkageQuantityInput.value = '';
            }
            
            // Add pet fee if applicable - FIXED to check the checkbox AND quantity
            if (petFeeCheckbox.checked) {
                const petQuantity = parseInt(petQuantityInput.value) || 0;
                if (petQuantity > 0) {
                    const petTotal = petQuantity * 200; // ₱200 per dog
                    petFeeElement.textContent = `Pet Fee (${petQuantity} pet${petQuantity > 1 ? 's' : ''}): ₱${petTotal}`;
                    petFeeElement.style.display = 'block';
                    totalPrice += petTotal;
                } else {
                    petFeeElement.style.display = 'none';
                }
            } else {
                petFeeElement.style.display = 'none';
                // Reset quantity input when checkbox is unchecked
                petQuantityInput.value = '';
            }
            
            totalPriceElement.textContent = `Total Price: ₱${totalPrice.toLocaleString()}`;
            updateAmenitiesInfo();
        } else {
            tourTypeElement.textContent = "Tour Type: Not selected";
            estimatedPriceElement.textContent = "Estimated Price: ₱0";
            totalPriceElement.textContent = "Total Price: ₱0";
            tentFeeElement.style.display = 'none';
            corkageFeeElement.style.display = 'none';
            petFeeElement.style.display = 'none';
            updateAmenitiesInfo();
        }
    }
    
    // Modified event handlers for checkboxes
    corkageFeeCheckbox.addEventListener("change", function() {
        if (this.checked) {
            corkageQuantityContainer.style.display = 'block';
            // Set default value when checked
            if (!corkageQuantityInput.value || corkageQuantityInput.value == '0') {
                corkageQuantityInput.value = 1;
            }
        } else {
            corkageQuantityContainer.style.display = 'none';
            // Reset quantity value when unchecked to ensure it's not included in calculations
            corkageQuantityInput.value = '';
        }
        updateGuestInfo(); // Update billing summary
    });

    petFeeCheckbox.addEventListener("change", function() {
        if (this.checked) {
            petQuantityContainer.style.display = 'block';
            // Set default value when checked
            if (!petQuantityInput.value || petQuantityInput.value == '0') {
                petQuantityInput.value = 1;
            }
        } else {
            petQuantityContainer.style.display = 'none';
            // Reset quantity value when unchecked to ensure it's not included in calculations
            petQuantityInput.value = '';
        }
        updateGuestInfo(); // Update billing summary
    });
    
    // Add event listeners for quantity changes
    corkageQuantityInput.addEventListener("input", updateGuestInfo);
    petQuantityInput.addEventListener("input", updateGuestInfo);
    
    // Modified event listeners with guest validation
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
    
    // Initial setup for preselected values
    if (originalTourType) {
        // Display the pricing for the selected tour type
        showPricingTable(originalTourType);
        
        // Calculate and display guest info with the selected tour type
        setTimeout(() => {
            updateGuestInfo();
        }, 100);
    } else {
        // Initial hide of all pricing tables if not preselected
        showPricingTable(null);
    }
    
    // Set a default value of 1 for adult count if empty
    if (!adultCountInput.value) {
        adultCountInput.value = 1;
    }
    
    // Always update guest info on page load
    updateGuestInfo();

    // Form validation with guest limit and tent requirement
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
        const contactNumberRegex = /^09\d{9}$/;
        if (contactNumber === "") {
            errors.contact_number = "Contact number is required";
        } else if (!contactNumberRegex.test(contactNumber)) {
            errors.contact_number = "Contact number must start with 09 and be 11 digits in total";
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
        if (totalGuests > 50 && !extraTentCheckbox.checked) {
            errors.extra_tent = "An extra tent is required for groups larger than 50 people";
        }
        
        // Validate pet quantity if pet fee is checked
        if (petFeeCheckbox.checked) {
            const petQuantity = parseInt(petQuantityInput.value) || 0;
            if (petQuantity < 1) {
                errors.pet_quantity = "Please specify at least 1 dog";
            }
        }
        
        // Validate corkage quantity if corkage fee is checked
        if (corkageFeeCheckbox.checked) {
            const corkageQuantity = parseInt(corkageQuantityInput.value) || 0;
            if (corkageQuantity < 1) {
                errors.corkage_quantity = "Please specify at least 1 bottle";
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
});
    </script>
    </body>
    </html>