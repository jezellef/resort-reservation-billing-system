<?php
session_start(); // Start the session at the beginning

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Select Date</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
 
  <style>
    .date-form {
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }
    input, button {
      padding: 10px;
      margin: 10px 0;
      width: 100%;
    }
    button {
      background-color: #89baa9;
      color: white;
      border: none;
      cursor: pointer;
    }
    .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        
        .steps::before {
            content: '';
            position: absolute;
            top: 24px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e0e0e0;
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 25%;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            background-color: #e0e0e0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            color: #777;
            font-weight: bold;
            font-size: 20px;
            transition: all 0.3s ease;
        }
        
        .step-title {
            font-size: 14px;
            color: #777;
            transition: all 0.3s ease;
        }
        
        .step.active .step-number, .step.completed .step-number {
            background-color: #325f51;
            color: white;
        }
        
        .step.active .step-title, .step.completed .step-title {
            color: #325f51;
            font-weight: bold;
        }
        
        .step.completed .step-number::after {
            content: '✓';
        }
        
        .step-content {
            display: none;
        }
        
        .step-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }
        .scontainer {
        max-width: 800px;
        margin: 10px auto;
        padding: 20px;

        }

  </style>
</head>
<body>

<div class="scontainer">
  <form class="date-form" action="available_rooms.php" method="post">

    <h2>Select Your Stay</h2>
        <div class='steps'>
            <div class='step active' id='step-1-indicator' onclick="goToStep(1)">
                <div class='step-number'>1</div>
                <div class='step-title'>Dates</div>
            </div>
            <div class='step' id='step-2-indicator' onclick="goToStep(2)">
                <div class='step-number'>2</div>
                <div class='step-title'>Room Selection</div>
            </div>
            <div class='step' id='step-3-indicator' onclick="goToStep(3)">
                <div class='step-number'>3</div>
                <div class='step-title'>Guest Details</div>
            </div>
            <div class='step' id='step-4-indicator' onclick="goToStep(4)">
                <div class='step-number'>4</div>
                <div class='step-title'>Payment & Confirmation</div>
            </div>
        </div>

        <label>Check-in Date</label>
        <input type="text" name="check_in" id="check_in" required>
        <label>Check-out Date</label>
        <input type="text" name="check_out" id="check_out" required>
        <button type="submit">Search Available Rooms</button>
        </form>
</div>


    
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


  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
  <script>
    flatpickr("#check_in", { minDate: "today" });
    flatpickr("#check_out", { minDate: "today" });
  </script>



</body>
</html>
