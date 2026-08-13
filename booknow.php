<?php
session_start(); // Start the session at the beginning
if (isset($_GET['check_in']) && isset($_GET['check_out'])) {
    $check_in = date('Y-m-d', strtotime($_GET['check_in']));
    $check_out = date('Y-m-d', strtotime($_GET['check_out']));
    if ($check_in && $check_out && $check_in < $check_out) {
        $_SESSION['check_in'] = $check_in;
        $_SESSION['check_out'] = $check_out;
    }
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
    <title>HOME - Rainbow Forest Paradise Resort and Campsite</title>
    <link rel="stylesheet" href="styles/mystyle.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link rel="stylesheet" href="flatpickr.min.css">
    <style>
    .book-now-background {
        background-image: url('images/bg1.png');
        background-size: contain; /* Show the entire image within the container */
        background-position: center;
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .book-now-container {
    background-color: rgba(255, 255, 255, 0.8); /* Semi-transparent white background */
    max-width: 1000px; /* Adjust as needed */
    margin: 10px; /* Adjust margin as needed */
    border-radius: 10px;
    padding: 10px; /* Add padding inside the container */
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); /* Optional: Add a subtle shadow */
    text-align: center; /* Center the content within the container */
    }

    .book-now-heading {
    text-align: center;
    margin-top: 10px;
    color: #333;
    }

    .book-now-calendar-container {
    display: flex;
    justify-content: center;
    gap: 1.5rem;
    flex-wrap: wrap;

    }

    .book-now-calendar-wrapper {
    border: 1px solid #ddd;
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
    }

    .book-now-calendar-wrapper h3 {
    margin-top: 0;
    margin-bottom: 0.5rem;
    color: #555;
    }

    .book-now-form {
    padding: 1.5rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    text-align: center; /* Center form elements */
    }

    .book-now-form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;

    }

    .book-now-form input[type="text"],
    .book-now-form input[type="email"],
    .book-now-form input[type="tel"],
    .book-now-form select {
    padding: 0.75rem;
    border: 1px solid #ccc;
    border-radius: 4px;
    width: 100%;
    box-sizing: border-box;
    }

    .book-now-form button {
    background-color: #28a745; /* Example button color */
    color: white;
    padding: 0.6rem 2.5rem;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    transition: background-color 0.3s ease;
    }

    .book-now-form button:hover {
    background-color: #0056b3;
    }

    .book-now-availability-result {
    margin-top: 1rem;
    font-weight: bold;
    color: #28a745; /* Example success color */
    text-align: center;
    font-size: 1.1rem;
    }
    </style>
</head>
<body>
<?php include 'headers/header.php'; ?>
        <section class="book-now-page book-now-background">
        <div class="book-now-container">
        <h2 class="book-now-heading">Book Your Stay</h2>
        <div class="tour-type-container" style="margin-bottom: 20px;">
            <label for="tourType" style="font-weight: bold; color: #333;"> Please Select Tour Type:</label>
            <select id="tourType" name="tour_type" required style="margin-left: 10px; padding: 5px;">
                <option value="" disabled selected>Select Tour Type</option>
                <option value="whole_day">Whole Day</option>
                <option value="day_tour">Day Tour</option>
                <option value="night_tour">Night Tour</option>
            </select>

            <div id="dayTourInfo" style="margin-top: 8px; font-size: small; color: black; display: none;">
                Note: If you select "Day Tour", you will only be able to select one day.
            </div>

            <div style="margin-top: 10px; font-weight: bold; color: #d9534f;">
                 Day Tour: 9:00 AM - 6:00 PM <br>
                 Night Tour: 8:00 PM - 7:00 AM <br>
                 Whole Day / 22 hrs Package:<br>
                &nbsp;&nbsp;&nbsp;&nbsp; 9:00 AM - 7:00 AM <br>
                &nbsp;&nbsp;&nbsp;&nbsp; 8:00 PM - 6:00 PM
            </div>

        </div>
            <div class="book-now-calendar-container">
                <input type="hidden" id="check_in" name="check_in">
                <div class="book-now-calendar-wrapper">
                    <h3>Check-in</h3>
                    <div id="calendarCheckIn"></div>
                </div>
                <div class="book-now-calendar-wrapper" id="checkOutContainer">
                    <h3>Check-out</h3>
                    <input type="hidden" id="check_out" name="check_out" readonly>
                    <div id="calendarCheckOut"></div>
                </div>
            </div>
            <form id="bookingFormDetails" class="book-now-form">
                <button type="button" id="checkAvailability" class="book-now-button">Check Availability</button>
                <div id="availabilityResult" class="book-now-availability-result"></div>
            </form>
        </div>
    </section>

    <script>
    $(document).ready(function(){
        const tourTypeSelect = document.getElementById('tourType');
        const initialTourType = "<?php echo isset($_SESSION['tour_type']) ? htmlspecialchars($_SESSION['tour_type']) : ''; ?>";
        tourTypeSelect.value = initialTourType;
        const dayTourInfoDiv = document.getElementById('dayTourInfo');
        const checkOutContainer = document.getElementById('checkOutContainer');
        const checkOutInput = document.getElementById('check_out');
        const calendarCheckOut = document.getElementById('calendarCheckOut');
        const checkInContainer = document.getElementById('checkInContainer'); // Assuming you have a container for check-in

        tourTypeSelect.addEventListener('change', function() {
            if (this.value === 'day_tour') {
                if (dayTourInfoDiv) {
                    dayTourInfoDiv.style.display = 'block';
                }
                if (checkOutContainer) {
                    checkOutContainer.style.display = 'none';
                }
                checkOutInput.value = ""; // Clear check-out input
            } else {
                if (dayTourInfoDiv) {
                    dayTourInfoDiv.style.display = 'none';
                }
                if (checkOutContainer) {
                    checkOutContainer.style.display = 'block';
                }
            }
        });
        $("#tourType").change(function() {
            var selectedType = $(this).val();
            if (selectedType === "day_tour") {
                $("#calendarCheckOut").hide(); // hide check-out calendar
                $("#check_out").prop("disabled", true); // Disable the input field
                $("#check_out").val("");       // clear check-out input
            } else {
                $("#calendarCheckOut").show(); // show calendar again
                $("#check_out").prop("disabled", false); // Enable the input field
            }
        });
        var today = new Date().toISOString().split('T')[0];
        $("#check_in").attr("min", today);
        $("#check_out").attr("min", today);
        $("#checkAvailability").click(function() {
            var check_in = $("#check_in").val();
            var check_out = $("#check_out").val();
            var tour_type = $("#tourType").val(); // Get tour type
             if (!tour_type) {
                $("#availabilityResult").html("<p style='color: red;'>Please select a tour type.</p>");
                return;
            }
            if (!check_in) {
                $("#availabilityResult").html("<p style='color: red;'>Please select a check-in date.</p>");
                return;
            }
            if (tour_type !== "day_tour" && !check_out) {
                $("#availabilityResult").html("<p style='color: red;'>Please select a check-out date.</p>");
                return;
            }
            if (check_in === today) {
                $("#availabilityResult").html("<p style='color: red;'>Same-day check-in is not allowed. Please select a future date.</p>");
                return;
            }
            if (tour_type !== "day_tour" && check_in >= check_out) {
                $("#availabilityResult").html("<p style='color: red;'>Check-out date must be after check-in date.</p>");
                return;
            }
            $("#availabilityResult").html("<p class='loading'>Checking availability...</p>");
            $.ajax({
                url: "check_availability.php",
                type: "POST",
                data: { check_in: check_in, check_out: check_out, tour_type: tour_type }, // Send tour type
                success: function(response){
                    $("#availabilityResult").html(response);
                    $(".reservation-buttons, #proceedToReservation").parent("div").remove();
                    if(response.includes("Available!")) {
                        sessionStorage.setItem('check_in', check_in);
                        sessionStorage.setItem('check_out', check_out);
                        $.ajax({
                            url: "check_login.php",
                            type: "GET",
                            success: function(loginResponse) {
                                if (loginResponse.trim() === "logged_in") {
                                    // User is logged in, show normal button as direct link
                                    $("#availabilityResult").append(`
                                        <div style="display: flex; justify-content: center; margin-top: 15px;">
                                            <a href="reservation_form.php?check_in=${encodeURIComponent(check_in)}&check_out=${encodeURIComponent(check_out)}&tour_type=${encodeURIComponent(tour_type)}" class="btn" id="proceedToReservation" style="padding: 10px 18px; background: green; color: white; text-decoration: none; border-radius: 5px; cursor: pointer;">Proceed to Reservation</a>
                                        </div>
                                    `);
                                } else {
                                    // User is not logged in, show both options as direct links
                                    $("#availabilityResult").append(`
                                        <div class="reservation-buttons" style="display: flex; justify-content: center; gap: 10px; margin-top: 15px;">
                                            <a href="guest_reservation.php?check_in=${encodeURIComponent(check_in)}&check_out=${encodeURIComponent(check_out)}&tour_type=${encodeURIComponent(tour_type)}" class="btn" id="guestReservation" style="padding: 10px 18px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; cursor: pointer;">Guest Checkout</a>
                                            <a href="login.php?check_in=${encodeURIComponent(check_in)}&check_out=${encodeURIComponent(check_out)}&tour_type=${encodeURIComponent(tour_type)}" class="btn" id="loginToReserve" style="padding: 10px 18px; background: #03624c; color: white; text-decoration: none; border-radius: 5px; cursor: pointer;">Login to Reserve</a>
                                        </div>
                                    `);
                                }
                            }
                        });
                    }
                }
            });
        });
        $(document).on("click", "#proceedToReservation", function(){
            var check_in = sessionStorage.getItem('check_in');
            var check_out = sessionStorage.getItem('check_out');
            var tour_type = $("#tourType").val();
            window.location.href = "reservation_form.php?check_in=" + encodeURIComponent(check_in) + "&check_out=" + encodeURIComponent(check_out) + "&tour_type=" + encodeURIComponent(tour_type);
        });
        $(document).on("click", "#guestReservation", function(){
            var check_in = sessionStorage.getItem('check_in');
            var check_out = sessionStorage.getItem('check_out');
            var tour_type = $("#tourType").val();
            window.location.href = "guest_reservation.php?check_in=" + encodeURIComponent(check_in) + "&check_out=" + encodeURIComponent(check_out) + "&tour_type=" + encodeURIComponent(tour_type);
        });
        $(document).on("click", "#loginToReserve", function(){
            var check_in = sessionStorage.getItem('check_in');
            var check_out = sessionStorage.getItem('check_out');
            var tour_type = $("#tourType").val();
            window.location.href = "login.php?check_in=" + encodeURIComponent(check_in) + "&check_out=" + encodeURIComponent(check_out) + "&tour_type=" + encodeURIComponent(tour_type);
        });
    });
    function proceedToReservationFunc(check_in, check_out) {
        var tour_type = $("#tourType").val();
        window.location.href = "reservation_form.php?check_in=" + encodeURIComponent(check_in) + "&check_out=" + encodeURIComponent(check_out) + "&tour_type=" + encodeURIComponent(tour_type);
    }
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script>
    flatpickr("#calendarCheckIn", {
        inline: true,
        minDate: "today",
        onChange: function(selectedDates, dateStr, instance) {
            $("#check_in").val(dateStr);
        }
    });
    flatpickr("#calendarCheckOut", {
        inline: true,
        minDate: "today",
        onChange: function(selectedDates, dateStr, instance) {
            $("#check_out").val(dateStr);
        }
    });
</script>
<script>
    flatpickr("#calendarCheckIn", {
        inline: true,
        minDate: "today",
        onChange: function(selectedDates, dateStr, instance) {
            $("#check_in").val(dateStr);
        }
    });
    flatpickr("#calendarCheckOut", {
        inline: true,
        minDate: "today",
        onChange: function(selectedDates, dateStr, instance) {
            $("#check_out").val(dateStr);
        }
    });
</script>

    <script>
        function toggleMenu() {
            const menu = document.querySelector('.menu');
            const hamburger = document.querySelector('.hamburger');
            const hamburgerVertical = document.querySelector('.hamburger-vertical');
            const header = document.querySelector('.page-header');
            menu.classList.toggle('active');
            header.classList.toggle('hidden');
            if (menu.classList.contains('active')) {
                hamburger.style.display = 'none';
                hamburgerVertical.style.display = 'block';
            } else {
                hamburger.style.display = 'block';
                hamburgerVertical.style.display = 'none';
            }
        }

        function bookNow(phase) {
            alert(`You clicked Book Now for ${phase}!`);
        }
    </script>
</body>
</html>